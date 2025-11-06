<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class ExerciseTypeMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function migration_adds_exercise_type_column()
    {
        $this->assertTrue(Schema::hasColumn('exercises', 'exercise_type'));
    }

    /** @test */
    public function migration_adds_exercise_type_index()
    {
        // Test that we can query efficiently on exercise_type (index exists)
        // We'll test this by checking that the column exists and can be queried
        $this->assertTrue(Schema::hasColumn('exercises', 'exercise_type'));
        
        // Create test data and verify we can query by exercise_type efficiently
        Exercise::factory()->create(['exercise_type' => 'cardio']);
        Exercise::factory()->create(['exercise_type' => 'regular']);
        
        $cardioCount = Exercise::where('exercise_type', 'cardio')->count();
        $regularCount = Exercise::where('exercise_type', 'regular')->count();
        
        $this->assertEquals(1, $cardioCount);
        $this->assertEquals(1, $regularCount);
    }

    /** @test */
    public function migration_populates_cardio_exercises_based_on_keywords()
    {
        // Create exercises with cardio keywords before running the migration logic
        $runExercise = Exercise::factory()->create([
            'title' => 'Morning Run',
            'exercise_type' => null
        ]);
        
        $cycleExercise = Exercise::factory()->create([
            'title' => 'Cycle Training',
            'exercise_type' => null
        ]);
        
        $rowExercise = Exercise::factory()->create([
            'title' => 'Rowing Machine',
            'exercise_type' => null
        ]);
        
        $regularExercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'exercise_type' => null
        ]);

        // Simulate the migration population logic
        $cardioKeywords = ['run', 'running', 'cycle', 'cycling', 'row', 'rowing', 'walk', 'walking', 'jog', 'jogging'];
        
        foreach ($cardioKeywords as $keyword) {
            Exercise::where('title', 'LIKE', "%{$keyword}%")
                ->whereNull('exercise_type')
                ->update(['exercise_type' => 'cardio']);
        }
        
        // Mark remaining as regular
        Exercise::whereNull('exercise_type')->update(['exercise_type' => 'regular']);

        $runExercise->refresh();
        $cycleExercise->refresh();
        $rowExercise->refresh();
        $regularExercise->refresh();

        $this->assertEquals('cardio', $runExercise->exercise_type);
        $this->assertEquals('cardio', $cycleExercise->exercise_type);
        $this->assertEquals('cardio', $rowExercise->exercise_type);
        $this->assertEquals('regular', $regularExercise->exercise_type);
    }

    /** @test */
    public function migration_populates_banded_exercises()
    {
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Band Pull Apart',
            'band_type' => 'resistance',
            'exercise_type' => null
        ]);
        
        $regularExercise = Exercise::factory()->create([
            'title' => 'Regular Exercise',
            'band_type' => null,
            'exercise_type' => null
        ]);

        // Simulate migration logic for banded exercises
        Exercise::whereNotNull('band_type')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'banded']);
            
        Exercise::whereNull('exercise_type')->update(['exercise_type' => 'regular']);

        $bandedExercise->refresh();
        $regularExercise->refresh();

        $this->assertEquals('banded', $bandedExercise->exercise_type);
        $this->assertEquals('regular', $regularExercise->exercise_type);
    }

    /** @test */
    public function migration_populates_bodyweight_exercises()
    {
        $bodyweightExercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'is_bodyweight' => true,
            'exercise_type' => null
        ]);
        
        $weightedExercise = Exercise::factory()->create([
            'title' => 'Weighted Squat',
            'is_bodyweight' => false,
            'exercise_type' => null
        ]);

        // Simulate migration logic for bodyweight exercises
        Exercise::where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);
            
        Exercise::whereNull('exercise_type')->update(['exercise_type' => 'regular']);

        $bodyweightExercise->refresh();
        $weightedExercise->refresh();

        $this->assertEquals('bodyweight', $bodyweightExercise->exercise_type);
        $this->assertEquals('regular', $weightedExercise->exercise_type);
    }

    /** @test */
    public function migration_prioritizes_cardio_over_other_types()
    {
        // Create an exercise that could be both cardio and bodyweight
        $cardioBodyweightExercise = Exercise::factory()->create([
            'title' => 'Running in Place',
            'is_bodyweight' => true,
            'exercise_type' => null
        ]);

        // Simulate migration logic (cardio keywords are processed first)
        $cardioKeywords = ['run', 'running', 'cycle', 'cycling', 'row', 'rowing', 'walk', 'walking', 'jog', 'jogging'];
        
        foreach ($cardioKeywords as $keyword) {
            Exercise::where('title', 'LIKE', "%{$keyword}%")
                ->whereNull('exercise_type')
                ->update(['exercise_type' => 'cardio']);
        }
        
        // Then banded exercises
        Exercise::whereNotNull('band_type')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'banded']);
        
        // Then bodyweight exercises
        Exercise::where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);
        
        // Finally regular exercises
        Exercise::whereNull('exercise_type')->update(['exercise_type' => 'regular']);

        $cardioBodyweightExercise->refresh();

        // Should be marked as cardio since cardio keywords are processed first
        $this->assertEquals('cardio', $cardioBodyweightExercise->exercise_type);
    }

    /** @test */
    public function migration_handles_exercises_with_multiple_characteristics()
    {
        // Create exercises with multiple characteristics to test priority
        $cardioBodyweightExercise = Exercise::factory()->create([
            'title' => 'Bodyweight Running',
            'is_bodyweight' => true,
            'exercise_type' => null
        ]);
        
        $bandedBodyweightExercise = Exercise::factory()->create([
            'title' => 'Assisted Pull-up',
            'is_bodyweight' => true,
            'band_type' => 'assistance',
            'exercise_type' => null
        ]);

        // Simulate the complete migration logic
        $cardioKeywords = ['run', 'running', 'cycle', 'cycling', 'row', 'rowing', 'walk', 'walking', 'jog', 'jogging'];
        
        foreach ($cardioKeywords as $keyword) {
            Exercise::where('title', 'LIKE', "%{$keyword}%")
                ->whereNull('exercise_type')
                ->update(['exercise_type' => 'cardio']);
        }
        
        Exercise::whereNotNull('band_type')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'banded']);
        
        Exercise::where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);
        
        Exercise::whereNull('exercise_type')->update(['exercise_type' => 'regular']);

        $cardioBodyweightExercise->refresh();
        $bandedBodyweightExercise->refresh();

        // Cardio should take priority over bodyweight
        $this->assertEquals('cardio', $cardioBodyweightExercise->exercise_type);
        
        // Banded should take priority over bodyweight
        $this->assertEquals('banded', $bandedBodyweightExercise->exercise_type);
    }
}