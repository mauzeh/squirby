<?php

namespace App\Services;

use App\Models\Workout;
use Illuminate\Support\Facades\Auth;

/**
 * WOD Display Service
 * 
 * Processes WOD syntax for display by:
 * - Converting exercise brackets to clickable links
 * - Preserving markdown formatting
 */
class WodDisplayService
{
    protected $exerciseMatchingService;

    public function __construct(ExerciseMatchingService $exerciseMatchingService)
    {
        $this->exerciseMatchingService = $exerciseMatchingService;
    }

    /**
     * Process WOD syntax for display
     * Converts exercise brackets to clickable markdown links
     * 
     * @param Workout $workout
     * @return string Processed markdown text (not HTML)
     */
    public function processForDisplay(Workout $workout): string
    {
        if (!$workout->wod_syntax) {
            return '';
        }

        $text = $workout->wod_syntax;
        
        // First, process single-bracketed exercises (non-loggable) - just remove brackets
        // This must be done BEFORE processing double brackets to avoid conflicts
        $text = preg_replace('/(?<!\[)\[([^\]]+)\](?!\])/', '$1', $text);
        
        // Then, process double-bracketed exercises (loggable)
        $text = preg_replace_callback('/\[\[([^\]]+)\]\]/', function($matches) {
            $exerciseName = $matches[1];
            $matchingExercise = $this->exerciseMatchingService->findBestMatch($exerciseName, Auth::id());
            
            if ($matchingExercise) {
                $url = route('lift-logs.create', [
                    'exercise_id' => $matchingExercise->id,
                    'date' => now()->toDateString(),
                    'redirect_to' => 'workouts'
                ]);
                return '**[' . $exerciseName . '](' . $url . ')**';
            }
            
            return '**' . $exerciseName . '**';
        }, $text);
        
        return $text;
    }
}
