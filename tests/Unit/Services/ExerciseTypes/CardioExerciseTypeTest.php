<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\CardioExerciseType;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CardioExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private CardioExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CardioExerciseType();
    }

    /** @test */
    public function it_returns_correct_type_name()
    {
        $this->assertEquals('cardio', $this->strategy->getTypeName());
    }

    /** @test */
    public function it_processes_lift_data_correctly()
    {
        $inputData = [
            'reps' => 500, // Distance in meters
            'sets' => 7,   // Rounds
            'weight' => 25, // Should be forced to 0
            'band_color' => 'red', // Should be nullified
        ];

        $expectedData = [
            'reps' => 500,
            'sets' => 7,
            'weight' => 0,
            'band_color' => null,
        ];

        $result = $this->strategy->processLiftData($inputData);

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_forces_weight_to_zero()
    {
        $inputData = [
            'reps' => 1000,
            'weight' => 100, // Should be forced to 0
        ];

        $result = $this->strategy->processLiftData($inputData);

        $this->assertEquals(0, $result['weight']);
    }

    /** @test */
    public function it_nullifies_band_color()
    {
        $inputData = [
            'reps' => 500,
            'band_color' => 'blue', // Should be nullified
        ];

        $result = $this->strategy->processLiftData($inputData);

        $this->assertNull($result['band_color']);
    }

    /** @test */
    public function it_validates_minimum_distance()
    {
        $inputData = [
            'reps' => 25, // Below minimum of 50m
        ];

        $this->expectException(InvalidExerciseDataException::class);
        $this->expectExceptionMessage('distance must be at least 50 meters');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_validates_maximum_distance()
    {
        $inputData = [
            'reps' => 60000, // Above maximum of 50,000m
        ];

        $this->expectException(InvalidExerciseDataException::class);
        $this->expectExceptionMessage('distance cannot exceed 50000 meters');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_accepts_valid_distance_range()
    {
        $inputData = [
            'reps' => 1000, // Valid distance
        ];

        $result = $this->strategy->processLiftData($inputData);

        $this->assertEquals(1000, $result['reps']);
    }

    /** @test */
    public function it_throws_exception_for_missing_distance()
    {
        $inputData = [
            'sets' => 5,
            // Missing 'reps' (distance)
        ];

        $this->expectException(InvalidExerciseDataException::class);
        $this->expectExceptionMessage("Required field 'reps' missing for cardio exercise");

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_throws_exception_for_non_numeric_distance()
    {
        $inputData = [
            'reps' => 'invalid', // Non-numeric distance
        ];

        $this->expectException(InvalidExerciseDataException::class);
        $this->expectExceptionMessage('distance must be a number');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_formats_distance_display_in_meters()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500, // 500 meters
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('500m', $formatted);
    }

    /** @test */
    public function it_formats_distance_display_in_kilometers()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 2000, // 2000 meters = 2km
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('2km', $formatted);
    }

    /** @test */
    public function it_formats_fractional_kilometers()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 1500, // 1500 meters = 1.5km
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('1.5km', $formatted);
    }

    /** @test */
    public function it_handles_zero_distance()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 0,
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('0m', $formatted);
    }

    /** @test */
    public function it_does_not_support_1rm_calculation()
    {
        $this->assertFalse($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_returns_empty_string_for_1rm_display()
    {
        $liftLog = new LiftLog();
        $liftLog->one_rep_max = 100; // Should be ignored

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        $this->assertEquals('', $formatted);
    }

    /** @test */
    public function it_suggests_distance_progression_for_moderate_distances()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create 3 sets to simulate rounds
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500, // 500m distance
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 600m × 3 rounds', $suggestion);
    }

    /** @test */
    public function it_suggests_smaller_increment_for_short_distances()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        LiftSet::factory()->count(2)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 200, // 200m distance (< 500m)
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 250m × 2 rounds', $suggestion);
    }

    /** @test */
    public function it_suggests_rounds_progression_for_long_distances()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        LiftSet::factory()->count(5)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 2000, // 2000m distance (>= 1000m)
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 2km × 6 rounds', $suggestion);
    }

    /** @test */
    public function it_returns_null_progression_for_invalid_distance()
    {
        $exercise = Exercise::factory()->create();
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 'invalid',
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_processes_exercise_data_correctly()
    {
        $inputData = [
            'title' => 'Running',
            'is_bodyweight' => true, // Should be set to false
            'band_type' => 'resistance', // Should be nullified
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals('Running', $processedData['title']);
        $this->assertFalse($processedData['is_bodyweight']);
        $this->assertNull($processedData['band_type']);
    }

    /** @test */
    public function it_returns_validation_rules_with_distance_constraints()
    {
        $rules = $this->strategy->getValidationRules();

        $this->assertArrayHasKey('reps', $rules);
        $this->assertArrayHasKey('weight', $rules);
        $this->assertArrayHasKey('sets', $rules);
        
        $this->assertStringContainsString('min:50', $rules['reps']);
        $this->assertStringContainsString('max:50000', $rules['reps']);
        $this->assertStringContainsString('in:0', $rules['weight']);
    }
}