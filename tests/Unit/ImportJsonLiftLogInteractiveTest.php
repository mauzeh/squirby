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

    public function test_create_new_global_exercise_creates_exercise_with_correct_attributes()
    {
        $exerciseData = [
            'exercise' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'is_bodyweight' => true
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewGlobalExercise', [$exerciseData]);
        
        // Verify exercise was created with correct attributes
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'description' => 'Imported from JSON file',
            'is_bodyweight' => true,
            'user_id' => null
        ]);
        
        $this->assertEquals('Custom Exercise', $result->title);
        $this->assertEquals('custom_exercise', $result->canonical_name);
        $this->assertEquals('Imported from JSON file', $result->description);
        $this->assertTrue($result->is_bodyweight);
        $this->assertNull($result->user_id);
    }

    public function test_create_new_global_exercise_defaults_is_bodyweight_to_false()
    {
        $exerciseData = [
            'exercise' => 'Weighted Exercise',
            'canonical_name' => 'weighted_exercise'
            // Note: is_bodyweight not provided
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewGlobalExercise', [$exerciseData]);
        
        // Verify is_bodyweight defaults to false
        $this->assertDatabaseHas('exercises', [
            'title' => 'Weighted Exercise',
            'canonical_name' => 'weighted_exercise',
            'is_bodyweight' => false,
            'user_id' => null
        ]);
        
        $this->assertFalse($result->is_bodyweight);
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
        $this->assertEquals($existingExercise->id, $result->id);
        $this->assertEquals('Existing Exercise', $result->title);
        $this->assertEquals('existing_exercise', $result->canonical_name);
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

    public function test_create_new_global_exercise_sets_user_id_to_null()
    {
        $exerciseData = [
            'exercise' => 'Global Exercise',
            'canonical_name' => 'global_exercise',
            'is_bodyweight' => false
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewGlobalExercise', [$exerciseData]);
        
        // Verify it's created as a global exercise (user_id = null)
        $this->assertNull($result->user_id);
        $this->assertDatabaseHas('exercises', [
            'canonical_name' => 'global_exercise',
            'user_id' => null
        ]);
    }

    public function test_create_new_global_exercise_uses_provided_exercise_title()
    {
        $exerciseData = [
            'exercise' => 'Custom Exercise Title',
            'canonical_name' => 'custom_canonical_name',
            'is_bodyweight' => true
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'createNewGlobalExercise', [$exerciseData]);
        
        // Verify it uses the exercise title from the data
        $this->assertEquals('Custom Exercise Title', $result->title);
        // The canonical name should be preserved as provided (not auto-generated from title)
        $this->assertEquals('custom_canonical_name', $result->canonical_name);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise Title',
            'canonical_name' => 'custom_canonical_name'
        ]);
    }
}