<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserSeederService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, UserSeederService $userSeederService): RedirectResponse
    {
        // Custom validation to handle soft-deleted users
        $this->validateRegistration($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Seed the new user with default data
        $userSeederService->seedNewUser($user);

        // Dispatch user registered event
        UserRegistered::dispatch($user);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('mobile-entry.lifts', absolute: false));
    }

    /**
     * Validate registration request with custom email uniqueness check.
     */
    private function validateRegistration(Request $request): void
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Check if user exists with this email, including soft-deleted ones
        $existingUser = User::withTrashed()->where('email', $request->email)->first();

        if ($existingUser) {
            if ($existingUser->trashed()) {
                // Soft-deleted user - provide specific error message
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'This email address was previously registered but the account has been deactivated. Please contact support to reactivate your account or use a different email address.'
                ]);
            } else {
                // Active user - standard uniqueness error
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'The email has already been taken.'
                ]);
            }
        }
    }
}
