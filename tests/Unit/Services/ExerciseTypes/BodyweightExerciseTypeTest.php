<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\BodyweightExerciseType;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BodyweightExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private BodyweightExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BodyweightExerciseType();
    }

    /** @test */
    public function it_returns_correct_type_name()
    {
        $this->assertEquals('bodyweight', $this->strategy->getTypeName());
    }

    /** @test */
    public function it_returns_validation_rules_from_config()
    {
        $expectedRules = [
            'weight' => 'nullable|numeric|min:0',
            'reps' => 'required|integer|min:1|max:100',
        ];

        $this->assertEquals($expectedRules, $this->strategy->getValidationRules());
    }

    /** @test */
    public function it_returns_required_weight_validation_for_user_with_show_extra_weight()
    {
        $user = User::factory()->create(['show_extra_weight' => true]);

        $rules = $this->strategy->getValidationRules($user);

        $this->assertEquals('required|numeric|min:0', $rules['weight']);
    }

    /** @test */
    public function it_returns_nullable_weight_validation_for_user_without_show_extra_weight()
    {
        $user = User::factory()->create(['show_extra_weight' => false]);

        $rules = $this->strategy->getValidationRules($user);

        $this->assertEquals('nullable|numeric|min:0', $rules['weight']);
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
            'weight' => 25, // Extra weight
            'reps' => 5,
            'band_color' => 'red', // Should be nullified
        ];

        $expectedData = [
            'weight' => 25,
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

        $processedData = $this->strategy->processLiftData($inputData);

        $this->assertEquals(0, $processedData['weight']);
        $this->assertNull($processedData['band_color']);
    }

    /** @test */
    public function it_processes_lift_data_with_non_numeric_weight()
    {
        $inputData = [
            'weight' => 'invalid',
            'reps' => 5,
        ];

        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Invalid weight value \'invalid\' for bodyweight exercise');

        $this->strategy->processLiftData($inputData);
    }

    /** @test */
    public function it_processes_exercise_data_correctly()
    {
        $inputData = [
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight',
        ];

        $processedData = $this->strategy->processExerciseData($inputData);

        $this->assertEquals('Push-ups', $processedData['title']);
        $this->assertEquals('bodyweight', $processedData['exercise_type']);
    }

    /** @test */
    public function it_does_not_support_1rm_calculation()
    {
        $this->assertFalse($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_returns_correct_chart_type()
    {
        $this->assertEquals('bodyweight_progression', $this->strategy->getChartType());
    }

    /** @test */
    public function it_returns_supported_progression_types()
    {
        $expectedTypes = ['linear', 'double_progression', 'bodyweight_progression'];

        $this->assertEquals($expectedTypes, $this->strategy->getSupportedProgressionTypes());
    }

    /** @test */
    public function it_formats_bodyweight_only_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Bodyweight', $formatted);
    }

    /** @test */
    public function it_formats_bodyweight_plus_extra_weight_display()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25.5,
            'reps' => 10
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Bodyweight +25.5 lbs', $formatted);
    }

    /** @test */
    public function it_formats_negative_weight_as_bodyweight_only()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => -10,
            'reps' => 10
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Bodyweight', $formatted);
    }

    /** @test */
    public function it_formats_non_numeric_weight_as_bodyweight_only()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 'invalid',
            'reps' => 10
        ]);

        $formatted = $this->strategy->formatWeightDisplay($liftLog);

        $this->assertEquals('Bodyweight', $formatted);
    }

    /** @test */
    public function it_formats_1rm_display_as_empty_string()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 30,
            'reps' => 5
        ]);

        // Manually set the one_rep_max for testing
        $liftLog->one_rep_max = 35.0;

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        $this->assertEquals('', $formatted);
    }

    /** @test */
    public function it_formats_zero_1rm_as_empty_string()
    {
        $liftLog = new LiftLog();
        $liftLog->one_rep_max = 0;

        $formatted = $this->strategy->format1RMDisplay($liftLog);

        $this->assertEquals('', $formatted);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_high_reps_no_weight()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 12
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Consider adding 5-10 lbs extra weight', $suggestion);
    }

    /** @test */
    public function it_returns_progression_suggestion_for_high_reps_with_weight()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25,
            'reps' => 15
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertEquals('Try 30 lbs extra weight', $suggestion);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_for_low_reps()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 8
        ]);

        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_returns_null_progression_suggestion_for_non_numeric_reps()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 'invalid'
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
    }

}