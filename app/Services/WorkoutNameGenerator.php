<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use Carbon\Carbon;

class WorkoutNameGenerator
{
    /**
     * Generate an intelligent workout name based on the first exercise
     */
    public function generate(Exercise $exercise): string
    {
        // Try to get exercise intelligence data
        $intelligence = ExerciseIntelligence::where('exercise_id', $exercise->id)->first();
        
        if (!$intelligence) {
            // Fallback to date-based name if no intelligence data
            return 'New Workout - ' . Carbon::now()->format('M j, Y');
        }

        // Combine movement archetype and category for descriptive names
        // e.g., "Hinge Day (Strength)", "Push Day (Cardio)"
        if ($intelligence->movement_archetype && $intelligence->category) {
            return ucfirst($intelligence->movement_archetype) . ' Day (' . ucfirst($intelligence->category) . ')';
        }
        
        // Fallback to just archetype or category if only one is available
        if ($intelligence->movement_archetype) {
            return ucfirst($intelligence->movement_archetype) . ' Day';
        }
        
        if ($intelligence->category) {
            return ucfirst($intelligence->category) . ' Workout';
        }

        // Fallback to date-based name
        return 'New Workout - ' . Carbon::now()->format('M j, Y');
    }
}
