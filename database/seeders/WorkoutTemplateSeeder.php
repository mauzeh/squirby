<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkoutTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (admin)
        $user = \App\Models\User::first();
        
        if (!$user) {
            $this->command->warn('No users found. Skipping workout template seeding.');
            return;
        }

        // Create example templates - just exercises in priority order
        $templates = [
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

        foreach ($templates as $templateData) {
            $template = \App\Models\WorkoutTemplate::create([
                'user_id' => $user->id,
                'name' => $templateData['name'],
                'description' => $templateData['description'],
                'is_public' => false,
                'tags' => $templateData['tags'],
            ]);

            foreach ($templateData['exercises'] as $index => $exerciseName) {
                // Find or create exercise
                $exercise = \App\Models\Exercise::firstOrCreate(
                    ['title' => $exerciseName],
                    ['user_id' => $user->id]
                );

                \App\Models\WorkoutTemplateExercise::create([
                    'workout_template_id' => $template->id,
                    'exercise_id' => $exercise->id,
                    'order' => $index + 1,
                ]);
            }

            $this->command->info("Created template: {$template->name}");
        }

        $this->command->info('Workout templates seeded successfully!');
    }
}
