<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BodyLog;
use App\Models\MeasurementType;
use App\Models\User;
use Carbon\Carbon;

class MeasurementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();

        $csvFile = fopen(base_path('database/seeders/csv/measurements_from_real_world.csv'), 'r');
        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $measurementType = MeasurementType::firstOrCreate([
                    'user_id' => $adminUser->id,
                    'name' => $data[2],
                    'default_unit' => $data[4],
                ]);

                BodyLog::create([
                    'user_id' => $adminUser->id,
                    'measurement_type_id' => $measurementType->id,
                    'value' => $data[3],
                    'comments' => $data[5] ?? null,
                    'logged_at' => Carbon::parse($data[0] . ' ' . $data[1]),
                ]);
            }
            $firstline = false;
        }
        fclose($csvFile);
    }
}
