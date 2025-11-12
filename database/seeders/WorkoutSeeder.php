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
        // Get the first user (admin)
        $user = \App\Models\User::first();
        
        if (!$user) {
            $this->command->warn('No users found. Skipping workout seeding.');
            return;
        }

        // Create example workouts - just exercises in priority order
        $workouts = [
            [
                'name' => 'Push Day',
                'description' => 'Upper body pushing exercises',
                'tags' => ['push', 'strength', 'intermediate'],
                'exercises' => [
                    'Bench Press',
                    'Strict Press',
                    'Dips',
                    'Tricep Extensions',
                ],
            ],
            [
                'name' => 'Pull Day',
                'description' => 'Upper body pulling exercises',
                'tags' => ['pull', 'strength', 'intermediate'],
                'exercises' => [
                    'Deadlift',
                    'Pull-Ups',
                    'Rows',
                    'Bicep Curls',
                ],
            ],
            [
                'name' => 'Leg Day',
                'description' => 'Lower body exercises',
                'tags' => ['legs', 'strength', 'intermediate'],
                'exercises' => [
                    'Back Squat',
                    'Romanian Deadlift',
                    'Lunges',
                    'Leg Curls',
                ],
            ],
            [
                'name' => 'Full Body A',
                'description' => 'Beginner full body workout',
                'tags' => ['full-body', 'strength', 'beginner'],
                'exercises' => [
                    'Back Squat',
                    'Bench Press',
                    'Deadlift',
                    'Pull-Ups',
                ],
            ],
            [
                'name' => 'Full Body B',
                'description' => 'Beginner full body workout (alternate)',
                'tags' => ['full-body', 'strength', 'beginner'],
                'exercises' => [
                    'Front Squat',
                    'Strict Press',
                    'Romanian Deadlift',
                    'Rows',
                ],
            ],
        ];

        foreach ($workouts as $workoutData) {
            $workout = \App\Models\Workout::create([
                'user_id' => $user->id,
                'name' => $workoutData['name'],
                'description' => $workoutData['description'],
                'is_public' => false,
                'tags' => $workoutData['tags'],
            ]);

            foreach ($workoutData['exercises'] as $index => $exerciseName) {
                // Find or create exercise
                $exercise = \App\Models\Exercise::firstOrCreate(
                    ['title' => $exerciseName],
                    ['user_id' => $user->id]
                );

                \App\Models\WorkoutExercise::create([
                    'workout_id' => $workout->id,
                    'exercise_id' => $exercise->id,
                    'order' => $index + 1,
                ]);
            }

            $this->command->info("Created workout: {$workout->name}");
        }

        $this->command->info('Workouts seeded successfully!');
    }
}
