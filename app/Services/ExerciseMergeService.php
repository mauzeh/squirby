<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class ExerciseMergeService
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Determine if an exercise can be merged by an admin
     */
    public function canBeMerged(Exercise $sourceExercise): bool
    {
        // Only user exercises can be merged (not global exercises)
        if ($sourceExercise->isGlobal()) {
            return false;
        }

        // Must have at least one potential target
        return $this->getPotentialTargets($sourceExercise)->isNotEmpty();
    }

    /**
     * Get potential global target exercises for merging
     */
    public function getPotentialTargets(Exercise $sourceExercise): Collection
    {
        return Exercise::onlyGlobal()
            ->where('id', '!=', $sourceExercise->id)
            ->get()
            ->filter(function ($target) use ($sourceExercise) {
                return $sourceExercise->isCompatibleForMerge($target);
            })
            ->sortBy('title')
            ->values();
    }

    /**
     * Validate merge compatibility between source and target exercises
     */
    public function validateMergeCompatibility(Exercise $source, Exercise $target): array
    {
        $errors = [];
        $warnings = [];

        // Target must be global
        if (!$target->isGlobal()) {
            $errors[] = 'Target exercise must be a global exercise.';
        }

        // Cannot merge into self
        if ($source->id === $target->id) {
            $errors[] = 'Cannot merge exercise into itself.';
        }

        // Check exercise type compatibility using the model's method
        if (!$source->isCompatibleForMerge($target)) {
            $errors[] = 'Exercises have incompatible types.';
        }

        // Check if source exercise owner has global visibility disabled
        if ($source->user && !$source->user->shouldShowGlobalExercises()) {
            $warnings[] = 'The owner of this exercise has global exercise visibility disabled. They will lose access to their exercise data after the merge.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'can_merge' => empty($errors)
        ];
    }



    /**
     * Perform the exercise merge operation
     */
    public function mergeExercises(Exercise $source, Exercise $target, User $admin, bool $createAlias = true): bool
    {
        // Validate compatibility first
        $validation = $this->validateMergeCompatibility($source, $target);
        if (!$validation['can_merge']) {
            throw new \InvalidArgumentException('Exercises are not compatible for merging: ' . implode(', ', $validation['errors']));
        }

        try {
            DB::beginTransaction();

            // Transfer lift logs
            $this->transferLiftLogs($source, $target);

            // Transfer program entries
            $this->transferProgramEntries($source, $target);

            // Handle exercise intelligence
            $this->handleExerciseIntelligence($source, $target);

            // Create alias for the source exercise owner if requested
            $aliasCreated = $this->createAliasForOwner($source, $target, $createAlias);

            // Delete the source exercise
            $source->delete();

            // Log the merge operation
            Log::info('Exercise merge completed', [
                'source_exercise_id' => $source->id,
                'source_exercise_title' => $source->title,
                'target_exercise_id' => $target->id,
                'target_exercise_title' => $target->title,
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'alias_created' => $aliasCreated,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Exercise merge failed', [
                'source_exercise_id' => $source->id,
                'target_exercise_id' => $target->id,
                'admin_user_id' => $admin->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Transfer all lift logs from source to target exercise
     */
    private function transferLiftLogs(Exercise $source, Exercise $target): void
    {
        $liftLogs = LiftLog::where('exercise_id', $source->id)->get();

        foreach ($liftLogs as $liftLog) {
            // Append merge note to comments
            $this->appendMergeNote($liftLog, $source->title);
            
            // Update exercise_id to target
            $liftLog->update(['exercise_id' => $target->id]);
        }
    }

    /**
     * Transfer all program entries from source to target exercise
     */
    private function transferProgramEntries(Exercise $source, Exercise $target): void
    {
        Program::where('exercise_id', $source->id)
            ->update(['exercise_id' => $target->id]);
    }

    /**
     * Handle exercise intelligence transfer
     */
    private function handleExerciseIntelligence(Exercise $source, Exercise $target): void
    {
        $sourceIntelligence = $source->intelligence;
        $targetIntelligence = $target->intelligence;

        // If source has intelligence and target doesn't, transfer it
        if ($sourceIntelligence && !$targetIntelligence) {
            $sourceIntelligence->update(['exercise_id' => $target->id]);
        }
        // If both have intelligence, keep target's intelligence (source will be deleted with exercise)
        // No action needed as source intelligence will be cascade deleted
    }

    /**
     * Append merge note to lift log comments
     */
    public function appendMergeNote(LiftLog $liftLog, string $originalExerciseName): void
    {
        $mergeNote = "[Merged from: {$originalExerciseName}]";
        
        if (empty($liftLog->comments)) {
            $liftLog->comments = $mergeNote;
        } else {
            $liftLog->comments = $liftLog->comments . ' ' . $mergeNote;
        }
        
        $liftLog->save();
    }

    /**
     * Create alias for the source exercise owner if requested
     * 
     * @param Exercise $source
     * @param Exercise $target
     * @param bool $createAlias
     * @return bool Whether an alias was created
     */
    private function createAliasForOwner(Exercise $source, Exercise $target, bool $createAlias): bool
    {
        // Don't create alias if not requested
        if (!$createAlias) {
            return false;
        }

        // Don't create alias if source has no owner (global exercise)
        if (!$source->user) {
            return false;
        }

        try {
            $this->aliasService->createAlias(
                $source->user,
                $target,
                $source->title
            );

            Log::info('Exercise alias created during merge', [
                'user_id' => $source->user->id,
                'user_email' => $source->user->email,
                'exercise_id' => $target->id,
                'exercise_title' => $target->title,
                'alias_name' => $source->title,
                'created_via' => 'merge_operation',
            ]);

            return true;
        } catch (QueryException $e) {
            // Handle duplicate alias errors gracefully
            if ($e->getCode() === '23000') {
                Log::warning('Duplicate alias during merge - alias already exists', [
                    'user_id' => $source->user->id,
                    'exercise_id' => $target->id,
                    'alias_name' => $source->title,
                ]);
                // Continue with merge - alias already exists
                return false;
            }
            
            // For other database errors, log and continue
            Log::error('Failed to create alias during merge', [
                'user_id' => $source->user->id,
                'exercise_id' => $target->id,
                'alias_name' => $source->title,
                'error' => $e->getMessage(),
            ]);
            
            // Don't fail the merge due to alias creation failure
            return false;
        } catch (\Exception $e) {
            // Catch any other exceptions and log them
            Log::error('Unexpected error creating alias during merge', [
                'user_id' => $source->user->id,
                'exercise_id' => $target->id,
                'alias_name' => $source->title,
                'error' => $e->getMessage(),
            ]);
            
            // Don't fail the merge due to alias creation failure
            return false;
        }
    }

    /**
     * Get merge statistics for display purposes
     */
    public function getMergeStatistics(Exercise $exercise): array
    {
        return [
            'lift_logs_count' => $exercise->liftLogs()->count(),
            'program_entries_count' => Program::where('exercise_id', $exercise->id)->count(),
            'has_intelligence' => $exercise->hasIntelligence(),
            'users_count' => $exercise->liftLogs()->distinct('user_id')->count('user_id')
        ];
    }
}