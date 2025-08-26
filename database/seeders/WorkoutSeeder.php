<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = fopen(base_path('database/seeders/csv/workouts_from_real_world.csv'), 'r');
        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $exerciseTitle = $data[2];
                $exercise = \App\Models\Exercise::where('title', $exerciseTitle)->first();

                if ($exercise) {
                    \App\Models\Workout::create([
                        'exercise_id' => $exercise->id,
                        'weight' => $data[3],
                        'reps' => $data[4],
                        'rounds' => $data[5],
                        'comments' => $data[6],
                        'logged_at' => \Carbon\Carbon::parse($data[0] . ' ' . $data[1])->ceilMinute(15),
                    ]);
                }
            }
            $firstline = false;
        }
        fclose($csvFile);
    }
}
