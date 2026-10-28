<?php

namespace App\Sync\Controllers;

use App\Sync\Actions\UpsertPrismInterferenceDismissalsAction;
use App\Sync\Controllers\Concerns\ValidatesGym;
use App\Sync\Requests\StorePrismInterferenceDismissalsRequest;
use Illuminate\Http\JsonResponse;

class PrismInterferenceDismissalController
{
    use ValidatesGym;

    public function __construct(
        private readonly UpsertPrismInterferenceDismissalsAction $upsertDismissalsAction,
    ) {
    }

    /**
     * Store or update Prism interference dismissals and write tombstones for removals.
     */
    public function store(StorePrismInterferenceDismissalsRequest $request, string $gym): JsonResponse
    {
        $this->findGymOrFail($gym);

        $deviceId = $request->attributes->get('device_id');
        $dismissals = $request->validated('dismissals', []);
        $removals = $request->validated('removals', []);

        $updatedAt = $this->upsertDismissalsAction->execute($gym, $dismissals, $removals, $deviceId);

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt->toIso8601String(),
        ]);
    }
}
