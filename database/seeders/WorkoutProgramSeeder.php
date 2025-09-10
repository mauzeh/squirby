<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkoutProgram;
use App\Models\Exercise;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class WorkoutProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();

        if (!$adminUser) {
            return;
        }

        // Day 1 (Sept 15) - Heavy Squat & Bench
        $day1Program = WorkoutProgram::create([
            'user_id' => $adminUser->id,
            'date' => Carbon::parse('2025-09-15'),
            'name' => 'Day 1 - Heavy Squat & Bench',
            'notes' => 'High-frequency squatting program - Heavy day'
        ]);

        $this->attachExercisesToProgram($day1Program, [
            ['title' => 'Back Squat', 'sets' => 3, 'reps' => 5, 'notes' => 'heavy', 'type' => 'main', 'order' => 1],
            ['title' => 'Bench Press', 'sets' => 3, 'reps' => 5, 'notes' => '', 'type' => 'main', 'order' => 2],
            ['title' => 'Zombie Squats', 'sets' => 3, 'reps' => 10, 'notes' => '8-12 reps', 'type' => 'accessory', 'order' => 3],
            ['title' => 'Pendlay Rows', 'sets' => 3, 'reps' => 8, 'notes' => '', 'type' => 'accessory', 'order' => 4],
            ['title' => 'Romanian Deadlifts', 'sets' => 3, 'reps' => 9, 'notes' => '8-10 reps', 'type' => 'accessory', 'order' => 5],
            ['title' => 'Plank', 'sets' => 3, 'reps' => 1, 'notes' => '45-60s holds', 'type' => 'accessory', 'order' => 6],
        ]);

        // Day 2 (Sept 16) - Light Squat & Overhead Press
        $day2Program = WorkoutProgram::create([
            'user_id' => $adminUser->id,
            'date' => Carbon::parse('2025-09-16'),
            'name' => 'Day 2 - Light Squat & Overhead Press',
            'notes' => 'High-frequency squatting program - Light/Speed day'
        ]);

        $this->attachExercisesToProgram($day2Program, [
            ['title' => 'Back Squat', 'sets' => 2, 'reps' => 5, 'notes' => '75-80% of Day 1 weight', 'type' => 'main', 'order' => 1],
            ['title' => 'Overhead Press', 'sets' => 3, 'reps' => 5, 'notes' => '', 'type' => 'main', 'order' => 2],
            ['title' => 'Zombie Squats', 'sets' => 3, 'reps' => 10, 'notes' => '8-12 reps', 'type' => 'accessory', 'order' => 3],
            ['title' => 'Lat Pulldowns', 'sets' => 3, 'reps' => 10, 'notes' => '8-12 reps', 'type' => 'accessory', 'order' => 4],
            ['title' => 'Dumbbell Incline Press', 'sets' => 3, 'reps' => 10, 'notes' => '8-12 reps', 'type' => 'accessory', 'order' => 5],
            ['title' => 'Face Pulls', 'sets' => 3, 'reps' => 18, 'notes' => '15-20 reps', 'type' => 'accessory', 'order' => 6],
            ['title' => 'Bicep Curls', 'sets' => 3, 'reps' => 11, 'notes' => '10-12 reps', 'type' => 'accessory', 'order' => 7],
        ]);

        // Day 3 (Sept 17) - Volume Squat & Deadlift
        $day3Program = WorkoutProgram::create([
            'user_id' => $adminUser->id,
            'date' => Carbon::parse('2025-09-17'),
            'name' => 'Day 3 - Volume Squat & Deadlift',
            'notes' => 'High-frequency squatting program - Volume day'
        ]);

        $this->attachExercisesToProgram($day3Program, [
            ['title' => 'Back Squat', 'sets' => 5, 'reps' => 5, 'notes' => '85-90% of Day 1 weight', 'type' => 'main', 'order' => 1],
            ['title' => 'Conventional Deadlift', 'sets' => 1, 'reps' => 5, 'notes' => '', 'type' => 'main', 'order' => 2],
            ['title' => 'Zombie Squats', 'sets' => 3, 'reps' => 10, 'notes' => '8-12 reps', 'type' => 'accessory', 'order' => 3],
            ['title' => 'Glute-Ham Raises', 'sets' => 3, 'reps' => 13, 'notes' => '10-15 reps', 'type' => 'accessory', 'order' => 4],
            ['title' => 'Dumbbell Rows', 'sets' => 3, 'reps' => 11, 'notes' => '10-12 reps', 'type' => 'accessory', 'order' => 5],
            ['title' => 'Hanging Leg Raises', 'sets' => 3, 'reps' => 1, 'notes' => 'to failure', 'type' => 'accessory', 'order' => 6],
        ]);
    }

    /**
     * Attach exercises to a workout program with the specified parameters
     */
    private function attachExercisesToProgram(WorkoutProgram $program, array $exerciseData): void
    {
        foreach ($exerciseData as $data) {
            $exercise = Exercise::where('title', $data['title'])
                ->where('user_id', $program->user_id)
                ->first();

            if ($exercise) {
                $program->exercises()->attach($exercise->id, [
                    'sets' => $data['sets'],
                    'reps' => $data['reps'],
                    'notes' => $data['notes'],
                    'exercise_order' => $data['order'],
                    'exercise_type' => $data['type']
                ]);
            }
        }
    }
}