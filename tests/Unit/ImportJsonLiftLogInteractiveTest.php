<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\ImportJsonLiftLog;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportJsonLiftLogInteractiveTest extends TestCase
{
    use RefreshDatabase;

    private function callPrivateMethod($object, $method, $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    private function createGlobalExercise(string $title, string $canonicalName): Exercise
    {
        return Exercise::create([
            'title' => $title,
            'canonical_name' => $canonicalName,
            'description' => 'Test exercise',
            'is_bodyweight' => false,
            'user_id' => null // Global exercise
        ]);
    }

    public function test_create_new_user_exercise_creates_exercise_with_correct_attributes()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'is_bodyweight' => true
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewUserExercise', [$exerciseData, $user]);
        
        // Verify exercise was created with correct attributes
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'description' => 'Imported from JSON file',
            'is_bodyweight' => true,
            'user_id' => $user->id
        ]);
        
        $this->assertEquals('Custom Exercise', $result->title);
        $this->assertEquals('custom_exercise', $result->canonical_name);
        $this->assertEquals('Imported from JSON file', $result->description);
        $this->assertTrue($result->is_bodyweight);
        $this->assertEquals($user->id, $result->user_id);
    }

    public function test_create_new_user_exercise_defaults_is_bodyweight_to_false()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'Weighted Exercise',
            'canonical_name' => 'weighted_exercise'
            // Note: is_bodyweight not provided
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewUserExercise', [$exerciseData, $user]);
        
        // Verify is_bodyweight defaults to false and it's a user exercise
        $this->assertDatabaseHas('exercises', [
            'title' => 'Weighted Exercise',
            'canonical_name' => 'weighted_exercise',
            'is_bodyweight' => false,
            'user_id' => $user->id
        ]);
        
        $this->assertFalse($result->is_bodyweight);
        $this->assertEquals($user->id, $result->user_id);
    }

    public function test_find_or_create_exercise_finds_existing_global_exercise_by_canonical_name()
    {
        $user = User::factory()->create();
        $existingExercise = $this->createGlobalExercise('Existing Exercise', 'existing_exercise');
        
        $exerciseData = [
            'exercise' => 'Existing Exercise',
            'canonical_name' => 'existing_exercise',
            'is_bodyweight' => false
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'findOrCreateExercise', [$exerciseData, $user]);
        
        // Should return the existing exercise
        $this->assertEquals($existingExercise->id, $result['exercise']->id);
        $this->assertEquals('Existing Exercise', $result['exercise']->title);
        $this->assertEquals('existing_exercise', $result['exercise']->canonical_name);
    }

    public function test_exercise_lookup_only_searches_global_exercises()
    {
        $user = User::factory()->create();
        
        // Create a user-specific exercise with a specific canonical name
        $userExercise = Exercise::create([
            'title' => 'User Exercise',
            'canonical_name' => 'user_exercise',
            'description' => 'User specific exercise',
            'is_bodyweight' => false,
            'user_id' => $user->id
        ]);
        
        $exerciseData = [
            'exercise' => 'Test Exercise',
            'canonical_name' => 'user_exercise', // Same canonical name as user exercise
            'is_bodyweight' => false
        ];
        
        $command = new ImportJsonLiftLog();
        
        // Since there's no global exercise with this canonical name,
        // and the method only looks at global exercises,
        // it should not find the user-specific exercise
        
        $globalExercise = Exercise::global()->where('canonical_name', 'user_exercise')->first();
        $this->assertNull($globalExercise);
        
        // Verify the user exercise exists but won't be found by global lookup
        $this->assertDatabaseHas('exercises', [
            'canonical_name' => 'user_exercise',
            'user_id' => $user->id
        ]);
    }

    public function test_global_exercise_scope_only_returns_global_exercises()
    {
        $user = User::factory()->create();
        
        // Create global exercise
        $globalExercise = $this->createGlobalExercise('Global Exercise', 'global_exercise');
        
        // Create user-specific exercise
        $userExercise = Exercise::create([
            'title' => 'User Exercise',
            'canonical_name' => 'user_exercise',
            'description' => 'User specific exercise',
            'is_bodyweight' => false,
            'user_id' => $user->id
        ]);
        
        // Test global scope
        $globalExercises = Exercise::global()->get();
        
        $this->assertCount(1, $globalExercises);
        $this->assertEquals($globalExercise->id, $globalExercises->first()->id);
        
        // Verify user exercise is not included
        $globalCanonicalNames = $globalExercises->pluck('canonical_name')->toArray();
        $this->assertContains('global_exercise', $globalCanonicalNames);
        $this->assertNotContains('user_exercise', $globalCanonicalNames);
    }

    public function test_create_new_user_exercise_sets_user_id_correctly()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'User Exercise',
            'canonical_name' => 'user_exercise',
            'is_bodyweight' => false
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewUserExercise', [$exerciseData, $user]);
        
        // Verify it's created as a user-specific exercise
        $this->assertEquals($user->id, $result->user_id);
        $this->assertDatabaseHas('exercises', [
            'canonical_name' => 'user_exercise',
            'user_id' => $user->id
        ]);
    }

    public function test_create_new_user_exercise_uses_provided_exercise_title()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'Custom Exercise Title',
            'canonical_name' => 'custom_canonical_name',
            'is_bodyweight' => true
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewUserExercise', [$exerciseData, $user]);
        
        // Verify it uses the exercise title from the data
        $this->assertEquals('Custom Exercise Title', $result->title);
        // The canonical name should be preserved as provided (not auto-generated from title)
        $this->assertEquals('custom_canonical_name', $result->canonical_name);
        $this->assertEquals($user->id, $result->user_id);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise Title',
            'canonical_name' => 'custom_canonical_name',
            'user_id' => $user->id
        ]);
    }

    public function test_find_or_create_exercise_with_create_exercises_flag_automatically_creates_user_exercise()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'is_bodyweight' => false
        ];
        
        // Mock the command with --create-exercises flag
        $command = $this->getMockBuilder(ImportJsonLiftLog::class)
            ->onlyMethods(['option', 'line'])
            ->getMock();
        
        $command->expects($this->once())
            ->method('option')
            ->with('create-exercises')
            ->willReturn(true);
        
        $command->expects($this->once())
            ->method('line')
            ->with("⚠ Exercise 'New Exercise' not found. Creating user-specific exercise...");
        
        $result = $this->callPrivateMethod($command, 'findOrCreateExercise', [$exerciseData, $user]);
        
        // Verify it created a user-specific exercise
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'is_bodyweight' => false,
            'user_id' => $user->id
        ]);
        
        $this->assertEquals('New Exercise', $result['exercise']->title);
        $this->assertEquals('new_exercise', $result['exercise']->canonical_name);
        $this->assertEquals($user->id, $result['exercise']->user_id);
    }
}