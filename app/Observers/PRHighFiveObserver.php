<?php

namespace App\Observers;

use App\Models\PRHighFive;
use App\Models\Notification;

class PRHighFiveObserver
{
    public function created(PRHighFive $highFive): void
    {
        // Get the PR owner
        $prOwner = $highFive->personalRecord->user;
        
        // Don't notify if high-fiving own PR
        if ($highFive->user_id === $prOwner->id) {
            return;
        }
        
        // Create notification for PR owner
        Notification::create([
            'user_id' => $prOwner->id,
            'type' => 'pr_high_five',
            'actor_id' => $highFive->user_id,
            'notifiable_type' => PRHighFive::class,
            'notifiable_id' => $highFive->id,
            'data' => [
                'personal_record_id' => $highFive->personal_record_id,
            ],
        ]);
    }

    public function deleted(PRHighFive $highFive): void
    {
        // Remove the notification when high five is removed
        Notification::where('notifiable_type', PRHighFive::class)
            ->where('notifiable_id', $highFive->id)
            ->delete();
    }
}
