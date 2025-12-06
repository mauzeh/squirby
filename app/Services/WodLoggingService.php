<?php

namespace App\Services;

use App\Models\Workout;
use Illuminate\Support\Facades\Auth;

class WodLoggingService
{
    protected $exerciseMatchingService;

    public function __construct(ExerciseMatchingService $exerciseMatchingService)
    {
        $this->exerciseMatchingService = $exerciseMatchingService;
    }

    /**
     * Flatten WOD exercises (skip special format headers, extract nested exercises)
     * Only includes loggable exercises
     */
    public function flattenWodExercises($exercise, &$flatExercises = [])
    {
        if ($exercise['type'] === 'special_format') {
            // Skip special format header, just process nested exercises
            if (isset($exercise['exercises'])) {
                foreach ($exercise['exercises'] as $nestedEx) {
                    $this->flattenWodExercises($nestedEx, $flatExercises);
                }
            }
        } else {
            // Regular exercise - only add if loggable
            if (($exercise['loggable'] ?? false) === true) {
                $flatExercises[] = $exercise;
            }
        }
        return $flatExercises;
    }

    /**
     * Add WOD exercises to a row builder (shared between index and edit)
     */
    public function addWodExercisesToRow($rowBuilder, Workout $workout, $today = null, $loggedExerciseData = [], &$hasLoggedExercisesToday = false)
    {
        if (!$workout->wod_parsed || !isset($workout->wod_parsed['blocks'])) {
            return;
        }

        $subItemId = 1000;
        foreach ($workout->wod_parsed['blocks'] as $block) {
            foreach ($block['exercises'] as $exercise) {
                $flatExercises = [];
                $this->flattenWodExercises($exercise, $flatExercises);
                foreach ($flatExercises as $flatExercise) {
                    $this->addWodExerciseSubItem($rowBuilder, $flatExercise, $subItemId, $today, $loggedExerciseData, $hasLoggedExercisesToday, $workout);
                    $subItemId++;
                }
            }
        }
    }

    /**
     * Add WOD exercise as sub-item in workout display
     */
    public function addWodExerciseSubItem($rowBuilder, $exercise, &$subItemId, $today, $loggedExerciseData, &$hasLoggedExercisesToday, Workout $workout)
    {
        $exerciseNameFromSyntax = $exercise['name'];
        $scheme = $exercise['scheme'] ?? '';
        
        // Try to find matching exercise in database to allow logging
        $matchingExercise = $this->exerciseMatchingService->findBestMatch($exerciseNameFromSyntax, Auth::id());
        
        // Use database exercise name if found, otherwise use syntax name
        $displayName = $matchingExercise ? $matchingExercise->title : $exerciseNameFromSyntax;
        
        $subItem = $rowBuilder->subItem(
            $subItemId,
            $displayName,
            $scheme,
            null
        );
        
        if ($matchingExercise) {
            // Check if logged today
            if (isset($loggedExerciseData[$matchingExercise->id])) {
                $hasLoggedExercisesToday = true;
                $liftLog = $loggedExerciseData[$matchingExercise->id];
                $strategy = $matchingExercise->getTypeStrategy();
                $formattedMessage = $strategy->formatLoggedItemDisplay($liftLog);
                
                $subItem->message('success', $formattedMessage, 'Completed:');
                $subItem->linkAction('fa-pencil', route('lift-logs.edit', ['lift_log' => $liftLog->id]), 'Edit lift log', 'btn-transparent');
                $subItem->formAction('fa-trash', route('lift-logs.destroy', ['lift_log' => $liftLog->id]), 'DELETE', ['redirect_to' => 'workouts', 'workout_id' => $workout->id], 'Delete lift log', 'btn-danger', true);
            } else {
                // Not logged yet
                $logUrl = route('lift-logs.create', [
                    'exercise_id' => $matchingExercise->id,
                    'date' => $today->toDateString(),
                    'redirect_to' => 'workouts',
                    'workout_id' => $workout->id
                ]);
                $subItem->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
            }
        }
        
        $subItem->compact()->add();
    }
}
