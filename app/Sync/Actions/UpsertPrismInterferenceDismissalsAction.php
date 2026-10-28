<?php

namespace App\Sync\Actions;

use App\Sync\Models\PrismInterferenceDismissal;
use Illuminate\Support\Carbon;

class UpsertPrismInterferenceDismissalsAction
{
    /**
     * Upsert interference dismissals and write tombstones for removals.
     *
     * @param  array<int, array{resolution_key: string}>  $upserts
     * @param  array<int, array{resolution_key: string}>  $removals
     */
    public function execute(string $gymId, array $upserts, array $removals, ?string $deviceId): Carbon
    {
        if (empty($upserts) && empty($removals)) {
            return Carbon::now();
        }

        $touchedKeys = [];
        $now = Carbon::now();

        if (! empty($upserts)) {
            $upsertRows = [];
            foreach ($upserts as $item) {
                $resolutionKey = (string) $item['resolution_key'];
                $touchedKeys[] = $resolutionKey;
                $upsertRows[] = [
                    'gym_id' => $gymId,
                    'resolution_key' => $resolutionKey,
                    'device_id' => $deviceId,
                    'deleted_at' => null,
                ];
            }

            PrismInterferenceDismissal::upsert(
                $upsertRows,
                ['gym_id', 'resolution_key'],
                ['device_id', 'deleted_at']
            );
        }

        if (! empty($removals)) {
            $removalRows = [];
            $nowFormatted = $now->format('Y-m-d H:i:s.u');
            foreach ($removals as $item) {
                $resolutionKey = (string) $item['resolution_key'];
                $touchedKeys[] = $resolutionKey;
                $removalRows[] = [
                    'gym_id' => $gymId,
                    'resolution_key' => $resolutionKey,
                    'device_id' => $deviceId,
                    'deleted_at' => $nowFormatted,
                ];
            }

            PrismInterferenceDismissal::upsert(
                $removalRows,
                ['gym_id', 'resolution_key'],
                ['device_id', 'deleted_at']
            );
        }

        $maxUpdatedAt = PrismInterferenceDismissal::query()
            ->where('gym_id', $gymId)
            ->whereIn('resolution_key', array_unique($touchedKeys))
            ->max('updated_at');

        $maxDeletedAt = PrismInterferenceDismissal::query()
            ->where('gym_id', $gymId)
            ->whereIn('resolution_key', array_unique($touchedKeys))
            ->max('deleted_at');

        $timestamps = array_filter([$maxUpdatedAt, $maxDeletedAt]);

        if (empty($timestamps)) {
            return $now;
        }

        $carbonTimestamps = array_map(fn ($ts) => Carbon::parse($ts), $timestamps);

        return max($carbonTimestamps);
    }
}
