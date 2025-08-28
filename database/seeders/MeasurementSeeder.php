<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Measurement;
use Carbon\Carbon;

class MeasurementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = fopen(base_path('database/seeders/csv/measurements_from_real_world.csv'), 'r');
        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                Measurement::create([
                    'name' => $data[2],
                    'value' => $data[3],
                    'unit' => $data[4],
                    'comments' => $data[5] ?? null,
                    'logged_at' => Carbon::parse($data[0] . ' ' . $data[1]),
                ]);
            }
            $firstline = false;
        }
        fclose($csvFile);
    }
}
