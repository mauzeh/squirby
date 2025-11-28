<?php

namespace App\Listeners;

use App\Events\LiftLogged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
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
            Mail::to('bluedackers@gmail.com')->send(new FirstLiftOfTheDay($liftLog));
        }
    }
}
