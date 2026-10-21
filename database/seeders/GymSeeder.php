<?php

namespace Database\Seeders;

use App\Sync\Models\Gym;
use Illuminate\Database\Seeder;

/**
 * Seeds the known gym partitions.
 *
 * These gym ids ('the-cove', 'flagship') are the cross-app join key and
 * MUST match Prism's `src/constants/accessControl.js` (`GYM_DOMAINS`)
 * byte-for-byte; renaming a shipped gym id orphans data.
 */
class GymSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Gym::updateOrCreate(
            ['id' => 'the-cove'],
            ['name' => 'The Cove']
        );

        Gym::updateOrCreate(
            ['id' => 'flagship'],
            ['name' => 'Flagship']
        );
    }
}
