<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Services\MobileEntry\ExerciseSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiftLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseSelectionService $exerciseSelectionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required data
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        // Create the service
        $this->exerciseSelectionService = app(ExerciseSelectionService::class);
    }

    public function test_getCommonExercisesForNewUsers_returns_empty_array_when_no_exercises_available()
    {
        $exercises = collect([]);
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        $this->assertEmpty($result);
    }

    public function test_getCommonExercisesForNewUsers_returns_top_exercises_by_log_count()
    {
        // Create exercises
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2']);
        $exercise3 = Exercise::factory()->create(['title' => 'Exercise 3']);
        
        $exercises = collect([$exercise1, $exercise2, $exercise3]);
        
        // Create non-admin users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create logs with different frequencies
        // Exercise 2 should be most popular (15 logs)
        LiftLog::factory()->count(10)->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise2->id,
        ]);
        LiftLog::factory()->count(5)->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise2->id,
        ]);
        
        // Exercise 1 should be second (8 logs)
        LiftLog::factory()->count(8)->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise1->id,
        ]);
        
        // Exercise 3 should be third (3 logs)
        LiftLog::factory()->count(3)->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise3->id,
        ]);
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        // Should return exercises in order of popularity
        $this->assertEquals(1, $result[$exercise2->id]); // Most popular
        $this->assertEquals(2, $result[$exercise1->id]); // Second most popular
        $this->assertEquals(3, $result[$exercise3->id]); // Third most popular
    }

    public function test_getCommonExercisesForNewUsers_excludes_admin_logs()
    {
        // Create exercises
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2']);
        
        $exercises = collect([$exercise1, $exercise2]);
        
        // Create admin and regular users
        $adminUser = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);
        
        $regularUser = User::factory()->create();
        
        // Admin logs exercise 1 heavily (should be ignored)
        LiftLog::factory()->count(50)->create([
            'user_id' => $adminUser->id,
            'exercise_id' => $exercise1->id,
        ]);
        
        // Regular user logs exercise 2 less frequently (should be prioritized)
        LiftLog::factory()->count(5)->create([
            'user_id' => $regularUser->id,
            'exercise_id' => $exercise2->id,
        ]);
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        // Exercise 2 should be prioritized despite having fewer total logs
        // because admin logs are excluded
        $this->assertEquals(1, $result[$exercise2->id]);
        $this->assertArrayNotHasKey($exercise1->id, $result);
    }

    public function test_getCommonExercisesForNewUsers_limits_to_top_10()
    {
        // Create 15 exercises
        $exercises = Exercise::factory()->count(15)->create();
        
        // Create regular user
        $regularUser = User::factory()->create();
        
        // Create logs for all exercises with different frequencies
        foreach ($exercises as $index => $exercise) {
            LiftLog::factory()->count(15 - $index)->create([
                'user_id' => $regularUser->id,
                'exercise_id' => $exercise->id,
            ]);
        }
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        // Should return at most 10 exercises
        $this->assertLessThanOrEqual(10, count($result));
        
        // Should return exactly 10 since we have 15 exercises with logs
        $this->assertEquals(10, count($result));
        
        // Should be ordered by priority (1-10)
        $priorities = array_values($result);
        sort($priorities);
        $this->assertEquals(range(1, 10), $priorities);
    }

    public function test_getCommonExercisesForNewUsers_only_includes_available_exercises()
    {
        // Create exercises
        $availableExercise = Exercise::factory()->create(['title' => 'Available Exercise']);
        $unavailableExercise = Exercise::factory()->create(['title' => 'Unavailable Exercise']);
        
        // Only pass available exercise to the method
        $exercises = collect([$availableExercise]);
        
        // Create regular user
        $regularUser = User::factory()->create();
        
        // Create logs for both exercises
        LiftLog::factory()->count(10)->create([
            'user_id' => $regularUser->id,
            'exercise_id' => $availableExercise->id,
        ]);
        
        LiftLog::factory()->count(20)->create([
            'user_id' => $regularUser->id,
            'exercise_id' => $unavailableExercise->id,
        ]);
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        // Should only include the available exercise
        $this->assertArrayHasKey($availableExercise->id, $result);
        $this->assertArrayNotHasKey($unavailableExercise->id, $result);
        $this->assertEquals(1, count($result));
    }

    public function test_getCommonExercisesForNewUsers_handles_exercises_with_no_logs()
    {
        // Create exercises with no logs
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2']);
        
        $exercises = collect([$exercise1, $exercise2]);
        
        $reflection = new \ReflectionClass($this->exerciseSelectionService);
        $method = $reflection->getMethod('getCommonExercisesForNewUsers');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exerciseSelectionService, $exercises);
        
        // Should return empty array when no exercises have logs
        $this->assertEmpty($result);
    }
}