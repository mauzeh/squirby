<?php

namespace App\Sync\Actions;

use App\Sync\Models\PrismExerciseOverlayRecord;
use Illuminate\Support\Carbon;

class UpsertPrismExercisesAction
{
    /**
     * Upsert exercise overlays and write tombstones for removals.
     *
     * @param  array<int, array{exercise_id: string, record: array<string, mixed>}>  $upserts
     * @param  array<int, array{exercise_id: string}>  $removals
     */
    public function execute(string $gymId, array $upserts, array $removals, ?string $deviceId): Carbon
    {
        if (empty($upserts) && empty($removals)) {
            return Carbon::now();
        }

        $touchedExerciseIds = [];
        $now = Carbon::now();

        if (! empty($upserts)) {
            $upsertRows = [];
            foreach ($upserts as $item) {
                $exerciseId = (string) $item['exercise_id'];
                $touchedExerciseIds[] = $exerciseId;
                $upsertRows[] = [
                    'gym_id' => $gymId,
                    'exercise_id' => $exerciseId,
                    'record' => json_encode($item['record']),
                    'device_id' => $deviceId,
                    'deleted_at' => null,
                ];
            }

            PrismExerciseOverlayRecord::upsert(
                $upsertRows,
                ['gym_id', 'exercise_id'],
                ['record', 'device_id', 'deleted_at']
            );
        }

        if (! empty($removals)) {
            $removalRows = [];
            $nowFormatted = $now->format('Y-m-d H:i:s.u');
            foreach ($removals as $item) {
                $exerciseId = (string) $item['exercise_id'];
                $touchedExerciseIds[] = $exerciseId;
                $removalRows[] = [
                    'gym_id' => $gymId,
                    'exercise_id' => $exerciseId,
                    'record' => null,
                    'device_id' => $deviceId,
                    'deleted_at' => $nowFormatted,
                ];
            }

            PrismExerciseOverlayRecord::upsert(
                $removalRows,
                ['gym_id', 'exercise_id'],
                ['record', 'device_id', 'deleted_at']
            );
        }

        $maxUpdatedAt = PrismExerciseOverlayRecord::query()
            ->where('gym_id', $gymId)
            ->whereIn('exercise_id', array_unique($touchedExerciseIds))
            ->max('updated_at');

        $maxDeletedAt = PrismExerciseOverlayRecord::query()
            ->where('gym_id', $gymId)
            ->whereIn('exercise_id', array_unique($touchedExerciseIds))
            ->max('deleted_at');

        $timestamps = array_filter([$maxUpdatedAt, $maxDeletedAt]);

        if (empty($timestamps)) {
            return $now;
        }

        $carbonTimestamps = array_map(fn ($ts) => Carbon::parse($ts), $timestamps);

        return max($carbonTimestamps);
    }
}
