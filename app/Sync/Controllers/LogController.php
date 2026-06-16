<?php

namespace App\Sync\Controllers;

use App\Models\LiftLog;
use App\Sync\Actions\DeleteSyncLogAction;
use App\Sync\Actions\StoreSyncLogAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController
{
    public function __construct(
        private StoreSyncLogAction $storeSyncLogAction,
        private DeleteSyncLogAction $deleteSyncLogAction,
    ) {}

    /**
     * Store a synced completion log.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exercise_name' => 'required|string',
            'canonical_name' => 'nullable|string',
            'date' => 'required|date',
            'log_type' => 'required|string',
            'weight_unit' => 'required|string',
            'sets' => 'required|array|max:100',
            'track' => 'nullable|string',
            'block_index' => 'nullable|integer',
            'movement_index' => 'nullable|integer',
            'note' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if ($idempotencyKey) {
            $validated['idempotency_key'] = $idempotencyKey;
        }

        $deviceId = $request->attributes->get('device_id');
        $liftLog = $this->storeSyncLogAction->execute($request->user(), $validated, $deviceId);

        return response()->json([
            'status' => 'ok',
            'log_id' => $liftLog->id,
            'updated_at' => $liftLog->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Destroy a synced completion log.
     */
    public function destroy(Request $request, LiftLog $liftLog): JsonResponse
    {
        $this->deleteSyncLogAction->execute($request->user(), $liftLog);

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
