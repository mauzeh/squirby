<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PRComment;
use App\Models\PRHighFive;
use Illuminate\Console\Command;

class CleanOrphanedNotifications extends Command
{
    protected $signature = 'notifications:clean-orphaned';

    protected $description = 'Remove notifications that reference deleted comments or high fives';

    public function handle(): int
    {
        $this->info('Scanning for orphaned notifications...');

        $orphanedCount = 0;

        // Clean orphaned PR comment notifications
        $commentNotifications = Notification::where('notifiable_type', PRComment::class)->get();
        
        foreach ($commentNotifications as $notification) {
            if (!PRComment::find($notification->notifiable_id)) {
                $notification->delete();
                $orphanedCount++;
            }
        }

        // Clean orphaned PR high five notifications
        $highFiveNotifications = Notification::where('notifiable_type', PRHighFive::class)->get();
        
        foreach ($highFiveNotifications as $notification) {
            if (!PRHighFive::find($notification->notifiable_id)) {
                $notification->delete();
                $orphanedCount++;
            }
        }

        if ($orphanedCount > 0) {
            $this->info("Cleaned up {$orphanedCount} orphaned notification(s).");
        } else {
            $this->info('No orphaned notifications found.');
        }

        return Command::SUCCESS;
    }
}
