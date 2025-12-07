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
    protected $exerciseAliasService;

    public function __construct(
        ExerciseMatchingService $exerciseMatchingService,
        ExerciseAliasService $exerciseAliasService
    ) {
        $this->exerciseMatchingService = $exerciseMatchingService;
        $this->exerciseAliasService = $exerciseAliasService;
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
        $text = preg_replace_callback('/\[\[([^\]]+)\]\]/', function($matches) use ($workout) {
            $exerciseName = $matches[1];
            $matchingExercise = $this->exerciseMatchingService->findBestMatch($exerciseName, Auth::id());
            
            if ($matchingExercise) {
                // Use the original text from WOD syntax, not the exercise's actual name
                // This preserves what the user typed (e.g., "HelemaalNiks" instead of "Back Rack Lunge")
                $displayName = $exerciseName;
                
                // Matched - link to exercise logs page
                $url = route('exercises.show-logs', ['exercise' => $matchingExercise->id]);
                return '**[' . $displayName . '](' . $url . ')**';
            }
            
            // Unmatched - link to alias creation page
            $url = route('exercise-aliases.create', [
                'alias_name' => $exerciseName,
                'workout_id' => $workout->id
            ]);
            return '**[' . $exerciseName . '](' . $url . ')**';
        }, $text);
        
        return $text;
    }
}
