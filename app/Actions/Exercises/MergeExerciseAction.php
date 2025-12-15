<?php

namespace App\Actions\Exercises;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseMergeService;
use Illuminate\Http\Request;

class MergeExerciseAction
{
    public function __construct(
        private ExerciseMergeService $exerciseMergeService
    ) {}

    public function execute(Request $request, Exercise $exercise, User $user): array
    {
        // Validate the request
        $validated = $this->validateRequest($request);
        
        $targetExercise = Exercise::findOrFail($validated['target_exercise_id']);

        // Validate compatibility
        $compatibility = $this->exerciseMergeService->validateMergeCompatibility($exercise, $targetExercise);
        
        if (!$compatibility['can_merge']) {
            throw new \InvalidArgumentException('Merge failed: ' . implode(', ', $compatibility['errors']));
        }

        // Get create_alias parameter, default to true if not provided
        $createAlias = $request->boolean('create_alias', true);

        // Perform the merge
        $this->exerciseMergeService->mergeExercises($exercise, $targetExercise, $user, $createAlias);
        
        // Build success message
        $successMessage = "Exercise '{$exercise->title}' successfully merged into '{$targetExercise->title}'. All workout data has been preserved.";
        
        // Add alias creation note if applicable
        if ($createAlias && $exercise->user) {
            $successMessage .= " An alias has been created so the owner will continue to see '{$exercise->title}'.";
        }
        
        return [
            'sourceExercise' => $exercise,
            'targetExercise' => $targetExercise,
            'successMessage' => $successMessage,
            'aliasCreated' => $createAlias && $exercise->user
        ];
    }
    
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'target_exercise_id' => 'required|exists:exercises,id',
            'create_alias' => 'nullable|boolean',
        ]);
    }
}