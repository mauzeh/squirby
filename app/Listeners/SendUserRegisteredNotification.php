<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\NewUserRegistered;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendUserRegisteredNotification implements ShouldQueue
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
    public function handle(UserRegistered $event): void
    {
        try {
            // Get all admin users
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'Admin');
            })->get();

            // If there are admin users, send the notification
            if ($adminUsers->isNotEmpty()) {
                $adminEmails = $adminUsers->pluck('email')->toArray();
                
                // Send email to first admin as primary recipient, others as CC
                $primaryAdmin = $adminEmails[0];
                $ccAdmins = array_slice($adminEmails, 1);

                if (count($ccAdmins) > 0) {
                    Mail::to($primaryAdmin)
                        ->cc($ccAdmins)
                        ->send(new NewUserRegistered($event->user));
                } else {
                    Mail::to($primaryAdmin)
                        ->send(new NewUserRegistered($event->user));
                }
            }
        } catch (\Exception $e) {
            // Log the failure but don't break the event handling
            \Log::error('Failed to send new user registration notification', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        // Log the failure or handle it as needed
        \Log::error('Failed to send new user registration notification', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}