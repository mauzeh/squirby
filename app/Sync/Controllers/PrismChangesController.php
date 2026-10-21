<?php

namespace App\Sync\Controllers;

use App\Sync\Controllers\Concerns\ValidatesGym;
use App\Sync\Models\PrismExerciseOverlayRecord;
use App\Sync\Models\PrismPreference;
use App\Sync\Models\PrismProgram;
use App\Sync\Models\PrismProgramCell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PrismChangesController
{
    use ValidatesGym;

    /**
     * Return gym-scoped changes and cursor for Prism sync.
     *
     * GET /api/sync/prism/{gym}/changes?since=<cursor>
     */
    public function index(Request $request, string $gym): JsonResponse
    {
        $this->findGymOrFail($gym);

        $since = null;
        if ($request->filled('since')) {
            $request->validate([
                'since' => 'date',
            ]);
            $since = Carbon::parse($request->query('since'));
        }

        // 1. Programs
        $programsQuery = PrismProgram::where('gym_id', $gym);
        if ($since !== null) {
            $programsQuery->where('updated_at', '>=', $since);
        }
        $programs = $programsQuery->get()->map(function (PrismProgram $program) {
            return [
                'program_uuid' => $program->program_uuid,
                'name' => $program->name,
                'week_count' => (int) $program->week_count,
                'metadata' => $program->metadata,
                'updated_at' => $program->updated_at->toIso8601String(),
            ];
        })->values()->all();

        // 2. Cells
        $cellsQuery = PrismProgramCell::where('gym_id', $gym);
        if ($since !== null) {
            $cellsQuery->where('updated_at', '>=', $since);
        }
        $cells = $cellsQuery->get()->map(function (PrismProgramCell $cell) {
            return [
                'program_uuid' => $cell->program_uuid,
                'cell_id' => $cell->cell_id,
                'text' => $cell->text,
                'updated_at' => $cell->updated_at->toIso8601String(),
            ];
        })->values()->all();

        // 3. Exercises (overlays + tombstones)
        $exercisesQuery = PrismExerciseOverlayRecord::where('gym_id', $gym);
        if ($since !== null) {
            $exercisesQuery->where('updated_at', '>=', $since);
        }
        $exercises = $exercisesQuery->get()->map(function (PrismExerciseOverlayRecord $record) {
            return [
                'exercise_id' => $record->exercise_id,
                'record' => $record->record,
                'deleted_at' => $record->deleted_at?->toIso8601String(),
                'updated_at' => $record->updated_at->toIso8601String(),
            ];
        })->values()->all();

        // 4. Preferences
        $preferencesQuery = PrismPreference::where('gym_id', $gym);
        if ($since !== null) {
            $preferencesQuery->where('updated_at', '>=', $since);
        }
        $pref = $preferencesQuery->first();
        $preferences = $pref ? [
            'data' => $pref->data,
            'updated_at' => $pref->updated_at->toIso8601String(),
        ] : null;

        // 5. Compute cursor high-water-mark across all gym rows
        $maxProgramUpdated = PrismProgram::where('gym_id', $gym)->max('updated_at');
        $maxCellUpdated = PrismProgramCell::where('gym_id', $gym)->max('updated_at');
        $maxExerciseUpdated = PrismExerciseOverlayRecord::where('gym_id', $gym)->max('updated_at');
        $maxPreferenceUpdated = PrismPreference::where('gym_id', $gym)->max('updated_at');

        $updatedInstants = array_filter([
            $maxProgramUpdated ? Carbon::parse($maxProgramUpdated) : null,
            $maxCellUpdated ? Carbon::parse($maxCellUpdated) : null,
            $maxExerciseUpdated ? Carbon::parse($maxExerciseUpdated) : null,
            $maxPreferenceUpdated ? Carbon::parse($maxPreferenceUpdated) : null,
        ]);

        $maxUpdated = null;
        foreach ($updatedInstants as $instant) {
            if ($maxUpdated === null || $instant->gt($maxUpdated)) {
                $maxUpdated = $instant;
            }
        }

        $rawMaxDeleted = PrismExerciseOverlayRecord::where('gym_id', $gym)
            ->whereNotNull('deleted_at')
            ->max('deleted_at');
        $maxDeleted = $rawMaxDeleted ? Carbon::parse($rawMaxDeleted) : null;

        $maxInstant = null;
        if ($maxUpdated && $maxDeleted) {
            $maxInstant = $maxUpdated->gte($maxDeleted) ? $maxUpdated : $maxDeleted;
        } elseif ($maxUpdated) {
            $maxInstant = $maxUpdated;
        } elseif ($maxDeleted) {
            $maxInstant = $maxDeleted;
        }

        if ($since !== null && ($maxInstant === null || $since->gt($maxInstant))) {
            $cursorInstant = $since;
        } else {
            $cursorInstant = $maxInstant ?? now();
        }

        $cursor = $cursorInstant->toIso8601String();

        return response()->json([
            'status' => 'ok',
            'cursor' => $cursor,
            'programs' => $programs,
            'cells' => $cells,
            'exercises' => $exercises,
            'preferences' => $preferences,
        ]);
    }
}
