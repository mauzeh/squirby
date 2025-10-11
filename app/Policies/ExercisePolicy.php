<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    /**
     * Determine if the user can create global exercises.
     */
    public function createGlobalExercise(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine if the user can update the exercise.
     */
    public function update(User $user, Exercise $exercise): bool
    {
        return $exercise->canBeEditedBy($user);
    }

    /**
     * Determine if the user can delete the exercise.
     */
    public function delete(User $user, Exercise $exercise): bool
    {
        return $exercise->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can promote the exercise to global.
     */
    public function promoteToGlobal(User $user, Exercise $exercise): bool
    {
        // Only admins can promote exercises
        if (!$user->hasRole('Admin')) {
            return false;
        }
        
        // Can only promote user-specific exercises (not already global)
        return !$exercise->isGlobal();
    }
}