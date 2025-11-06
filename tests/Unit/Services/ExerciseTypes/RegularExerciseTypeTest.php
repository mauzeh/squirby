<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\RegularExerciseType;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegularExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private RegularExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new RegularExerciseType();
    }

    /** @test */
    public function it_returns_correct_type_name()
    {
        $this->assertEquals('regular', $this->strategy->getTypeName());
    }

    /** @test */
    public function it_returns_validation_rules_from_config()
    {
        $expectedRules = [
            'weight' => 'required|numeric|min:0|max:2000',
            'reps' => 'required|integer|min:1|max:100',
        ];

        $this->assertEquals($expectedRules, $this->strategy->getValidationRules());
    }

    /** @test */
    public function it_returns_form_fields_from_config()
    {
        $expectedFields = ['weight', 'reps'];

        $this->assertEquals($expectedFields, $this->strategy->getFormFields());
    }

    /** @test */
    public function it_processes_lift_data_correctly()
    {
        $inputData = [
            'weight' => 100,
            'reps' => 5,
            'band_color' => 'red', // Should be nullified
        ];

        $expectedData = [
            'weight' => 100,
            'reps' => 5,
            'band_color' => null,
        ];

        $this->assertEquals($expectedData, $this->strategy->processLiftData($inputData));
    }

    /** @test */
    public function it_processes_lift_data_with_missing_weight()
    {
        $inputData = [
            'reps' => 5,
        ];

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Required field \'weight\' missing for regular exercise');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_processes_lift_data_with_non_numeric_weight()
    {
        $inputData = [
            'weight' => 'invalid',
            'reps' => 5,
        ];

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Invalid weight value \'invalid\' for regular exercise');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_processes_exercise_data_correctly()
    {
        $inputData = [
            'title' => 'Bench Press',
            // Should be set to false
            // Should be set to null,
            'exercise_type' => 'banded_resistance'
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals('Bench Press', $processedData['title']);
        $this->assertFalse($processedData['is_bodyweight']);
        $this->assertNull($processedData['band_type']);
    }

    /** @test */
    public function it_supports_1rm_calculation()
    {
        $this->assertTrue($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_returns_correct_chart_type()
    {
        $this->assertEquals('weight_progression', $this->strategy->getChartType());
    }

    /** @test */
    public function it_returns_supported_progression_types()
    {
        $expectedTypes = ['weight_progression', 'volume_progression'];

        $this->assertEquals($expectedTypes, $this->strategy->getSupportedProgressionTypes());
    }

    /** @test */
    public function it_formats_weight_display_correctly()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100.5,
            'reps' => 5
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('100.5 lbs', $formatted);
    }

    /** @test */
    public function it_formats_zero_weight_display()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 5
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('0 lbs', $formatted);
    }

    /** @test */
    public function it_formats_negative_weight_as_zero()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => -10,
            'reps' => 5
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('0 lbs', $formatted);
    }

    /** @test */
    public function it_formats_non_numeric_weight_as_zero()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 'invalid',
            'reps' => 5
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('0 lbs', $formatted);
    }

    /** @test */
    public function it_formats_1rm_display_correctly()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5
        ]);

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        // Should format the calculated 1RM (100 * (1 + 0.0333 * 5) = 116.65)
        $this->assertStringContainsString('lbs', $formatted);
        $this->assertNotEmpty($formatted);
    }

    /** @test */
    public function it_formats_zero_1rm_as_empty_string()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        // Don't create any lift sets, so 1RM will be 0

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        $this->assertEquals('', $formatted);
    }

    /** @test */
    public function it_returns_null_progression_suggestion()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_returns_type_config()
    {
        $config = $this->strategy->getTypeConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('class', $config);
        $this->assertArrayHasKey('validation', $config);
        $this->assertArrayHasKey('supports_1rm', $config);
        $this->assertTrue($config['supports_1rm']);
    }
}