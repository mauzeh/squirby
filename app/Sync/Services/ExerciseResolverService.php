<?php

namespace App\Sync\Services;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\User;
use Illuminate\Support\Str;

class ExerciseResolverService
{
    /**
     * Resolve an exercise name to an Exercise model.
     */
    public function resolve(string $exerciseName, User $user, ?string $logType = null): Exercise
    {
        $canonical = Str::snake($exerciseName);

        // 1. Exact match on exercises.canonical_name (scoped to global and user-owned)
        $exercise = Exercise::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->where('canonical_name', $canonical)
            ->first();

        if ($exercise) {
            return $exercise;
        }

        // 2. Case-insensitive match on exercises.title (scoped to global and user-owned)
        $exercise = Exercise::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->whereRaw('LOWER(title) = ?', [strtolower($exerciseName)])
            ->first();

        if ($exercise) {
            return $exercise;
        }

        // 3. Case-insensitive match on exercise_aliases.alias_name (scoped to global and user-owned)
        $alias = ExerciseAlias::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->whereRaw('LOWER(alias_name) = ?', [strtolower($exerciseName)])
            ->first();

        if ($alias) {
            $exercise = $alias->exercise;
            if ($exercise && ($exercise->user_id === null || $exercise->user_id === $user->id)) {
                return $exercise;
            }
        }

        // 4. Auto-create new exercise
        $derivedType = $this->deriveExerciseType($logType);

        return Exercise::create([
            'title' => $exerciseName,
            'canonical_name' => $canonical,
            'exercise_type' => $derivedType,
            'user_id' => null, // Auto-created exercises are global (NULL user_id)
            'show_in_feed' => true,
        ]);
    }

    /**
     * Derive exercise type from log type.
     */
    private function deriveExerciseType(?string $logType): string
    {
        if (!$logType) {
            return 'regular';
        }

        return match ($logType) {
            'barbell', 'single-dumbbell', 'dual-dumbbell', 'kettlebell', 'dual-kettlebell', 'ball', 'weighted-carry' => 'regular',
            'bodyweight', 'bodyweight-reps', 'added-weight' => 'bodyweight',
            'banded' => 'banded_resistance',
            'static-hold' => 'static_hold',
            'cardio', 'cardio-calories', 'cardio-distance' => 'cardio',
            default => 'regular',
        };
    }
}
