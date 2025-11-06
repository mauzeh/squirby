<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\Exercise;
use App\Models\User;

class ExerciseTypeConsolidationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function complete_migration_validation_workflow()
    {
        // Step 1: Create comprehensive test data that covers all migration scenarios
        $testScenarios = [
            // Regular exercises
            ['title' => 'Bench Press', 'is_bodyweight' => false, 'band_type' => null, 'expected' => 'regular'],
            ['title' => 'Squat', 'is_bodyweight' => false, 'band_type' => null, 'expected' => 'regular'],
            
            // Bodyweight exercises
            ['title' => 'Push-up', 'is_bodyweight' => true, 'band_type' => null, 'expected' => 'bodyweight'],
            ['title' => 'Pull-up', 'is_bodyweight' => true, 'band_type' => null, 'expected' => 'bodyweight'],
            
            // Banded resistance exercises
            ['title' => 'Banded Squat', 'is_bodyweight' => false, 'band_type' => 'resistance', 'expected' => 'banded_resistance'],
            ['title' => 'Banded Row', 'is_bodyweight' => false, 'band_type' => 'resistance', 'expected' => 'banded_resistance'],
            
            // Banded assistance exercises
            ['title' => 'Assisted Pull-up', 'is_bodyweight' => false, 'band_type' => 'assistance', 'expected' => 'banded_assistance'],
            ['title' => 'Assisted Dip', 'is_bodyweight' => false, 'band_type' => 'assistance', 'expected' => 'banded_assistance'],
            
            // Edge cases - band_type takes priority over is_bodyweight
            ['title' => 'Bodyweight + Resistance Band', 'is_bodyweight' => true, 'band_type' => 'resistance', 'expected' => 'banded_resistance'],
            ['title' => 'Bodyweight + Assistance Band', 'is_bodyweight' => true, 'band_type' => 'assistance', 'expected' => 'banded_assistance'],
        ];

        // Create exercises using the model (which will set exercise_type automatically)
        $createdExercises = [];
        foreach ($testScenarios as $scenario) {
            $exercise = Exercise::create([
                'title' => $scenario['title'],
                'description' => 'Test exercise for migration validation',
                'user_id' => $this->user->id,
                'is_bodyweight' => $scenario['is_bodyweight'],
                'band_type' => $scenario['band_type'],
            ]);
            $createdExercises[] = ['exercise' => $exercise, 'expected' => $scenario['expected']];
        }

        // Step 2: Validate that all exercises have the correct exercise_type
        foreach ($createdExercises as $data) {
            $exercise = $data['exercise'];
            $expectedType = $data['expected'];
            
            $this->assertEquals(
                $expectedType, 
                $exercise->exercise_type,
                "Exercise '{$exercise->title}' should have exercise_type '{$expectedType}'"
            );
        }

        // Step 3: Validate migration requirements are met
        $this->validateMigrationRequirements();

        // Step 4: Validate data integrity
        $this->validateDataIntegrity();

        // Step 5: Validate performance with the dataset
        $this->validateQueryPerformance();
    }

    /** @test */
    public function migration_validation_with_large_realistic_dataset()
    {
        // Create a realistic dataset with proper distribution
        $exerciseTemplates = [
            // Regular exercises (most common)
            ['title' => 'Bench Press', 'is_bodyweight' => false, 'band_type' => null],
            ['title' => 'Squat', 'is_bodyweight' => false, 'band_type' => null],
            ['title' => 'Deadlift', 'is_bodyweight' => false, 'band_type' => null],
            ['title' => 'Overhead Press', 'is_bodyweight' => false, 'band_type' => null],
            ['title' => 'Barbell Row', 'is_bodyweight' => false, 'band_type' => null],
            
            // Bodyweight exercises
            ['title' => 'Push-up', 'is_bodyweight' => true, 'band_type' => null],
            ['title' => 'Pull-up', 'is_bodyweight' => true, 'band_type' => null],
            ['title' => 'Dip', 'is_bodyweight' => true, 'band_type' => null],
            
            // Banded exercises
            ['title' => 'Banded Squat', 'is_bodyweight' => false, 'band_type' => 'resistance'],
            ['title' => 'Assisted Pull-up', 'is_bodyweight' => false, 'band_type' => 'assistance'],
        ];

        // Create multiple variations of each exercise
        $exerciseCount = 0;
        foreach ($exerciseTemplates as $template) {
            for ($i = 1; $i <= 10; $i++) {
                Exercise::create([
                    'title' => $template['title'] . " Variation {$i}",
                    'description' => 'Test exercise variation',
                    'user_id' => $this->user->id,
                    'is_bodyweight' => $template['is_bodyweight'],
                    'band_type' => $template['band_type'],
                ]);
                $exerciseCount++;
            }
        }

        // Validate the large dataset
        $this->assertEquals(100, $exerciseCount, 'Should have created 100 exercises');
        
        // Validate type distribution
        $typeCounts = Exercise::select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->pluck('count', 'exercise_type')
            ->toArray();

        $this->assertEquals(50, $typeCounts['regular'] ?? 0, 'Should have 50 regular exercises');
        $this->assertEquals(30, $typeCounts['bodyweight'] ?? 0, 'Should have 30 bodyweight exercises');
        $this->assertEquals(10, $typeCounts['banded_resistance'] ?? 0, 'Should have 10 banded_resistance exercises');
        $this->assertEquals(10, $typeCounts['banded_assistance'] ?? 0, 'Should have 10 banded_assistance exercises');

        // Validate all requirements are met
        $this->validateMigrationRequirements();
    }

    /**
     * Validate that all migration requirements are satisfied
     */
    private function validateMigrationRequirements(): void
    {
        // Requirement 6.2: Ensure no exercises are left with NULL exercise_type
        $nullCount = Exercise::whereNull('exercise_type')->count();
        $this->assertEquals(0, $nullCount, 'No exercises should have NULL exercise_type');

        // Requirement 6.1: Validate only valid exercise_type values exist
        $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
        $invalidCount = Exercise::whereNotIn('exercise_type', $validTypes)->count();
        $this->assertEquals(0, $invalidCount, 'All exercises should have valid exercise_type values');

        // Requirement 6.3: Verify migration report data is accurate
        $totalCount = Exercise::count();
        $typeCounts = Exercise::select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->pluck('count', 'exercise_type')
            ->toArray();

        $sumOfTypeCounts = array_sum($typeCounts);
        $this->assertEquals($totalCount, $sumOfTypeCounts, 'Type counts should sum to total exercise count');
    }

    /**
     * Validate data integrity after migration
     */
    private function validateDataIntegrity(): void
    {
        // Check that all exercises still have valid user relationships
        $exercisesWithValidUsers = Exercise::whereExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('users')
                  ->whereRaw('users.id = exercises.user_id');
        })->count();

        $totalExercises = Exercise::count();
        $this->assertEquals($totalExercises, $exercisesWithValidUsers, 'All exercises should have valid user relationships');

        // Verify that legacy fields are preserved
        $exercisesWithLegacyData = Exercise::where(function ($query) {
            $query->where('is_bodyweight', true)
                  ->orWhereNotNull('band_type');
        })->get();

        foreach ($exercisesWithLegacyData as $exercise) {
            // Verify exercise_type is consistent with legacy fields
            $expectedType = $this->determineExpectedType($exercise);
            $this->assertEquals(
                $expectedType, 
                $exercise->exercise_type,
                "Exercise '{$exercise->title}' has inconsistent type assignment"
            );
        }
    }

    /**
     * Validate query performance with the new exercise_type field
     */
    private function validateQueryPerformance(): void
    {
        $startTime = microtime(true);

        // Test common queries that would benefit from the exercise_type field
        $regularCount = Exercise::where('exercise_type', 'regular')->count();
        $bodyweightCount = Exercise::where('exercise_type', 'bodyweight')->count();
        $bandedCount = Exercise::whereIn('exercise_type', ['banded_resistance', 'banded_assistance'])->count();
        $allTypeCounts = Exercise::select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->get();

        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;

        // Queries should be fast with the new index
        $this->assertLessThan(1.0, $queryTime, 'Exercise type queries should be fast');
        
        // Verify query results make sense
        $totalFromQueries = $regularCount + $bodyweightCount + $bandedCount;
        $totalActual = Exercise::count();
        $this->assertEquals($totalActual, $totalFromQueries, 'Query counts should match total');
    }

    /**
     * Helper method to determine expected exercise type based on legacy fields
     */
    private function determineExpectedType($exercise): string
    {
        if ($exercise->band_type === 'resistance') {
            return 'banded_resistance';
        }
        
        if ($exercise->band_type === 'assistance') {
            return 'banded_assistance';
        }
        
        if ($exercise->is_bodyweight && $exercise->band_type === null) {
            return 'bodyweight';
        }
        
        return 'regular';
    }
}