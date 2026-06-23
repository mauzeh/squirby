<?php

namespace App\Sync\Controllers;

use App\Sync\Models\AthleteBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintController
{
    /**
     * Store the athlete blueprint.
     */
    public function store(Request $request): JsonResponse
    {
        $blueprintData = $request->except(['device_id']);
        $deviceId = $request->attributes->get('device_id');

        AthleteBlueprint::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'blueprint_data' => $blueprintData,
                'device_id' => $deviceId,
            ]
        );

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
