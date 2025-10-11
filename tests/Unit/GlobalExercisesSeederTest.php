<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Database\Seeders\GlobalExercisesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GlobalExercisesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_global_exercises()
    {
        // Ensure no exercises exist initially
        $this->assertEquals(0, Exercise::count());

        // Run the seeder
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();

        // Verify exercises were created
        $this->assertGreaterThan(0, Exercise::count());

        // Verify all created exercises are global (user_id is null)
        $globalExercises = Exercise::global()->get();
        $this->assertEquals(Exercise::count(), $globalExercises->count());

        // Verify specific exercises exist
        $expectedExercises = [
            'Back Squat',
            'Bench Press', 
            'Deadlift',
            'Strict Press',
            'Power Clean',
            'Half-Kneeling DB Press',
            'Cyclist Squat (Barbell, Front Rack)',
            'Chin-Ups',
            'Pull-Ups'
        ];

        foreach ($expectedExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Exercise '{$exerciseTitle}' should exist");
            $this->assertTrue($exercise->isGlobal(), "Exercise '{$exerciseTitle}' should be global");
        }
    }

    public function test_seeder_sets_bodyweight_exercises_correctly()
    {
        // Run the seeder
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();

        // Verify bodyweight exercises are marked correctly
        $bodyweightExercises = ['Chin-Ups', 'Pull-Ups'];
        
        foreach ($bodyweightExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise);
            $this->assertTrue($exercise->is_bodyweight, "Exercise '{$exerciseTitle}' should be marked as bodyweight");
        }

        // Verify non-bodyweight exercises are not marked as bodyweight
        $nonBodyweightExercises = ['Back Squat', 'Bench Press', 'Deadlift'];
        
        foreach ($nonBodyweightExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise);
            $this->assertFalse($exercise->is_bodyweight, "Exercise '{$exerciseTitle}' should not be marked as bodyweight");
        }
    }

    public function test_seeder_does_not_create_duplicates()
    {
        // Run the seeder twice
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();
        $initialCount = Exercise::count();
        
        $seeder->run();
        $finalCount = Exercise::count();

        // Verify no duplicates were created
        $this->assertEquals($initialCount, $finalCount, 'Seeder should not create duplicate exercises');

        // Verify each exercise title appears only once
        $exerciseTitles = Exercise::pluck('title')->toArray();
        $uniqueTitles = array_unique($exerciseTitles);
        
        $this->assertEquals(
            count($exerciseTitles), 
            count($uniqueTitles), 
            'All exercise titles should be unique'
        );
    }

    public function test_seeder_creates_exercises_with_descriptions()
    {
        // Run the seeder
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();

        // Verify all exercises have descriptions
        $exercises = Exercise::all();
        
        foreach ($exercises as $exercise) {
            $this->assertNotEmpty($exercise->description, "Exercise '{$exercise->title}' should have a description");
        }
    }
}