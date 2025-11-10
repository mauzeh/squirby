<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutTemplate;

class WorkoutTemplatePolicy
{
    /**
     * Determine if the user can create templates
     */
    public function create(User $user): bool
    {
        return true;
    }
    
    /**
     * Determine if the user can update the template
     */
    public function update(User $user, WorkoutTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
    
    /**
     * Determine if the user can delete the template
     */
    public function delete(User $user, WorkoutTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
