<?php

namespace App\Sync\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\AuthenticationException;

class AuthController
{
    /**
     * Register a new sync user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,name',
            'password' => 'required|string|min:6',
            'device_id' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['username'],
            'email' => $validated['username'] . '@sync.local',
            'password' => Hash::make($validated['password']),
        ]);

        $deviceId = $validated['device_id'] ?? $request->header('X-Device-Id') ?? 'default';
        $token = $user->createToken($deviceId)->plainTextToken;

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'athlete' => $user->name,
        ]);
    }

    /**
     * Authenticate an existing user and generate a token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        $user = User::where('name', $validated['username'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $deviceId = $validated['device_id'] ?? $request->header('X-Device-Id') ?? 'default';
        $token = $user->createToken($deviceId)->plainTextToken;

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'athlete' => $user->name,
        ]);
    }
}
