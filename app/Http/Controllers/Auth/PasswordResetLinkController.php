<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Check if we're being rate limited
        $key = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);
            
            $message = $minutes > 1 
                ? "Too many password reset attempts. Please wait {$minutes} minutes before retrying."
                : "Too many password reset attempts. Please wait {$seconds} seconds before retrying.";
                
            return back()->withInput($request->only('email'))
                        ->withErrors(['email' => $message]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            // Hit the rate limiter
            RateLimiter::hit($key, 300); // 5 minutes decay
            
            $message = __($status);
            
            // In local environment, include the reset link for easy testing
            if (app()->environment('local')) {
                $user = User::where('email', $request->email)->first();
                if ($user) {
                    // Get the most recent password reset token for this user
                    $tokenRecord = DB::table('password_reset_tokens')
                        ->where('email', $request->email)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($tokenRecord) {
                        $resetUrl = url(route('password.reset', ['token' => $tokenRecord->token], false)) . '?email=' . urlencode($request->email);
                        $message .= '<br><br><strong>Local Development:</strong><br><a href="' . $resetUrl . '" target="_blank" style="color: #007bff; word-break: break-all;">' . $resetUrl . '</a>';
                    }
                }
            }
            
            return back()->with('status', $message);
        }

        // Also hit rate limiter for failed attempts to prevent enumeration
        RateLimiter::hit($key, 300);

        return back()->withInput($request->only('email'))
                    ->withErrors(['email' => __($status)]);
    }
}
