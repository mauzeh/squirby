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
                // Check if user exists with this email
                $user = User::where('email', $googleUser->getEmail())->first();
                
                if ($user) {
                    // User exists with this email, update with google_id
                    $user->update([
                        'google_id' => $googleUser->getId(),
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'password' => bcrypt(str()->random(16)), // Random password for socialite users
                    ]);
                    
                    // Assign athlete role to new users
                    $athleteRole = \App\Models\Role::where('name', 'Athlete')->first();
                    if ($athleteRole) {
                        $user->roles()->attach($athleteRole);
                    }
                    
                    // Create default measurement types for new users
                    \App\Models\MeasurementType::create([
                        'name' => 'Bodyweight',
                        'default_unit' => 'lbs',
                        'user_id' => $user->id,
                    ]);
                    
                    \App\Models\MeasurementType::create([
                        'name' => 'Waist Size',
                        'default_unit' => 'in',
                        'user_id' => $user->id,
                    ]);
                    
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
