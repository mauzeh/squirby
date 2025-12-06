<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Workout;

trait DetectsSimpleWorkouts
{
    /**
     * Determine if a workout is a simple workout (no WOD syntax)
     * 
     * Simple workouts have null or empty wod_syntax and use workout_exercises table
     * Advanced workouts have populated wod_syntax
     */
    protected function isSimpleWorkout(Workout $workout): bool
    {
        return empty($workout->wod_syntax);
    }

    /**
     * Determine if the current user can access advanced workout features
     * Only Admins can create/edit advanced workouts with WOD syntax
     */
    protected function canAccessAdvancedWorkouts(): bool
    {
        return \Illuminate\Support\Facades\Auth::user()->hasRole('Admin');
    }
}
