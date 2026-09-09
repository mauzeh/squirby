<?php

namespace App\Sync\Controllers;

use App\Sync\Models\AthleteEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events'   => 'required|array|max:200',   // per-request event-count cap
            'events.*' => 'array',                     // opaque — NO inner-key rules
        ]);

        AthleteEvent::create([
            'user_id'    => $request->user()?->id,               // opportunistic; null for anonymous
            'device_id'  => $request->attributes->get('device_id'),
            'event_data' => ['events' => $validated['events']],  // verbatim
        ]);

        return response()->json(['status' => 'ok']);
    }
}
