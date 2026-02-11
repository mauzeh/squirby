<?php

namespace App\Observers;

use App\Models\PRComment;
use App\Models\Notification;

class PRCommentObserver
{
    public function created(PRComment $comment): void
    {
        // Get the PR owner
        $prOwner = $comment->personalRecord->user;
        
        // Don't notify if commenting on own PR
        if ($comment->user_id === $prOwner->id) {
            return;
        }
        
        // Create notification for PR owner
        Notification::create([
            'user_id' => $prOwner->id,
            'type' => 'pr_comment',
            'actor_id' => $comment->user_id,
            'notifiable_type' => PRComment::class,
            'notifiable_id' => $comment->id,
            'data' => [
                'personal_record_id' => $comment->personal_record_id,
                'comment_preview' => substr($comment->comment, 0, 100),
            ],
        ]);
    }

    public function deleted(PRComment $comment): void
    {
        // Remove the notification when comment is deleted
        Notification::where('notifiable_type', PRComment::class)
            ->where('notifiable_id', $comment->id)
            ->delete();
    }
}

