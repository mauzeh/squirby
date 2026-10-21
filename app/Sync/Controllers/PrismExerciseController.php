<?php

namespace App\Sync\Controllers;

use App\Sync\Actions\UpsertPrismExercisesAction;
use App\Sync\Controllers\Concerns\ValidatesGym;
use App\Sync\Requests\StorePrismExercisesRequest;
use Illuminate\Http\JsonResponse;

class PrismExerciseController
{
    use ValidatesGym;

    public function __construct(
        private readonly UpsertPrismExercisesAction $upsertExercisesAction,
    ) {
    }

    /**
     * Store or update Prism exercises and write tombstones for removals.
     */
    public function store(StorePrismExercisesRequest $request, string $gym): JsonResponse
    {
        $this->findGymOrFail($gym);

        $deviceId = $request->attributes->get('device_id');
        $upserts = $request->validated('upserts', []);
        $removals = $request->validated('removals', []);

        $updatedAt = $this->upsertExercisesAction->execute($gym, $upserts, $removals, $deviceId);

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt->toIso8601String(),
        ]);
    }
}
