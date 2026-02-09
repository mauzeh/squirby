<?php

namespace App\Actions\Exercises;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use Illuminate\Http\Request;

class UpdateExerciseAction
{
    public function execute(Request $request, Exercise $exercise, User $user): Exercise
    {
        // Validate the request
        $validated = $this->validateRequest($request, $user);
        
        // Check for name conflicts (excluding current exercise)
        $this->validateExerciseNameForUpdate($exercise, $validated['title'], $user);

        $exerciseType = $validated['exercise_type'];

        // Create a temporary exercise with new values to determine the strategy
        $tempExercise = new Exercise([
            'exercise_type' => $exerciseType,
        ]);

        // Use exercise type strategy to process exercise data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($tempExercise);
        $processedData = $exerciseTypeStrategy->processExerciseData($validated);

        $exercise->update([
            'title' => $processedData['title'],
            'description' => $processedData['description'],
            'exercise_type' => $exerciseType,
            'show_in_feed' => $validated['show_in_feed'] ?? false,
        ]);

        return $exercise->fresh();
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
    
    private function validateExerciseNameForUpdate(Exercise $exercise, string $title, User $user): void
    {
        // For updates, we need to check based on the current exercise's global status
        // since we're no longer changing it via the form
        if ($exercise->isGlobal()) {
            // Check if another global exercise with same name exists
            if (Exercise::global()->where('title', $title)->where('id', '!=', $exercise->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has another exercise with same name OR global exercise exists
            $userId = $exercise->user_id; // Use the exercise's current user_id, not the editing user
            $conflicts = Exercise::where('title', $title)
                ->where('id', '!=', $exercise->id)
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