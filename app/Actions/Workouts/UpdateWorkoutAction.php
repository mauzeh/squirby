<?php

namespace App\Actions\Workouts;

use App\Models\User;
use App\Models\Workout;
use App\Services\WodParser;
use Illuminate\Http\Request;

class UpdateWorkoutAction
{
    public function __construct(
        private WodParser $wodParser
    ) {}

    public function execute(Request $request, Workout $workout, User $user): Workout
    {
        // Validate the request
        $validated = $this->validateRequest($request, $user);
        
        // Parse the workout syntax
        try {
            $parsed = $this->wodParser->parse($validated['wod_syntax']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Failed to parse workout syntax: ' . $e->getMessage());
        }
        
        $workout->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'wod_syntax' => $validated['wod_syntax'],
        ]);
        
        return $workout->fresh();
    }
    
    private function validateRequest(Request $request, User $user): array
    {
        // Only admins and impersonators can update advanced workouts
        if (!$this->canAccessAdvancedWorkouts($user)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Advanced workout editing is only available to admins.');
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'wod_syntax' => 'required|string',
        ]);
    }
    
    private function canAccessAdvancedWorkouts(User $user): bool
    {
        return $user->hasRole('Admin') || session()->has('impersonate');
    }
}