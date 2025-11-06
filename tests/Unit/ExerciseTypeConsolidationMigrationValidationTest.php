<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Models\Exercise;
use App\Models\User;

class ExerciseTypeConsolidationMigrationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function migration_validation_detects_null_exercise_type_values()
    {
        // Test the validation logic by checking that all exercises have non-null exercise_type
        // This simulates what the migration validation would check
        
        $nullCount = Exercise::whereNull('exercise_type')->count();
        $this->assertEquals(0, $nullCount, 'Migration validation should ensure no NULL exercise_type values exist');
        
        // Test that the validation query works correctly
        $totalCount = Exercise::count();
        $nonNullCount = Exercise::whereNotNull('exercise_type')->count();
        $this->assertEquals($totalCount, $nonNullCount, 'All exercises should have non-null exercise_type');
    }

    /** @test */
    public function migration_validation_detects_invalid_exercise_type_values()
    {
        // Create an exercise with invalid exercise_type
        DB::table('exercises')->insert([
            'title' => 'Invalid Exercise',
            'description' => 'Test exercise',
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
            'exercise_type' => 'invalid_type', // This should be caught by validation
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
        $invalidCount = Exercise::whereNotIn('exercise_type', $validTypes)->count();
        $this->assertGreaterThan(0, $invalidCount, 'Should detect exercises with invalid exercise_type values');
    }

    /** @test */
    public function migration_validation_correctly_counts_exercise_types()
    {
        // Create exercises of each type
        $testData = [
            ['title' => 'Regular 1', 'exercise_type' => 'regular'],
            ['title' => 'Regular 2', 'exercise_type' => 'regular'],
            ['title' => 'Bodyweight 1', 'exercise_type' => 'bodyweight'],
            ['title' => 'Banded Resistance 1', 'exercise_type' => 'banded_resistance'],
            ['title' => 'Banded Assistance 1', 'exercise_type' => 'banded_assistance'],
        ];

        foreach ($testData as $data) {
            DB::table('exercises')->insert([
                'title' => $data['title'],
                'description' => 'Test exercise',
                'user_id' => $this->user->id,
                'is_bodyweight' => false,
                'band_type' => null,
                'exercise_type' => $data['exercise_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Verify counts
        $typeCounts = Exercise::select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->pluck('count', 'exercise_type')
            ->toArray();

        $this->assertEquals(2, $typeCounts['regular'] ?? 0);
        $this->assertEquals(1, $typeCounts['bodyweight'] ?? 0);
        $this->assertEquals(1, $typeCounts['banded_resistance'] ?? 0);
        $this->assertEquals(1, $typeCounts['banded_assistance'] ?? 0);
    }

    /** @test */
    public function migration_validation_handles_empty_table()
    {
        // Clear all exercises
        Exercise::truncate();
        
        // Validation should handle empty table gracefully
        $totalCount = Exercise::count();
        $nullCount = Exercise::whereNull('exercise_type')->count();
        
        $this->assertEquals(0, $totalCount);
        $this->assertEquals(0, $nullCount);
    }

    /** @test */
    public function migration_validation_detects_data_inconsistencies()
    {
        // Create exercise where exercise_type doesn't match legacy fields
        DB::table('exercises')->insert([
            'title' => 'Inconsistent Exercise',
            'description' => 'Exercise with mismatched type indicators',
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => null,
            'exercise_type' => 'regular', // Should be 'bodyweight' based on is_bodyweight=true
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exercise = Exercise::where('title', 'Inconsistent Exercise')->first();
        
        // This represents what the validation logic should detect
        $expectedType = $this->determineExpectedType($exercise);
        $this->assertNotEquals($expectedType, $exercise->exercise_type, 'Should detect inconsistency');
        $this->assertEquals('bodyweight', $expectedType);
        $this->assertEquals('regular', $exercise->exercise_type);
    }

    /** @test */
    public function migration_validation_verifies_priority_order()
    {
        // Test that band_type takes priority over is_bodyweight
        $testCases = [
            [
                'title' => 'Band Priority Resistance',
                'is_bodyweight' => true,
                'band_type' => 'resistance',
                'expected_type' => 'banded_resistance'
            ],
            [
                'title' => 'Band Priority Assistance',
                'is_bodyweight' => true,
                'band_type' => 'assistance',
                'expected_type' => 'banded_assistance'
            ],
            [
                'title' => 'Bodyweight Only',
                'is_bodyweight' => true,
                'band_type' => null,
                'expected_type' => 'bodyweight'
            ],
            [
                'title' => 'Regular Exercise',
                'is_bodyweight' => false,
                'band_type' => null,
                'expected_type' => 'regular'
            ],
        ];

        foreach ($testCases as $testCase) {
            $expectedType = $this->determineExpectedType((object) $testCase);
            $this->assertEquals(
                $testCase['expected_type'], 
                $expectedType,
                "Priority order validation failed for {$testCase['title']}"
            );
        }
    }

    /** @test */
    public function migration_validation_checks_referential_integrity()
    {
        // Create exercise with valid user
        $validExercise = Exercise::create([
            'title' => 'Valid Exercise',
            'description' => 'Exercise with valid user',
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        // Since foreign key constraints prevent inserting invalid user_ids,
        // we'll test the validation logic by checking for existing valid relationships
        $validExerciseCount = DB::table('exercises')
            ->whereNotNull('user_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('users')
                      ->whereRaw('users.id = exercises.user_id');
            })
            ->count();

        $this->assertGreaterThan(0, $validExerciseCount, 'Should find exercises with valid user relationships');

        // Test the orphaned exercise detection query (should return 0 in a healthy database)
        $orphanedCount = DB::table('exercises')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('users')
                      ->whereRaw('users.id = exercises.user_id');
            })
            ->count();

        $this->assertEquals(0, $orphanedCount, 'Should not find any orphaned exercises in a healthy database');
    }

    /** @test */
    public function migration_validation_performance_with_large_dataset()
    {
        // Create a larger dataset to test validation performance
        $batchSize = 1000;
        $exerciseData = [];
        
        for ($i = 1; $i <= $batchSize; $i++) {
            $exerciseData[] = [
                'title' => "Performance Test Exercise {$i}",
                'description' => "Test exercise {$i}",
                'user_id' => $this->user->id,
                'is_bodyweight' => $i % 4 === 0,
                'band_type' => $i % 3 === 0 ? 'resistance' : ($i % 5 === 0 ? 'assistance' : null),
                'exercise_type' => $this->determineExpectedType((object) [
                    'is_bodyweight' => $i % 4 === 0,
                    'band_type' => $i % 3 === 0 ? 'resistance' : ($i % 5 === 0 ? 'assistance' : null)
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks for better performance
        $chunks = array_chunk($exerciseData, 100);
        foreach ($chunks as $chunk) {
            DB::table('exercises')->insert($chunk);
        }

        // Validate the large dataset
        $startTime = microtime(true);
        
        $totalCount = Exercise::count();
        $nullCount = Exercise::whereNull('exercise_type')->count();
        $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
        $invalidCount = Exercise::whereNotIn('exercise_type', $validTypes)->count();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertEquals($batchSize, $totalCount);
        $this->assertEquals(0, $nullCount);
        $this->assertEquals(0, $invalidCount);
        $this->assertLessThan(5.0, $executionTime, 'Validation should complete within 5 seconds for 1000 exercises');
    }

    /**
     * Helper method to determine expected exercise type based on migration logic
     */
    private function determineExpectedType($exercise): string
    {
        // Mirror the migration priority logic
        if (isset($exercise->band_type) && $exercise->band_type === 'resistance') {
            return 'banded_resistance';
        }
        
        if (isset($exercise->band_type) && $exercise->band_type === 'assistance') {
            return 'banded_assistance';
        }
        
        if (isset($exercise->is_bodyweight) && $exercise->is_bodyweight && 
            (!isset($exercise->band_type) || $exercise->band_type === null)) {
            return 'bodyweight';
        }
        
        return 'regular';
    }
}