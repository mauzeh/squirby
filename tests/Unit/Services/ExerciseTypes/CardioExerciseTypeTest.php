<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\CardioExerciseType;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
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
    public function it_returns_validation_rules_from_config()
    {
        $rules = $this->strategy->getValidationRules();
        
        $this->assertIsArray($rules);
    }

    /** @test */
    public function it_returns_form_fields_from_config()
    {
        $fields = $this->strategy->getFormFields();
        
        $this->assertIsArray($fields);
    }

    /** @test */
    public function it_processes_lift_data_correctly()
    {
        $inputData = [
            'reps' => 500,      // Distance in meters
            'sets' => 7,        // Rounds
            'weight' => 25,     // Should be forced to 0
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
        $validDistances = [50, 500, 1000, 5000, 50000];

        foreach ($validDistances as $distance) {
            $inputData = ['reps' => $distance];
            $result = $this->strategy->processLiftData($inputData);
            
            $this->assertEquals($distance, $result['reps']);
        }
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
            'reps' => 'invalid',
        ];

        $this->expectException(InvalidExerciseDataException::class);
        $this->expectExceptionMessage('distance must be a number');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_processes_exercise_data_correctly()
    {
        $inputData = [
            'title' => 'Running',
            'exercise_type' => 'cardio',
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals('Running', $processedData['title']);
        $this->assertEquals('cardio', $processedData['exercise_type']);
        $this->assertFalse($processedData['is_bodyweight']);
    }

    /** @test */
    public function it_does_not_support_1rm_calculation()
    {
        $this->assertFalse($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_formats_distance_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500, // Distance in meters
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('500m', $formatted);
    }

    /** @test */
    public function it_formats_zero_distance_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
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
    public function it_formats_non_numeric_distance_as_zero()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 'invalid',
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('0m', $formatted);
    }

    /** @test */
    public function it_formats_long_distances_in_kilometers()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 15000, // 15km
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('15.0km', $formatted);
    }

    /** @test */
    public function it_formats_complete_cardio_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create multiple sets to test rounds counting
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500,
            'weight' => 0
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500,
            'weight' => 0
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500,
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatCompleteDisplay($liftLog);

        $this->assertEquals('500m × 3 rounds', $formatted);
    }

    /** @test */
    public function it_formats_complete_cardio_display_with_single_round()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 1000,
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatCompleteDisplay($liftLog);

        $this->assertEquals('1,000m × 1 round', $formatted);
    }

    /** @test */
    public function it_formats_complete_cardio_display_with_long_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 12000, // 12km
            'weight' => 0
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 12000,
            'weight' => 0
        ]);

        $formatted = $this->strategy->formatCompleteDisplay($liftLog);

        $this->assertEquals('12.0km × 2 rounds', $formatted);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_short_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create 3 sets to simulate 3 rounds
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 500, // 500m distance
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 600m × 3 rounds', $suggestion);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_very_short_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create 2 sets to simulate 2 rounds
        LiftSet::factory()->count(2)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 200, // 200m distance
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 250m × 2 rounds', $suggestion);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_long_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create 5 sets to simulate 5 rounds
        LiftSet::factory()->count(5)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 2000, // 2000m distance
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 2000m × 6 rounds', $suggestion);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_for_invalid_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
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
    public function it_returns_null_progression_suggestion_for_zero_distance()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 0,
            'weight' => 0
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_returns_type_config()
    {
        $config = $this->strategy->getTypeConfig();

        $this->assertIsArray($config);
    }

    /** @test */
    public function it_formats_suggestion_text_for_short_distance_in_miles()
    {
        $suggestion = (object)[
            'reps' => 3.5, // 3.5 miles
            'sets' => 2
        ];

        $result = $this->strategy->formatSuggestionText($suggestion);

        $this->assertEquals('Suggested: 3.5 mi × 2 rounds', $result);
    }

    /** @test */
    public function it_formats_suggestion_text_for_long_distance_in_kilometers()
    {
        $suggestion = (object)[
            'reps' => 15, // 15 miles = ~2.8 km
            'sets' => 1
        ];

        $result = $this->strategy->formatSuggestionText($suggestion);

        $this->assertEquals('Suggested: 2.8 km × 1 rounds', $result);
    }

    /** @test */
    public function it_formats_suggestion_text_with_default_sets()
    {
        $suggestion = (object)[
            'reps' => 5
        ];

        $result = $this->strategy->formatSuggestionText($suggestion);

        $this->assertEquals('Suggested: 5 mi × 1 rounds', $result);
    }

    /** @test */
    public function it_returns_null_when_cardio_suggestion_missing_reps()
    {
        $suggestion = (object)[
            'sets' => 2
        ];

        $result = $this->strategy->formatSuggestionText($suggestion);

        $this->assertNull($result);
    }
}