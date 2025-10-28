<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExerciseMergeService
{
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
            ->where('is_bodyweight', $sourceExercise->is_bodyweight)
            ->where(function ($query) use ($sourceExercise) {
                // Compatible band types: both null, or one null and one has value
                $query->where(function ($q) use ($sourceExercise) {
                    // Both have same band_type (including both null)
                    $q->where('band_type', $sourceExercise->band_type);
                })->orWhere(function ($q) use ($sourceExercise) {
                    // Source has null, target has any value
                    if ($sourceExercise->band_type === null) {
                        $q->whereNotNull('band_type');
                    }
                })->orWhere(function ($q) use ($sourceExercise) {
                    // Target has null, source has any value
                    if ($sourceExercise->band_type !== null) {
                        $q->whereNull('band_type');
                    }
                });
            })
            ->orderBy('title')
            ->get();
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

        // Must have same bodyweight setting
        if ($source->is_bodyweight !== $target->is_bodyweight) {
            $errors[] = 'Exercises must have the same bodyweight setting.';
        }

        // Band type compatibility check
        if (!$this->areBandTypesCompatible($source->band_type, $target->band_type)) {
            $errors[] = 'Exercises have incompatible band types.';
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
     * Check if band types are compatible for merging
     */
    private function areBandTypesCompatible(?string $sourceBandType, ?string $targetBandType): bool
    {
        // Same band types are always compatible (including both null)
        if ($sourceBandType === $targetBandType) {
            return true;
        }

        // One null and one with value is compatible
        if ($sourceBandType === null || $targetBandType === null) {
            return true;
        }

        // Different non-null values are not compatible
        return false;
    }

    /**
     * Perform the exercise merge operation
     */
    public function mergeExercises(Exercise $source, Exercise $target, User $admin): bool
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

            // Delete the source exercise
            $source->delete();

            // Log the merge operation
            Log::info('Exercise merge completed', [
                'source_exercise_id' => $source->id,
                'source_exercise_title' => $source->title,
                'target_exercise_id' => $target->id,
                'target_exercise_title' => $target->title,
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email
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