<?php

namespace App\Listeners;

use App\Events\LiftLogged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\FirstLiftOfTheDay;
use App\Models\LiftLog;
use App\Models\User;

class SendFirstLiftOfTheDayNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LiftLogged $event): void
    {
        $liftLog = $event->liftLog;
        $user = $liftLog->user;

        // Check if this is the first lift of the day for the user
        $firstLift = LiftLog::where('user_id', $user->id)
            ->whereDate('logged_at', $liftLog->logged_at->toDateString())
            ->orderBy('logged_at', 'asc')
            ->first();

        if ($firstLift && $firstLift->id === $liftLog->id) {
            try {
                $liftLog->load('exercise', 'user');
                $environmentFile = app()->environmentFile();
                
                Log::info('Attempting to send FirstLiftOfTheDay email.', [
                    'lift_log_id' => $liftLog->id,
                    'user_id' => $user->id,
                    'exercise_name' => $liftLog->exercise->getDisplayNameForUser($user),
                    'environment_file' => $environmentFile,
                ]);

                Mail::to('bluedackers@gmail.com')->send(new FirstLiftOfTheDay($liftLog, $environmentFile));
                
                Log::info('Successfully sent FirstLiftOfTheDay email.');
            } catch (\Exception $e) {
                Log::error('Failed to send FirstLiftOfTheDay email: ' . $e->getMessage());
            }
        }
    }
}
