<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;

class GlobalExercisesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exercises = [
            ['title' => 'Back Squat', 'description' => 'A compound exercise that targets the muscles of the legs and core.', 'user_id' => null],
            ['title' => 'Bench Press', 'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.', 'user_id' => null],
            ['title' => 'Deadlift', 'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.', 'user_id' => null],
            ['title' => 'Strict Press', 'description' => 'A compound exercise that targets the shoulders and triceps.', 'user_id' => null],
            ['title' => 'Power Clean', 'description' => 'An explosive deadlift.', 'user_id' => null],
            ['title' => 'Half-Kneeling DB Press', 'description' => 'A unilateral exercise that targets the shoulders and core.', 'user_id' => null],
            ['title' => 'Cyclist Squat (Barbell, Front Rack)', 'description' => 'A squat variation that emphasizes the quadriceps by elevating the heels, performed with a barbell in the front rack position.', 'user_id' => null],
            ['title' => 'Chin-Ups', 'description' => 'A bodyweight pulling exercise with supinated grip.', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Pull-Ups', 'description' => 'A bodyweight pulling exercise with pronated grip.', 'is_bodyweight' => true, 'user_id' => null],
        ];

        foreach ($exercises as $exercise) {
            Exercise::firstOrCreate(
                ['title' => $exercise['title'], 'user_id' => null],
                $exercise
            );
        }
    }
}