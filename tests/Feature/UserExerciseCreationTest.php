<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserExerciseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_all_required_exercises()
    {
        // Create a new user
        $user = User::factory()->create();

        // Expected exercises including the new ones for workout programs
        $expectedExercises = [
            'Back Squat',
            'Bench Press',
            'Deadlift',
            'Strict Press',
            'Power Clean',
            'Half-Kneeling DB Press',
            'Cyclist Squat (Barbell, Front Rack)',
            'Chin-Ups',
            'Zombie Squats',
            'Pendlay Rows',
            'Romanian Deadlifts',
            'Plank',
            'Overhead Press',
            'Lat Pulldowns',
            'Dumbbell Incline Press',
            'Face Pulls',
            'Bicep Curls',
            'Conventional Deadlift',
            'Glute-Ham Raises',
            'Dumbbell Rows',
            'Hanging Leg Raises',
        ];

        // Verify all expected exercises were created
        $userExercises = $user->exercises()->pluck('title')->toArray();
        
        foreach ($expectedExercises as $exerciseTitle) {
            $this->assertContains($exerciseTitle, $userExercises, "Exercise '{$exerciseTitle}' was not created for new user");
        }

        // Verify the total count matches
        $this->assertCount(count($expectedExercises), $userExercises, 'Total number of exercises does not match expected count');
    }

    public function test_new_user_gets_bodyweight_exercises_marked_correctly()
    {
        // Create a new user
        $user = User::factory()->create();

        // Expected bodyweight exercises
        $expectedBodyweightExercises = [
            'Chin-Ups',
            'Plank',
            'Glute-Ham Raises',
            'Hanging Leg Raises',
        ];

        foreach ($expectedBodyweightExercises as $exerciseTitle) {
            $exercise = $user->exercises()->where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Bodyweight exercise '{$exerciseTitle}' was not found");
            $this->assertTrue($exercise->is_bodyweight, "Exercise '{$exerciseTitle}' should be marked as bodyweight");
        }
    }

    public function test_user_has_workout_programs_relationship()
    {
        // Create a new user
        $user = User::factory()->create();

        // Test that the workoutPrograms relationship exists and returns a collection
        $workoutPrograms = $user->workoutPrograms;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $workoutPrograms);
        $this->assertCount(0, $workoutPrograms); // Should be empty for new user
    }

    public function test_workout_program_exercises_are_available_for_programs()
    {
        // Create a new user
        $user = User::factory()->create();

        // Verify that key exercises needed for the high-frequency program are available
        $programExercises = [
            'Back Squat',
            'Bench Press',
            'Overhead Press',
            'Conventional Deadlift',
            'Zombie Squats',
            'Pendlay Rows',
            'Romanian Deadlifts',
            'Plank',
            'Lat Pulldowns',
            'Dumbbell Incline Press',
            'Face Pulls',
            'Bicep Curls',
            'Glute-Ham Raises',
            'Dumbbell Rows',
            'Hanging Leg Raises',
        ];

        foreach ($programExercises as $exerciseTitle) {
            $exercise = $user->exercises()->where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Program exercise '{$exerciseTitle}' was not created for new user");
            $this->assertNotEmpty($exercise->description, "Exercise '{$exerciseTitle}' should have a description");
        }
    }
}