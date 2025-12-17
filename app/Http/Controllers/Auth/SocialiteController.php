<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->redirectUrl(url('/auth/google/callback'))
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(url('/auth/google/callback'))
                ->user();
            $isNewUser = false;
            
            // First check if user exists with this google_id
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // Check if user exists with this email, including soft-deleted ones
                $user = User::withTrashed()->where('email', $googleUser->getEmail())->first();

                if ($user) {
                    // If user was soft-deleted, restore them
                    if ($user->trashed()) {
                        $user->restore();
                    }

                    // User exists with this email, update with google_id
                    $user->update([
                        'google_id' => $googleUser->getId(),
                    ]);
                } else {
                    // Create new user with basic info
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'password' => bcrypt(str()->random(16)), // Random password for socialite users
                    ]);

                    // Use UserSeederService to set up all default data consistently
                    app(\App\Services\UserSeederService::class)->seedNewUser($user);

                    $isNewUser = true;
                }
            }

            Auth::login($user, true); // true enables "remember me"

            // Show welcome message for new users
            if ($isNewUser) {
                return redirect('/mobile-entry/lifts')->with('success', 'Welcome to our app! Thanks for trying us out. We\'re excited to help you track your fitness journey!');
            }

            return redirect('/mobile-entry/lifts');
        } catch (\Exception $e) {
            // Log the actual exception for debugging
            \Log::error('Google OAuth failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/login')->with('error', 'Google authentication failed.');
        }
    }
}
