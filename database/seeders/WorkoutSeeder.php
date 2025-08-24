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
        $benchPress = \App\Models\Exercise::where('title', 'Bench Press')->first();
        $strictPress = \App\Models\Exercise::where('title', 'Strict Press')->first();
        $deadlift = \App\Models\Exercise::where('title', 'Deadlift')->first();
        $backSquat = \App\Models\Exercise::where('title', 'Back Squat')->first();

        \App\Models\Workout::create([
            'exercise_id' => $benchPress->id,
            'weight' => 135,
            'reps' => 5,
            'rounds' => 3,
            'comments' => "45x10\n95x5",
            'logged_at' => now()->subDays(2),
        ]);

        \App\Models\Workout::create([
            'exercise_id' => $strictPress->id,
            'weight' => 95,
            'reps' => 5,
            'rounds' => 3,
            'comments' => "45x10\n65x5",
            'logged_at' => now()->subDays(1),
        ]);

        \App\Models\Workout::create([
            'exercise_id' => $deadlift->id,
            'weight' => 225,
            'reps' => 5,
            'rounds' => 1,
            'comments' => "135x5\n185x3",
            'logged_at' => now()->subDays(1),
        ]);

        \App\Models\Workout::create([
            'exercise_id' => $backSquat->id,
            'weight' => 185,
            'reps' => 5,
            'rounds' => 3,
            'comments' => "45x10\n135x5",
            'logged_at' => now()->subDays(1),
        ]);
    }
}
