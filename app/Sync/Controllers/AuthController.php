<?php

namespace App\Sync\Controllers;

use App\Models\User;
use App\Sync\Services\AppleJwtVerifier;
use App\Sync\Services\GoogleJwtVerifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'password' => 'required|string|min:6',
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
