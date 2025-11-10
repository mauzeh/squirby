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

        // Create example templates based on the brainstorm document
        $templates = [
            [
                'name' => 'Push Day',
                'description' => 'Upper body pushing exercises',
                'tags' => ['push', 'strength', 'intermediate'],
                'exercises' => [
                    ['name' => 'Bench Press', 'sets' => 4, 'reps' => 6],
                    ['name' => 'Strict Press', 'sets' => 3, 'reps' => 8],
                    ['name' => 'Dips', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Tricep Extensions', 'sets' => 3, 'reps' => 12],
                ],
            ],
            [
                'name' => 'Pull Day',
                'description' => 'Upper body pulling exercises',
                'tags' => ['pull', 'strength', 'intermediate'],
                'exercises' => [
                    ['name' => 'Deadlift', 'sets' => 4, 'reps' => 5],
                    ['name' => 'Pull-Ups', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Rows', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Bicep Curls', 'sets' => 3, 'reps' => 12],
                ],
            ],
            [
                'name' => 'Leg Day',
                'description' => 'Lower body exercises',
                'tags' => ['legs', 'strength', 'intermediate'],
                'exercises' => [
                    ['name' => 'Back Squat', 'sets' => 4, 'reps' => 6],
                    ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => 8],
                    ['name' => 'Lunges', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Leg Curls', 'sets' => 3, 'reps' => 12],
                ],
            ],
            [
                'name' => 'Full Body A',
                'description' => 'Beginner full body workout',
                'tags' => ['full-body', 'strength', 'beginner'],
                'exercises' => [
                    ['name' => 'Back Squat', 'sets' => 3, 'reps' => 5],
                    ['name' => 'Bench Press', 'sets' => 3, 'reps' => 5],
                    ['name' => 'Deadlift', 'sets' => 1, 'reps' => 5],
                    ['name' => 'Pull-Ups', 'sets' => 3, 'reps' => 8],
                ],
            ],
            [
                'name' => 'Full Body B',
                'description' => 'Beginner full body workout (alternate)',
                'tags' => ['full-body', 'strength', 'beginner'],
                'exercises' => [
                    ['name' => 'Front Squat', 'sets' => 3, 'reps' => 5],
                    ['name' => 'Strict Press', 'sets' => 3, 'reps' => 5],
                    ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => 8],
                    ['name' => 'Rows', 'sets' => 3, 'reps' => 8],
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

            foreach ($templateData['exercises'] as $index => $exerciseData) {
                // Find or create exercise
                $exercise = \App\Models\Exercise::firstOrCreate(
                    ['title' => $exerciseData['name']],
                    ['user_id' => $user->id]
                );

                \App\Models\WorkoutTemplateExercise::create([
                    'workout_template_id' => $template->id,
                    'exercise_id' => $exercise->id,
                    'sets' => $exerciseData['sets'],
                    'reps' => $exerciseData['reps'],
                    'order' => $index + 1,
                ]);
            }

            $this->command->info("Created template: {$template->name}");
        }

        $this->command->info('Workout templates seeded successfully!');
    }
}
