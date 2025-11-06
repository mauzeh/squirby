<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\LiftLogController;
use App\Http\Controllers\ExerciseController;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class ExerciseTypeControllerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        ExerciseTypeFactory::clearCache();
    }

    /** @test */
    public function lift_log_controller_uses_regular_exercise_strategy_for_validation()
    {
        $this->actingAs($this->user);

        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($this->user);

        // Regular exercise should require weight
        $this->assertArrayHasKey('weight', $validationRules);
        $this->assertStringContainsString('required', $validationRules['weight']);
    }

    /** @test */
    public function lift_log_controller_uses_banded_exercise_strategy_for_validation()
    {
        $this->actingAs($this->user);

        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($this->user);

        // Banded exercise should require band_color
        $this->assertArrayHasKey('band_color', $validationRules);
        $this->assertStringContainsString('required', $validationRules['band_color']);
    }

    /** @test */
    public function lift_log_controller_uses_bodyweight_exercise_strategy_for_validation()
    {
        $this->actingAs($this->user);

        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($this->user);

        // Bodyweight exercise should have nullable weight by default
        $this->assertArrayHasKey('weight', $validationRules);
        $this->assertStringContainsString('nullable', $validationRules['weight']);
    }

    /** @test */
    public function lift_log_controller_uses_bodyweight_exercise_strategy_for_user_with_show_extra_weight()
    {
        $userWithExtraWeight = User::factory()->create(['show_extra_weight' => true]);
        $this->actingAs($userWithExtraWeight);

        $exercise = Exercise::factory()->create([
            'user_id' => $userWithExtraWeight->id,
            'exercise_type' => 'bodyweight'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($userWithExtraWeight);

        // Bodyweight exercise should require weight for users with show_extra_weight enabled
        $this->assertArrayHasKey('weight', $validationRules);
        $this->assertStringContainsString('required', $validationRules['weight']);
    }

    /** @test */
    public function lift_log_controller_uses_cardio_exercise_strategy_for_validation()
    {
        $this->actingAs($this->user);

        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($this->user);

        // Cardio exercise should require reps (distance) and weight should be 0
        $this->assertArrayHasKey('reps', $validationRules);
        $this->assertStringContainsString('required', $validationRules['reps']);
        $this->assertArrayHasKey('weight', $validationRules);
        $this->assertStringContainsString('in:0', $validationRules['weight']);
    }

    /** @test */
    public function lift_log_controller_processes_regular_exercise_data_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'weight' => 100,
            'reps' => 5,
            'band_color' => 'red', // Should be nullified
        ];

        $processedData = $strategy->processLiftData($inputData);

        $this->assertEquals(100, $processedData['weight']);
        $this->assertNull($processedData['band_color']);
    }

    /** @test */
    public function lift_log_controller_processes_banded_exercise_data_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'weight' => 100, // Should be set to 0
            'reps' => 8,
            'band_color' => 'red',
        ];

        $processedData = $strategy->processLiftData($inputData);

        $this->assertEquals(0, $processedData['weight']);
        $this->assertEquals('red', $processedData['band_color']);
    }

    /** @test */
    public function lift_log_controller_processes_bodyweight_exercise_data_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'weight' => 25, // Extra weight
            'reps' => 10,
            'band_color' => 'red', // Should be nullified
        ];

        $processedData = $strategy->processLiftData($inputData);

        $this->assertEquals(25, $processedData['weight']);
        $this->assertNull($processedData['band_color']);
    }

    /** @test */
    public function lift_log_controller_processes_cardio_exercise_data_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'weight' => 100, // Should be forced to 0
            'reps' => 500, // Distance in meters
            'band_color' => 'red', // Should be nullified
        ];

        $processedData = $strategy->processLiftData($inputData);

        $this->assertEquals(0, $processedData['weight']);
        $this->assertEquals(500, $processedData['reps']);
        $this->assertNull($processedData['band_color']);
    }

    /** @test */
    public function exercise_controller_processes_regular_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Bench Press',
            // Should be set to false
            // Should be set to null,
            'exercise_type' => 'banded_resistance'
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Bench Press', $processedData['title']);
        $this->assertEquals('regular', $processedData['exercise_type']);
    }

    /** @test */
    public function exercise_controller_processes_banded_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'exercise_type' => 'banded_resistance'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Banded Squat',
            // Should be set to false when band_type is set,
            'exercise_type' => 'banded_resistance'
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Banded Squat', $processedData['title']);
        $this->assertEquals('banded_resistance', $processedData['exercise_type']);
    }

    /** @test */
    public function exercise_controller_processes_bodyweight_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'exercise_type' => 'bodyweight'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Push-ups',
            // Should be set to null when is_bodyweight is true,
            'exercise_type' => 'banded_resistance'
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Push-ups', $processedData['title']);
        $this->assertEquals('bodyweight', $processedData['exercise_type']);
    }

    /** @test */
    public function exercise_controller_processes_cardio_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'exercise_type' => 'cardio'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Running',
            'exercise_type' => 'regular' // Should be overridden to cardio
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Running', $processedData['title']);
        $this->assertEquals('cardio', $processedData['exercise_type']);
        $this->assertFalse($processedData['is_bodyweight']);
    }

    /** @test */
    public function controllers_maintain_backward_compatibility_with_existing_exercise_properties()
    {
        // Create exercises with different types
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $cardioExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        // Test that existing model methods still work
        $this->assertFalse($regularExercise->isBandedResistance());
        $this->assertFalse($regularExercise->isBandedAssistance());
        
        $this->assertTrue($bandedExercise->isBandedResistance());
        $this->assertFalse($bandedExercise->isBandedAssistance());
        
        $this->assertFalse($bodyweightExercise->isBandedResistance());
        $this->assertFalse($bodyweightExercise->isBandedAssistance());

        $this->assertFalse($cardioExercise->isBandedResistance());
        $this->assertFalse($cardioExercise->isBandedAssistance());

        // Test that strategies are created correctly for each type
        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);
        $cardioStrategy = ExerciseTypeFactory::create($cardioExercise);

        $this->assertEquals('regular', $regularStrategy->getTypeName());
        $this->assertEquals('banded_resistance', $bandedStrategy->getTypeName());
        $this->assertEquals('bodyweight', $bodyweightStrategy->getTypeName());
        $this->assertEquals('cardio', $cardioStrategy->getTypeName());
    }

    /** @test */
    public function controllers_handle_form_fields_correctly_for_each_exercise_type()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $cardioExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);
        $cardioStrategy = ExerciseTypeFactory::create($cardioExercise);

        // Regular exercise should show weight and reps fields
        $regularFields = $regularStrategy->getFormFields();
        $this->assertContains('weight', $regularFields);
        $this->assertContains('reps', $regularFields);

        // Banded exercise should show band_color and reps fields
        $bandedFields = $bandedStrategy->getFormFields();
        $this->assertContains('band_color', $bandedFields);
        $this->assertContains('reps', $bandedFields);

        // Bodyweight exercise should show reps fields (weight is handled separately for extra weight)
        $bodyweightFields = $bodyweightStrategy->getFormFields();
        $this->assertContains('reps', $bodyweightFields);
        // Weight field is not included in form fields for bodyweight exercises
        // as it's handled through user's body weight + extra weight logic

        // Cardio exercise should only show reps field (distance)
        $cardioFields = $cardioStrategy->getFormFields();
        $this->assertContains('reps', $cardioFields);
        // Weight field is not included as it's always 0 for cardio exercises
    }

    /** @test */
    public function controllers_handle_edge_cases_in_data_processing()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($regularExercise);

        // Test missing weight - should throw exception
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Required field \'weight\' missing for regular exercise');
        
        $dataWithoutWeight = ['reps' => 5];
        $strategy->processLiftData($dataWithoutWeight);
    }

    /** @test */
    public function controllers_handle_invalid_weight_data()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($regularExercise);

        // Test non-numeric weight - should throw exception
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException::class);
        $this->expectExceptionMessage('Invalid weight value \'invalid\' for regular exercise');
        
        $dataWithInvalidWeight = ['weight' => 'invalid', 'reps' => 5];
        $strategy->processLiftData($dataWithInvalidWeight);
    }

    /** @test */
    public function controllers_handle_valid_data_processing()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $strategy = ExerciseTypeFactory::create($regularExercise);

        // Test that band_color is always nullified for regular exercises
        $dataWithBandColor = ['weight' => 100, 'reps' => 5, 'band_color' => 'red'];
        $processed = $strategy->processLiftData($dataWithBandColor);
        $this->assertEquals(100, $processed['weight']);
        $this->assertNull($processed['band_color']);
    }
}