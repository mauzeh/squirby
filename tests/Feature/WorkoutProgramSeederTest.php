<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutProgram;
use Database\Seeders\WorkoutProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class WorkoutProgramSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_program_seeder_creates_high_frequency_program_data()
    {
        // Create admin user first
        $admin = User::factory()->create([
            'email' => 'admin@example.com'
        ]);

        // Run the seeder
        $seeder = new WorkoutProgramSeeder();
        $seeder->run();

        // Verify three programs were created
        $this->assertEquals(3, WorkoutProgram::count());

        // Verify programs are for the correct dates
        $this->assertTrue(WorkoutProgram::forDate('2025-09-15')->exists());
        $this->assertTrue(WorkoutProgram::forDate('2025-09-16')->exists());
        $this->assertTrue(WorkoutProgram::forDate('2025-09-17')->exists());

        // Verify Day 1 program structure
        $day1Program = WorkoutProgram::forDate('2025-09-15')->first();
        $this->assertEquals('Day 1 - Heavy Squat & Bench', $day1Program->name);
        $this->assertEquals(6, $day1Program->exercises()->count());
        $this->assertEquals(2, $day1Program->exercises()->wherePivot('exercise_type', 'main')->count());
        $this->assertEquals(4, $day1Program->exercises()->wherePivot('exercise_type', 'accessory')->count());

        // Verify Day 2 program structure
        $day2Program = WorkoutProgram::forDate('2025-09-16')->first();
        $this->assertEquals('Day 2 - Light Squat & Overhead Press', $day2Program->name);
        $this->assertEquals(7, $day2Program->exercises()->count());
        $this->assertEquals(2, $day2Program->exercises()->wherePivot('exercise_type', 'main')->count());
        $this->assertEquals(5, $day2Program->exercises()->wherePivot('exercise_type', 'accessory')->count());

        // Verify Day 3 program structure
        $day3Program = WorkoutProgram::forDate('2025-09-17')->first();
        $this->assertEquals('Day 3 - Volume Squat & Deadlift', $day3Program->name);
        $this->assertEquals(6, $day3Program->exercises()->count());
        $this->assertEquals(2, $day3Program->exercises()->wherePivot('exercise_type', 'main')->count());
        $this->assertEquals(4, $day3Program->exercises()->wherePivot('exercise_type', 'accessory')->count());

        // Verify specific exercise configurations
        $backSquatDay1 = $day1Program->exercises()->where('title', 'Back Squat')->first();
        $this->assertEquals(3, $backSquatDay1->pivot->sets);
        $this->assertEquals(5, $backSquatDay1->pivot->reps);
        $this->assertEquals('heavy', $backSquatDay1->pivot->notes);
        $this->assertEquals('main', $backSquatDay1->pivot->exercise_type);

        $backSquatDay2 = $day2Program->exercises()->where('title', 'Back Squat')->first();
        $this->assertEquals(2, $backSquatDay2->pivot->sets);
        $this->assertEquals(5, $backSquatDay2->pivot->reps);
        $this->assertEquals('75-80% of Day 1 weight', $backSquatDay2->pivot->notes);

        $backSquatDay3 = $day3Program->exercises()->where('title', 'Back Squat')->first();
        $this->assertEquals(5, $backSquatDay3->pivot->sets);
        $this->assertEquals(5, $backSquatDay3->pivot->reps);
        $this->assertEquals('85-90% of Day 1 weight', $backSquatDay3->pivot->notes);
    }

    public function test_seeder_handles_missing_admin_user_gracefully()
    {
        // Don't create admin user
        $seeder = new WorkoutProgramSeeder();
        $seeder->run();

        // Should not create any programs
        $this->assertEquals(0, WorkoutProgram::count());
    }
}