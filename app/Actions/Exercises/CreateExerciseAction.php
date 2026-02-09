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
        $this->validateExerciseName($validated['title'], $user);

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
            'user_id' => $user->id, // All new exercises are user exercises
            'show_in_feed' => $validated['show_in_feed'] ?? false,
        ]);

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
            'show_in_feed' => 'nullable|boolean',
        ];

        return $request->validate($rules);
    }
    
    private function validateExerciseName(string $title, User $user): void
    {
        // All new exercises are user exercises, so check for conflicts with user's exercises and global exercises
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