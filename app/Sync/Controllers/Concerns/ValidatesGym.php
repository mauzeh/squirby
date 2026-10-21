<?php

namespace App\Sync\Controllers\Concerns;

use App\Sync\Models\Gym;

trait ValidatesGym
{
    /**
     * Validate that the gym exists or abort with 404.
     */
    protected function findGymOrFail(string $gymId): Gym
    {
        $gym = Gym::find($gymId);

        if (! $gym) {
            abort(404, 'Gym not found.');
        }

        return $gym;
    }
}
