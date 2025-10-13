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
            // Original global exercises
            ['title' => 'Back Squat', 'description' => 'A compound exercise that targets the muscles of the legs and core.', 'user_id' => null],
            ['title' => 'Bench Press', 'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.', 'user_id' => null],
            ['title' => 'Deadlift', 'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.', 'user_id' => null],
            ['title' => 'Strict Press', 'description' => 'A compound exercise that targets the shoulders and triceps.', 'user_id' => null],
            ['title' => 'Power Clean', 'description' => 'An explosive deadlift.', 'user_id' => null],
            ['title' => 'Half-Kneeling DB Press', 'description' => 'A unilateral exercise that targets the shoulders and core.', 'user_id' => null],
            ['title' => 'Cyclist Squat (Barbell, Front Rack)', 'description' => 'A squat variation that emphasizes the quadriceps by elevating the heels, performed with a barbell in the front rack position.', 'user_id' => null],
            ['title' => 'Chin-Ups', 'description' => 'A bodyweight pulling exercise with supinated grip.', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Pull-Ups', 'description' => 'A bodyweight pulling exercise with pronated grip.', 'is_bodyweight' => true, 'user_id' => null],
            
            // Exercises migrated from user=1 to global
            ['title' => 'Back Rack Lunge (Step Back)', 'description' => 'Reps are per leg', 'user_id' => null],
            ['title' => 'Bench Press (2-DB Seesaw)', 'description' => 'Weight is that of a single DB', 'user_id' => null],
            ['title' => 'DB Bench Press', 'description' => '', 'user_id' => null],
            ['title' => 'Front Squat', 'description' => '', 'user_id' => null],
            ['title' => 'Hip Thrust (Barbell)', 'description' => '', 'user_id' => null],
            ['title' => 'Kettlebell Swing', 'description' => '', 'user_id' => null],
            ['title' => 'L-Sit (Tucked, Parallelites)', 'description' => '', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Lat Pull-Down (Kneeled)', 'description' => 'Use the comments to indicate the color of the band you used', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Pendlay Row', 'description' => '', 'user_id' => null],
            ['title' => 'Push Press', 'description' => '', 'user_id' => null],
            ['title' => 'Push-Up', 'description' => '', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Ring Row', 'description' => '', 'is_bodyweight' => true, 'user_id' => null],
            ['title' => 'Walking Lunge (2-DB)', 'description' => 'Weight is both dumbbells combined', 'user_id' => null],
            ['title' => 'Zombie Squat', 'description' => '', 'user_id' => null],
            ['title' => 'Romanian Deadlift', 'description' => '', 'user_id' => null],
        ];

        foreach ($exercises as $exercise) {
            Exercise::firstOrCreate(
                ['title' => $exercise['title'], 'user_id' => null],
                $exercise
            );
        }
    }
}