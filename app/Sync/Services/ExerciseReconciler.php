<?php

namespace App\Sync\Services;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExerciseReconciler
{
    /**
     * Apply a changeset file to the database.
     */
    public static function apply(string $changesetFile): void
    {
        $path = database_path("changesets/exercises/{$changesetFile}");
        if (!file_exists($path)) {
            throw new \RuntimeException("Changeset file not found at: {$path}");
        }

        $changeset = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in changeset file: " . json_last_error_msg());
        }

        $operations = $changeset['operations'] ?? [];

        DB::transaction(function () use ($operations) {
            foreach ($operations as $op) {
                if (!isset($op['action'])) {
                    throw new \RuntimeException("Missing 'action' key in operation.");
                }

                switch ($op['action']) {
                    case 'rename':
                        self::handleRename($op);
                        break;
                    case 'create_alias':
                        self::handleCreateAlias($op);
                        break;
                    case 'rename_canonical':
                        self::handleRenameCanonical($op);
                        break;
                    case 'add_exercise':
                        self::handleAddExercise($op);
                        break;
                    default:
                        throw new \RuntimeException("Unknown operation type: {$op['action']}");
                }
            }
        });
    }

    /**
     * Rollback a changeset file in the database.
     */
    public static function rollback(string $changesetFile): void
    {
        $path = database_path("changesets/exercises/{$changesetFile}");
        if (!file_exists($path)) {
            throw new \RuntimeException("Changeset file not found at: {$path}");
        }

        $changeset = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in changeset file: " . json_last_error_msg());
        }

        $operations = $changeset['operations'] ?? [];
        $reversedOps = array_reverse($operations);

        DB::transaction(function () use ($reversedOps) {
            foreach ($reversedOps as $op) {
                if (!isset($op['action'])) {
                    throw new \RuntimeException("Missing 'action' key in operation.");
                }

                switch ($op['action']) {
                    case 'rename':
                        self::rollbackRename($op);
                        break;
                    case 'create_alias':
                        self::rollbackCreateAlias($op);
                        break;
                    case 'rename_canonical':
                        self::rollbackRenameCanonical($op);
                        break;
                    case 'add_exercise':
                        self::rollbackAddExercise($op);
                        break;
                    default:
                        throw new \RuntimeException("Unknown operation type: {$op['action']}");
                }
            }
        });
    }

    // ─── Apply Handlers ──────────────────────────────────────────────────────

    protected static function handleRename(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['canonical_name'])
            ->first();

        if ($exercise) {
            $exercise->update(['title' => $op['new_title']]);
            Log::info("ExerciseReconciler: Renamed exercise '{$op['canonical_name']}' from '{$op['old_title']}' to '{$op['new_title']}'");
        } else {
            Log::warning("ExerciseReconciler: Exercise '{$op['canonical_name']}' not found for rename.");
        }
    }

    protected static function handleCreateAlias(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['canonical_name'])
            ->first();

        if ($exercise) {
            ExerciseAlias::firstOrCreate([
                'exercise_id' => $exercise->id,
                'alias_name' => $op['alias_name'],
                'user_id' => null,
            ]);
            Log::info("ExerciseReconciler: Created alias '{$op['alias_name']}' for exercise '{$op['canonical_name']}'");
        } else {
            Log::warning("ExerciseReconciler: Exercise '{$op['canonical_name']}' not found to associate alias '{$op['alias_name']}'.");
        }
    }

    protected static function handleRenameCanonical(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['old_canonical'])
            ->first();

        if ($exercise) {
            $exercise->update([
                'canonical_name' => $op['new_canonical'],
                'title' => $op['new_title']
            ]);

            // Auto-create alias mapping old canonical to the renamed exercise
            ExerciseAlias::firstOrCreate([
                'exercise_id' => $exercise->id,
                'alias_name' => $op['old_canonical'],
                'user_id' => null,
            ]);

            Log::info("ExerciseReconciler: Renamed canonical '{$op['old_canonical']}' to '{$op['new_canonical']}' (new title: '{$op['new_title']}') and added old canonical as alias.");
        } else {
            Log::warning("ExerciseReconciler: Exercise '{$op['old_canonical']}' not found for rename_canonical.");
        }
    }

    protected static function handleAddExercise(array $op): void
    {
        Exercise::firstOrCreate(
            ['canonical_name' => $op['canonical_name'], 'user_id' => null],
            [
                'title' => $op['title'],
                'exercise_type' => $op['exercise_type'],
                'log_type' => $op['log_type'] ?? null,
                'show_in_feed' => true
            ]
        );
        Log::info("ExerciseReconciler: Added/verified exercise '{$op['canonical_name']}' (title: '{$op['title']}')");
    }

    // ─── Rollback Handlers ───────────────────────────────────────────────────

    protected static function rollbackRename(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['canonical_name'])
            ->first();

        if ($exercise) {
            $exercise->update(['title' => $op['old_title']]);
            Log::info("ExerciseReconciler Rollback: Restored title of '{$op['canonical_name']}' to '{$op['old_title']}'");
        }
    }

    protected static function rollbackCreateAlias(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['canonical_name'])
            ->first();

        if ($exercise) {
            ExerciseAlias::whereNull('user_id')
                ->where('exercise_id', $exercise->id)
                ->where('alias_name', $op['alias_name'])
                ->forceDelete();
            Log::info("ExerciseReconciler Rollback: Deleted alias '{$op['alias_name']}'");
        }
    }

    protected static function rollbackRenameCanonical(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['new_canonical'])
            ->first();

        if ($exercise) {
            // Delete alias created for the old canonical
            ExerciseAlias::whereNull('user_id')
                ->where('exercise_id', $exercise->id)
                ->where('alias_name', $op['old_canonical'])
                ->forceDelete();

            $exercise->update([
                'canonical_name' => $op['old_canonical'],
                'title' => $op['old_title']
            ]);

            Log::info("ExerciseReconciler Rollback: Restored canonical '{$op['new_canonical']}' to '{$op['old_canonical']}' (title: '{$op['old_title']}')");
        }
    }

    protected static function rollbackAddExercise(array $op): void
    {
        $exercise = Exercise::whereNull('user_id')
            ->where('canonical_name', $op['canonical_name'])
            ->first();

        if ($exercise) {
            if (!$exercise->liftLogs()->exists()) {
                $exercise->forceDelete();
                Log::info("ExerciseReconciler Rollback: Deleted exercise '{$op['canonical_name']}' as it had no lift logs.");
            } else {
                Log::info("ExerciseReconciler Rollback: Retained exercise '{$op['canonical_name']}' because it has associated lift logs.");
            }
        }
    }
}
