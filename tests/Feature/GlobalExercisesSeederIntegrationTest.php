<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GlobalExercisesSeederIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_includes_global_exercises()
    {
        // Run the full database seeder
        $this->seed(DatabaseSeeder::class);

        // Verify global exercises were created
        $globalExercises = Exercise::global()->get();
        $this->assertGreaterThan(0, $globalExercises->count(), 'Global exercises should be created by database seeder');

        // Verify specific exercises exist
        $expectedExercises = [
            'Back Squat',
            'Bench Press', 
            'Deadlift',
            'Strict Press',
            'Power Clean'
        ];

        foreach ($expectedExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->whereNull('user_id')->first();
            $this->assertNotNull($exercise, "Global exercise '{$exerciseTitle}' should exist after database seeding");
        }
    }

    public function test_new_users_can_access_global_exercises()
    {
        // Run the database seeder to create global exercises
        $this->seed(DatabaseSeeder::class);

        // Create a new user
        $user = User::factory()->create();

        // Verify the user can access global exercises
        $availableExercises = Exercise::availableToUser($user->id)->get();
        $globalExercises = Exercise::global()->get();

        $this->assertGreaterThan(0, $availableExercises->count(), 'User should have access to exercises');
        $this->assertEquals(
            $globalExercises->count(), 
            $availableExercises->count(), 
            'New user should have access to all global exercises'
        );

        // Verify all available exercises are global for a new user
        foreach ($availableExercises as $exercise) {
            $this->assertTrue($exercise->isGlobal(), "Exercise '{$exercise->title}' should be global");
        }
    }

    public function test_users_no_longer_get_duplicate_exercises_on_creation()
    {
        // Run the database seeder to create global exercises
        $this->seed(DatabaseSeeder::class);

        $initialExerciseCount = Exercise::count();

        // Create a new user
        $user = User::factory()->create();

        // Verify no new exercises were created for the user
        $finalExerciseCount = Exercise::count();
        $this->assertEquals(
            $initialExerciseCount, 
            $finalExerciseCount, 
            'Creating a new user should not create additional exercises'
        );

        // Verify the user has no personal exercises
        $userExercises = Exercise::userSpecific($user->id)->get();
        $this->assertEquals(0, $userExercises->count(), 'New user should not have personal exercises');
    }
}