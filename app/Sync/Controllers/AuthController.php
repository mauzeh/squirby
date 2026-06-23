<?php

namespace App\Sync\Controllers;

use App\Models\User;
use App\Sync\Services\AppleJwtVerifier;
use App\Sync\Services\GoogleJwtVerifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use UnexpectedValueException;

class AuthController
{
    /**
     * Register a new sync user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string',
            'device_id' => 'required|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return $this->authResponse($user, $validated['device_id']);
    }

    /**
     * Authenticate an existing user and generate a token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // In local environment, skip password verification (dev mode bypass)
        $passwordValid = app()->environment('local')
            ? true
            : ($user && Hash::check($validated['password'], $user->password));

        if (! $user || ! $passwordValid) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->authResponse($user, $validated['device_id']);
    }

    /**
     * Authenticate via Google social sign-in.
     */
    public function googleAuth(Request $request, GoogleJwtVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
            'device_id' => 'required|string',
        ]);

        try {
            $payload = $verifier->verify($validated['id_token']);
        } catch (UnexpectedValueException $e) {
            throw new AuthenticationException('Invalid Google token.');
        }

        $user = $this->findOrCreateSocialUser(
            email: $payload['email'],
            name: $payload['name'] ?? $payload['email'],
            googleId: $payload['sub']
        );

        return $this->authResponse($user, $validated['device_id']);
    }

    /**
     * Authenticate via Apple social sign-in.
     */
    public function appleAuth(Request $request, AppleJwtVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'identity_token' => 'required|string',
            'device_id' => 'required|string',
        ]);

        try {
            $payload = $verifier->verify($validated['identity_token']);
        } catch (UnexpectedValueException $e) {
            throw new AuthenticationException('Invalid Apple token.');
        }

        if (empty($payload['email'])) {
            throw new AuthenticationException('Invalid Apple token: email missing.');
        }

        $user = $this->findOrCreateSocialUser(
            email: $payload['email'],
            name: $payload['name'] ?? explode('@', $payload['email'])[0],
            googleId: null
        );

        return $this->authResponse($user, $validated['device_id']);
    }

    /**
     * Check if an email has an existing account and what auth methods are set.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $hasRealPassword = ! is_null($user->password) && $user->password !== '' && ! Hash::check('social-auth-placeholder-value', $user->password);

            return response()->json([
                'status' => 'ok',
                'exists' => true,
                'has_password' => $hasRealPassword,
                'has_google' => ! is_null($user->google_id),
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'exists' => false,
        ]);
    }

    /**
     * Send a password reset link to the given email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Always return success to prevent email enumeration
        return response()->json([
            'status' => 'ok',
            'message' => 'If an account exists with that email, a reset link has been sent.',
        ]);
    }

    /**
     * Reset the user's password using a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
        ], 422);
    }

    /**
     * Find or create a social auth user.
     */
    private function findOrCreateSocialUser(string $email, string $name, ?string $googleId): User
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            if ($googleId && ! $user->google_id) {
                $user->update(['google_id' => $googleId]);
            }

            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'google_id' => $googleId,
            'password' => 'social-auth-placeholder-value',
        ]);
    }

    /**
     * Generate Sanctum token and return unified auth response.
     */
    private function authResponse(User $user, string $deviceId): JsonResponse
    {
        $token = $user->createToken($deviceId)->plainTextToken;

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'athlete' => $user->name,
            'email' => $user->email,
        ]);
    }
}
