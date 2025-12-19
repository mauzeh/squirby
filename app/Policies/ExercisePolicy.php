<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    /**
     * Determine if the user can view the exercise index.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

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

    /**
     * Determine whether the user can unpromote the exercise to user exercise.
     */
    public function unpromoteToUser(User $user, Exercise $exercise): bool
    {
        // Only admins can unpromote exercises
        if (!$user->hasRole('Admin')) {
            return false;
        }
        
        // Can only unpromote global exercises
        return $exercise->isGlobal();
    }

    /**
     * Determine whether the user can merge the exercise.
     */
    public function merge(User $user, Exercise $exercise): bool
    {
        // Only admins can merge exercises
        return $user->hasRole('Admin');
    }
}