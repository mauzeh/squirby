<?php

namespace App\Sync\Controllers;

use App\Sync\Actions\UpsertPrismPreferencesAction;
use App\Sync\Controllers\Concerns\ValidatesGym;
use App\Sync\Requests\StorePrismPreferencesRequest;
use Illuminate\Http\JsonResponse;

class PrismPreferencesController
{
    use ValidatesGym;

    public function __construct(
        private readonly UpsertPrismPreferencesAction $upsertPreferencesAction,
    ) {
    }

    /**
     * Store or update Prism preferences for a gym.
     */
    public function store(StorePrismPreferencesRequest $request, string $gym): JsonResponse
    {
        $this->findGymOrFail($gym);

        $deviceId = $request->attributes->get('device_id');
        $data = $request->validated('data');

        $updatedAt = $this->upsertPreferencesAction->execute($gym, $data, $deviceId);

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt->toIso8601String(),
        ]);
    }
}
