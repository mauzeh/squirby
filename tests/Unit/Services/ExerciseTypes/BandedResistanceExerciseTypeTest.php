<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\BandedResistanceExerciseType;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandedResistanceExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private BandedResistanceExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BandedResistanceExerciseType();
    }

    /** @test */
    public function it_returns_correct_type_name()
    {
        $this->assertEquals('banded_resistance', $this->strategy->getTypeName());
    }

    /** @test */
    public function it_returns_validation_rules_from_config()
    {
        $expectedRules = [
            'band_color' => 'required|string|in:red,blue,green',
            'reps' => 'required|integer|min:1|max:100',
            'weight' => 'nullable|numeric|in:0',
        ];

        $this->assertEquals($expectedRules, $this->strategy->getValidationRules());
    }

    /** @test */
    public function it_returns_form_fields_from_config()
    {
        $expectedFields = ['band_color', 'reps'];

        $this->assertEquals($expectedFields, $this->strategy->getFormFields());
    }

    /** @test */
    public function it_processes_lift_data_correctly()
    {
        $inputData = [
            'weight' => 100, // Should be set to 0
            'reps' => 5,
            'band_color' => 'red',
        ];

        $expectedData = [
            'weight' => 0,
            'reps' => 5,
            'band_color' => 'red',
        ];

        $this->assertEquals($expectedData, $this->strategy->processLiftData($inputData));
    }

    /** @test */
    public function it_processes_lift_data_with_missing_band_color()
    {
        $inputData = [
            'weight' => 100,
            'reps' => 5,
        ];

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Required field \'band_color\' missing for banded_resistance exercise');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_processes_exercise_data_correctly()
    {
        $inputData = [
            'title' => 'Banded Squat',
            'is_bodyweight' => true, // Should be set to false when band_type is set
            'band_type' => 'resistance',
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals('Banded Squat', $processedData['title']);
        $this->assertFalse($processedData['is_bodyweight']);
        $this->assertEquals('resistance', $processedData['band_type']);
    }

    /** @test */
    public function it_does_not_support_1rm_calculation()
    {
        $this->assertFalse($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_returns_correct_chart_type()
    {
        $this->assertEquals('band_progression', $this->strategy->getChartType());
    }

    /** @test */
    public function it_returns_supported_progression_types()
    {
        $expectedTypes = ['band_progression'];

        $this->assertEquals($expectedTypes, $this->strategy->getSupportedProgressionTypes());
    }

    /** @test */
    public function it_formats_weight_display_correctly()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'red',
            'weight' => 0,
            'reps' => 8
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Band: Red', $formatted);
    }

    /** @test */
    public function it_formats_empty_band_color_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => '',
            'weight' => 0,
            'reps' => 8
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Band: N/A', $formatted);
    }

    /** @test */
    public function it_formats_1rm_display_throws_exception()
    {
        $liftLog = new LiftLog();
        $liftLog->one_rep_max = 125.7;

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for banded_resistance exercises');

        $this->strategy->format1RMDisplay($liftLog);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_high_reps()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'red',
            'weight' => 0,
            'reps' => 15
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try blue band with 8 reps', $suggestion);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_for_low_reps()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'red',
            'weight' => 0,
            'reps' => 10
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_for_highest_band()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'green', // Highest resistance band
            'weight' => 0,
            'reps' => 15
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
        $this->assertFalse($config['supports_1rm']);
        $this->assertEquals(\App\Services\ExerciseTypes\BandedResistanceExerciseType::class, $config['class']);
    }
}