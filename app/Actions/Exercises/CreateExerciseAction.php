<?php

namespace App\Actions\Exercises;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateExerciseAction
{
    public function execute(Request $request, User $user): Exercise
    {
        // Validate the request
        $validated = $this->validateRequest($request, $user);
        
        // Check for name conflicts
        $this->validateExerciseName($validated['title'], $validated['is_global'] ?? false, $user);

        $exerciseType = $validated['exercise_type'];

        // Create a temporary exercise to determine the strategy
        $tempExercise = new Exercise([
            'exercise_type' => $exerciseType,
        ]);

        // Use exercise type strategy to process exercise data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($tempExercise);
        $processedData = $exerciseTypeStrategy->processExerciseData($validated);

        $exercise = new Exercise([
            'title' => $processedData['title'],
            'description' => $processedData['description'],
            'exercise_type' => $exerciseType,
        ]);
        
        if ($validated['is_global'] ?? false) {
            $exercise->user_id = null;
        } else {
            $exercise->user_id = $user->id;
        }

        $exercise->save();
        
        return $exercise;
    }
    
    private function validateRequest(Request $request, User $user): array
    {
        $availableTypes = ExerciseTypeFactory::getAvailableTypes();
        
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercise_type' => 'required|in:' . implode(',', $availableTypes),
            'is_global' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            if (!$user->hasRole('Admin')) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Only admins can create global exercises.');
            }
        }

        return $validated;
    }
    
    private function validateExerciseName(string $title, bool $isGlobal, User $user): void
    {
        if ($isGlobal) {
            // Check if global exercise with same name exists
            if (Exercise::global()->where('title', $title)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has exercise with same name OR global exercise exists
            $userId = $user->id;
            $conflicts = Exercise::where('title', $title)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }
}