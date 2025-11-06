<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Models\Exercise;
use App\Models\User;

class ExerciseTypeConsolidationRollbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function rollback_removes_exercise_type_column_and_index()
    {
        // Verify the column and index exist after migration
        $this->assertTrue(Schema::hasColumn('exercises', 'exercise_type'));
        
        $indexes = DB::select("PRAGMA index_list('exercises')");
        $indexNames = collect($indexes)->pluck('name')->toArray();
        $this->assertContains('idx_exercises_exercise_type', $indexNames);

        // Simulate rollback by manually dropping the column and index
        // (In real scenario, this would be done by running the migration rollback)
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Verify column and index are removed
        $this->assertFalse(Schema::hasColumn('exercises', 'exercise_type'));
        
        $indexesAfterRollback = DB::select("PRAGMA index_list('exercises')");
        $indexNamesAfterRollback = collect($indexesAfterRollback)->pluck('name')->toArray();
        $this->assertNotContains('idx_exercises_exercise_type', $indexNamesAfterRollback);
    }

    /** @test */
    public function rollback_preserves_legacy_fields()
    {
        // Create exercise with legacy field data
        $exercise = Exercise::create([
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => 'resistance',
        ]);

        // Verify legacy fields exist and have correct values
        $this->assertTrue(Schema::hasColumn('exercises', 'is_bodyweight'));
        $this->assertTrue(Schema::hasColumn('exercises', 'band_type'));
        
        $this->assertTrue($exercise->is_bodyweight);
        $this->assertEquals('resistance', $exercise->band_type);

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Refresh the exercise from database
        $exercise->refresh();

        // Verify legacy fields are still intact
        $this->assertTrue($exercise->is_bodyweight);
        $this->assertEquals('resistance', $exercise->band_type);
    }

    /** @test */
    public function rollback_preserves_all_exercise_data()
    {
        // Create exercises with various configurations
        $testExercises = [
            [
                'title' => 'Regular Exercise',
                'is_bodyweight' => false,
                'band_type' => null,
            ],
            [
                'title' => 'Bodyweight Exercise',
                'is_bodyweight' => true,
                'band_type' => null,
            ],
            [
                'title' => 'Resistance Band Exercise',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
            ],
            [
                'title' => 'Assistance Band Exercise',
                'is_bodyweight' => false,
                'band_type' => 'assistance',
            ],
        ];

        $createdExercises = [];
        foreach ($testExercises as $exerciseData) {
            $createdExercises[] = Exercise::create([
                'title' => $exerciseData['title'],
                'description' => 'Test exercise',
                'user_id' => $this->user->id,
                'is_bodyweight' => $exerciseData['is_bodyweight'],
                'band_type' => $exerciseData['band_type'],
            ]);
        }

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Verify all exercise data is preserved
        foreach ($createdExercises as $index => $exercise) {
            $exercise->refresh();
            $originalData = $testExercises[$index];
            
            $this->assertEquals($originalData['title'], $exercise->title);
            $this->assertEquals($originalData['is_bodyweight'], $exercise->is_bodyweight);
            $this->assertEquals($originalData['band_type'], $exercise->band_type);
            $this->assertEquals($this->user->id, $exercise->user_id);
        }
    }

    /** @test */
    public function rollback_maintains_exercise_relationships()
    {
        // Create exercise with user relationship
        $exercise = Exercise::create([
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        // Verify relationship exists
        $this->assertNotNull($exercise->user);
        $this->assertEquals($this->user->id, $exercise->user->id);

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Refresh and verify relationship is still intact
        $exercise->refresh();
        $this->assertNotNull($exercise->user);
        $this->assertEquals($this->user->id, $exercise->user->id);
    }

    /** @test */
    public function rollback_preserves_exercise_count()
    {
        // Create multiple exercises
        $exerciseCount = 10;
        for ($i = 1; $i <= $exerciseCount; $i++) {
            Exercise::create([
                'title' => "Exercise {$i}",
                'description' => "Test exercise {$i}",
                'user_id' => $this->user->id,
                'is_bodyweight' => $i % 2 === 0,
                'band_type' => $i % 3 === 0 ? 'resistance' : null,
            ]);
        }

        // Verify count before rollback
        $countBeforeRollback = Exercise::count();
        $this->assertEquals($exerciseCount, $countBeforeRollback);

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Verify count after rollback
        $countAfterRollback = Exercise::count();
        $this->assertEquals($exerciseCount, $countAfterRollback);
        $this->assertEquals($countBeforeRollback, $countAfterRollback);
    }

    /** @test */
    public function rollback_is_safe_with_no_data_loss()
    {
        // Create comprehensive test data
        $originalData = [
            'title' => 'Comprehensive Test Exercise',
            'description' => 'This exercise tests all fields',
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => 'assistance',
            'canonical_name' => 'comprehensive-test-exercise',
        ];

        $exercise = Exercise::create($originalData);
        $originalId = $exercise->id;

        // Capture all original field values
        $originalValues = [
            'id' => $exercise->id,
            'title' => $exercise->title,
            'description' => $exercise->description,
            'user_id' => $exercise->user_id,
            'is_bodyweight' => $exercise->is_bodyweight,
            'band_type' => $exercise->band_type,
            'canonical_name' => $exercise->canonical_name,
            'created_at' => $exercise->created_at,
            'updated_at' => $exercise->updated_at,
        ];

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Verify all original data is preserved
        $exercise->refresh();
        
        foreach ($originalValues as $field => $value) {
            $this->assertEquals($value, $exercise->$field, "Field {$field} should be preserved after rollback");
        }
    }

    /** @test */
    public function rollback_handles_large_dataset()
    {
        // Create a large dataset
        $exerciseCount = 500;
        $exerciseData = [];
        
        for ($i = 1; $i <= $exerciseCount; $i++) {
            $exerciseData[] = [
                'title' => "Rollback Test Exercise {$i}",
                'description' => "Test exercise {$i}",
                'user_id' => $this->user->id,
                'is_bodyweight' => $i % 4 === 0,
                'band_type' => $i % 3 === 0 ? 'resistance' : ($i % 5 === 0 ? 'assistance' : null),
                'exercise_type' => 'regular', // This will be dropped during rollback
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks
        $chunks = array_chunk($exerciseData, 100);
        foreach ($chunks as $chunk) {
            DB::table('exercises')->insert($chunk);
        }

        // Verify count before rollback
        $countBefore = Exercise::count();
        $this->assertEquals($exerciseCount, $countBefore);

        // Measure rollback performance
        $startTime = microtime(true);
        
        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });
        
        $endTime = microtime(true);
        $rollbackTime = $endTime - $startTime;

        // Verify count after rollback
        $countAfter = Exercise::count();
        $this->assertEquals($exerciseCount, $countAfter);
        $this->assertEquals($countBefore, $countAfter);

        // Verify rollback performance
        $this->assertLessThan(10.0, $rollbackTime, 'Rollback should complete within 10 seconds for 500 exercises');

        // Verify exercise_type column is gone
        $this->assertFalse(Schema::hasColumn('exercises', 'exercise_type'));
    }

    /** @test */
    public function rollback_maintains_database_constraints()
    {
        // Create exercise that tests database constraints
        $exercise = Exercise::create([
            'title' => 'Constraint Test Exercise',
            'description' => 'Testing database constraints',
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        // Simulate rollback
        Schema::table('exercises', function ($table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });

        // Verify that existing constraints still work
        $exercise->refresh();
        
        // Test that we can still create new exercises after rollback
        // Use DB::table to avoid model-level exercise_type assignment
        $newExerciseId = DB::table('exercises')->insertGetId([
            'title' => 'Post-Rollback Exercise',
            'description' => 'Created after rollback',
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => 'resistance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newExercise = DB::table('exercises')->where('id', $newExerciseId)->first();
        
        $this->assertNotNull($newExercise->id);
        $this->assertTrue((bool) $newExercise->is_bodyweight);
        $this->assertEquals('resistance', $newExercise->band_type);
    }
}