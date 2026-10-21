<?php

namespace App\Sync\Actions;

use App\Sync\Models\PrismProgramCell;
use Illuminate\Support\Carbon;

class UpsertPrismCellsAction
{
    /**
     * Upsert a batch of Prism program cells and return the max updated_at.
     *
     * @param  array<int, array{cell_id: string, text: string}>  $cells
     */
    public function execute(string $gymId, string $programUuid, array $cells, ?string $deviceId): Carbon
    {
        $rows = [];
        $cellIds = [];

        foreach ($cells as $cell) {
            $cellId = (string) $cell['cell_id'];
            $cellIds[] = $cellId;
            $rows[] = [
                'gym_id' => $gymId,
                'program_uuid' => $programUuid,
                'cell_id' => $cellId,
                'text' => $cell['text'],
                'device_id' => $deviceId,
            ];
        }

        PrismProgramCell::upsert(
            $rows,
            ['gym_id', 'program_uuid', 'cell_id'],
            ['text', 'device_id']
        );

        $maxUpdatedAt = PrismProgramCell::query()
            ->where('gym_id', $gymId)
            ->where('program_uuid', $programUuid)
            ->whereIn('cell_id', $cellIds)
            ->max('updated_at');

        return $maxUpdatedAt ? Carbon::parse($maxUpdatedAt) : Carbon::now();
    }
}
