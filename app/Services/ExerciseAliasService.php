<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class ExerciseAliasService
{
    /**
     * Request-level cache for user aliases
     * Keyed by user_id, value is Collection keyed by exercise_id
     *
     * @var array<int, Collection>
     */
    protected array $userAliasesCache = [];

    /**
     * Create an alias for a user and exercise
     *
     * @param User $user
     * @param Exercise $exercise
     * @param string $aliasName
     * @return ExerciseAlias
     * @throws QueryException
     */
    public function createAlias(User $user, Exercise $exercise, string $aliasName): ExerciseAlias
    {
        try {
            $alias = ExerciseAlias::create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => $aliasName,
            ]);

            // Invalidate cache for this user
            unset($this->userAliasesCache[$user->id]);

            Log::info('Exercise alias created', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'exercise_id' => $exercise->id,
                'exercise_title' => $exercise->title,
                'alias_name' => $aliasName,
            ]);

            return $alias;
        } catch (QueryException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() === '23000') {
                Log::warning('Duplicate alias creation attempted', [
                    'user_id' => $user->id,
                    'exercise_id' => $exercise->id,
                    'alias_name' => $aliasName,
                ]);
                
                // Return existing alias instead of throwing
                return ExerciseAlias::forUser($user->id)
                    ->forExercise($exercise->id)
                    ->firstOrFail();
            }
            
            throw $e;
        }
    }

    /**
     * Get all aliases for a user
     * Returns collection keyed by exercise_id for efficient lookups
     * Uses request-level caching to prevent duplicate queries
     *
     * @param User $user
     * @return Collection
     */
    public function getUserAliases(User $user): Collection
    {
        if (!isset($this->userAliasesCache[$user->id])) {
            $this->userAliasesCache[$user->id] = ExerciseAlias::forUser($user->id)
                ->get()
                ->keyBy('exercise_id');
        }

        return $this->userAliasesCache[$user->id];
    }

    /**
     * Apply aliases to a collection of exercises for a user
     * Modifies the title attribute of exercises that have aliases
     *
     * @param Collection $exercises
     * @param User $user
     * @return Collection
     */
    public function applyAliasesToExercises(Collection $exercises, User $user): Collection
    {
        $aliases = $this->getUserAliases($user);

        return $exercises->map(function ($exercise) use ($aliases) {
            if ($aliases->has($exercise->id)) {
                $exercise->title = $aliases->get($exercise->id)->alias_name;
            }
            return $exercise;
        });
    }

    /**
     * Get display name for an exercise (alias if exists, otherwise title)
     * Includes error handling with graceful fallback to exercise title
     *
     * @param Exercise $exercise
     * @param User $user
     * @return string
     */
    public function getDisplayName(Exercise $exercise, User $user): string
    {
        try {
            $aliases = $this->getUserAliases($user);
            
            if ($aliases->has($exercise->id)) {
                return $aliases->get($exercise->id)->alias_name;
            }
            
            return $exercise->title;
        } catch (\Exception $e) {
            Log::error('Alias lookup failed', [
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to exercise title
            return $exercise->title;
        }
    }

    /**
     * Check if an alias exists for a user and exercise
     *
     * @param User $user
     * @param Exercise $exercise
     * @return bool
     */
    public function hasAlias(User $user, Exercise $exercise): bool
    {
        $aliases = $this->getUserAliases($user);
        return $aliases->has($exercise->id);
    }

    /**
     * Delete an alias
     *
     * @param ExerciseAlias $alias
     * @return bool
     */
    public function deleteAlias(ExerciseAlias $alias): bool
    {
        $userId = $alias->user_id;
        
        Log::info('Exercise alias deleted', [
            'alias_id' => $alias->id,
            'user_id' => $alias->user_id,
            'exercise_id' => $alias->exercise_id,
            'alias_name' => $alias->alias_name,
            'deleted_by' => auth()->id(),
        ]);

        $result = $alias->delete();

        // Invalidate cache for this user
        unset($this->userAliasesCache[$userId]);

        return $result;
    }

    /**
     * Clear the request-level cache
     * Useful for testing or when you need to force a refresh
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->userAliasesCache = [];
    }
}
