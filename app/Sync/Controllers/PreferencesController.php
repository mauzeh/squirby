<?php

namespace App\Sync\Controllers;

use App\Sync\Models\AthletePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferencesController
{
    /**
     * Store the athlete preferences.
     */
    public function store(Request $request): JsonResponse
    {
        $preferencesData = $request->except(['device_id']);
        $deviceId = $request->attributes->get('device_id');

        AthletePreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'preferences_data' => $preferencesData,
                'device_id' => $deviceId,
            ]
        );

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
