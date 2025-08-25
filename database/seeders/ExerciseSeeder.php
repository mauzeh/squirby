<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Exercise::create([
            'title' => 'Back Squat',
            'description' => 'A compound exercise that targets the muscles of the legs and core.'
        ]);

        \App\Models\Exercise::create([
            'title' => 'Bench Press',
            'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.'
        ]);

        \App\Models\Exercise::create([
            'title' => 'Deadlift',
            'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.'
        ]);

        \App\Models\Exercise::create([
            'title' => 'Strict Press',
            'description' => 'A compound exercise that targets the shoulders and triceps.'
        ]);

        \App\Models\Exercise::create([
            'title' => 'Power Clean',
            'description' => 'An explosive deadlift.'
        ]);

    }
}
