<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\BaseExerciseType;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BaseExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private TestableBaseExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TestableBaseExerciseType();
    }

    /** @test */
    public function it_loads_config_from_exercise_types_config()
    {
        $config = $this->strategy->getTypeConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('validation', $config);
        $this->assertArrayHasKey('supports_1rm', $config);
    }

    /** @test */
    public function it_returns_validation_rules_from_config()
    {
        $rules = $this->strategy->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('weight', $rules);
        $this->assertEquals('required|numeric|min:0|max:2000', $rules['weight']);
    }

    /** @test */
    public function it_returns_form_fields_from_config()
    {
        $fields = $this->strategy->getFormFields();

        $this->assertIsArray($fields);
        $this->assertContains('weight', $fields);
        $this->assertContains('reps', $fields);
    }

    /** @test */
    public function it_returns_exercise_data_unchanged_by_default()
    {
        $inputData = [
            'title' => 'Test Exercise',
            'description' => 'Test description',
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals($inputData, $processedData);
    }

    /** @test */
    public function it_returns_can_calculate_1rm_from_config()
    {
        $canCalculate = $this->strategy->canCalculate1RM();

        $this->assertTrue($canCalculate);
    }

    /** @test */
    public function it_returns_chart_type_from_config()
    {
        $chartType = $this->strategy->getChartType();

        $this->assertEquals('weight_progression', $chartType);
    }

    /** @test */
    public function it_returns_supported_progression_types_from_config()
    {
        $progressionTypes = $this->strategy->getSupportedProgressionTypes();

        $this->assertIsArray($progressionTypes);
        $this->assertContains('weight_progression', $progressionTypes);
        $this->assertContains('volume_progression', $progressionTypes);
    }

    /** @test */
    public function it_formats_1rm_display_when_supported()
    {
        $exercise = Exercise::factory()->create(['is_bodyweight' => false, 'band_type' => null]);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        
        // Create lift sets to generate a 1RM calculation
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        // Should format the calculated 1RM (100 * (1 + 0.0333 * 5) = 116.65)
        $this->assertStringContainsString('lbs', $formatted);
        $this->assertNotEmpty($formatted);
    }

    /** @test */
    public function it_returns_empty_string_for_zero_1rm()
    {
        $liftLog = new LiftLog();
        $liftLog->one_rep_max = 0;

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        $this->assertEquals('', $formatted);
    }

    /** @test */
    public function it_returns_empty_string_for_1rm_when_not_supported()
    {
        $strategy = new TestableBaseExerciseTypeNo1RM();
        $liftLog = new LiftLog();
        $liftLog->one_rep_max = 125.7;

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for banded exercises');

        $strategy->format1RMDisplay($liftLog);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_by_default()
    {
        $liftLog = new LiftLog();
        $liftLog->display_weight = 100;
        $liftLog->display_reps = 5;

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_returns_default_chart_type_when_not_configured()
    {
        $strategy = new TestableBaseExerciseTypeNoConfig();

        $chartType = $strategy->getChartType();

        $this->assertEquals('default', $chartType);
    }

    /** @test */
    public function it_returns_default_progression_types_when_not_configured()
    {
        $strategy = new TestableBaseExerciseTypeNoConfig();

        $progressionTypes = $strategy->getSupportedProgressionTypes();

        $this->assertEquals(['linear'], $progressionTypes);
    }

    /** @test */
    public function it_returns_false_for_1rm_when_not_configured()
    {
        $strategy = new TestableBaseExerciseTypeNoConfig();

        $canCalculate = $strategy->canCalculate1RM();

        $this->assertFalse($canCalculate);
    }
}

/**
 * Testable concrete implementation of BaseExerciseType for testing
 */
class TestableBaseExerciseType extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'regular';
    }

    public function processLiftData(array $data): array
    {
        return $data;
    }

    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return $liftLog->display_weight . ' lbs';
    }
}

/**
 * Testable concrete implementation that doesn't support 1RM
 */
class TestableBaseExerciseTypeNo1RM extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'banded';
    }

    public function processLiftData(array $data): array
    {
        return $data;
    }

    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return 'Band: ' . $liftLog->display_weight;
    }
}

/**
 * Testable concrete implementation with no config
 */
class TestableBaseExerciseTypeNoConfig extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'nonexistent';
    }

    public function processLiftData(array $data): array
    {
        return $data;
    }

    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return $liftLog->display_weight . ' units';
    }
}