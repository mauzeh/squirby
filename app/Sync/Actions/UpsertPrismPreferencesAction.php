<?php

namespace App\Sync\Actions;

use App\Sync\Models\PrismPreference;
use Illuminate\Support\Carbon;

class UpsertPrismPreferencesAction
{
    /**
     * Upsert Prism preferences for a gym.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(string $gymId, array $data, ?string $deviceId): Carbon
    {
        $preference = PrismPreference::updateOrCreate(
            ['gym_id' => $gymId],
            [
                'data' => $data,
                'device_id' => $deviceId,
            ]
        );

        return $preference->updated_at;
    }
}
