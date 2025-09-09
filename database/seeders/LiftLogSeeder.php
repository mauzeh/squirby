<?php

namespace Database\Seeders;

use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class LiftLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();

        $csvFile = fopen(base_path('database/seeders/csv/workouts_from_real_world.csv'), 'r');
        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $exerciseTitle = $data[2];
                $exercise = \App\Models\Exercise::where('title', $exerciseTitle)->first();

                if ($exercise) {
                    $liftLog = LiftLog::create([
                        'user_id' => $adminUser->id,
                        'exercise_id' => $exercise->id,
                        'logged_at' => Carbon::parse($data[0] . ' ' . $data[1])->ceilMinute(15),
                        'comments' => $data[6]
                    ]);

                    // Create LiftSet records based on rounds
                    $weight = $data[3];
                    $reps = $data[4];
                    $rounds = $data[5];

                    for ($i = 0; $i < $rounds; $i++) {
                        LiftSet::create([
                            'lift_log_id' => $liftLog->id,
                            'weight' => $weight,
                            'reps' => $reps
                        ]);
                    }
                }
            }
            $firstline = false;
        }
        fclose($csvFile);
    }
}
