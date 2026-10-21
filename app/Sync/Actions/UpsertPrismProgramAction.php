<?php

namespace App\Sync\Actions;

use App\Sync\Models\PrismProgram;
use Illuminate\Support\Carbon;

class UpsertPrismProgramAction
{
    /**
     * Upsert a Prism program metadata record.
     */
    public function execute(string $gymId, string $programUuid, array $attributes): Carbon
    {
        $program = PrismProgram::updateOrCreate(
            [
                'gym_id' => $gymId,
                'program_uuid' => $programUuid,
            ],
            [
                'name' => $attributes['name'],
                'week_count' => $attributes['week_count'],
                'metadata' => $attributes['metadata'] ?? null,
                'archived_at' => $attributes['archived_at'] ?? null,
                'device_id' => $attributes['device_id'] ?? null,
            ]
        );

        return $program->updated_at;
    }
}
