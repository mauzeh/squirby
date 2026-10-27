<?php

namespace App\Sync\Controllers;

use App\Sync\Actions\UpsertPrismCellsAction;
use App\Sync\Actions\UpsertPrismProgramAction;
use App\Sync\Controllers\Concerns\ValidatesGym;
use App\Sync\Requests\StorePrismCellsRequest;
use App\Sync\Requests\StorePrismProgramRequest;
use Illuminate\Http\JsonResponse;

class PrismProgramController
{
    use ValidatesGym;

    public function __construct(
        private readonly UpsertPrismProgramAction $upsertProgramAction,
        private readonly UpsertPrismCellsAction $upsertCellsAction,
    ) {
    }

    /**
     * Store or update a Prism program.
     */
    public function storeProgram(StorePrismProgramRequest $request, string $gym, string $program_uuid): JsonResponse
    {
        $this->findGymOrFail($gym);

        $deviceId = $request->attributes->get('device_id');
        $attributes = $request->validated();
        if ($deviceId !== null) {
            $attributes['device_id'] = $deviceId;
        }

        $updatedAt = $this->upsertProgramAction->execute($gym, $program_uuid, $attributes);

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt->toIso8601String(),
        ]);
    }

    /**
     * Store or update cells for a Prism program.
     */
    public function storeCells(StorePrismCellsRequest $request, string $gym, string $program_uuid): JsonResponse
    {
        $this->findGymOrFail($gym);

        $deviceId = $request->attributes->get('device_id');
        $cells = $request->validated('cells', []);
        $removals = $request->validated('removals', []);

        $updatedAt = $this->upsertCellsAction->execute($gym, $program_uuid, $cells, $removals, $deviceId);

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt->toIso8601String(),
        ]);
    }
}
