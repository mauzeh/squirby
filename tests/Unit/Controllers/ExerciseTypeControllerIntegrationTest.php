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
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => 'resistance',
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
            'is_bodyweight' => true,
            'band_type' => null,
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
            'is_bodyweight' => true,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        $validationRules = $strategy->getValidationRules($userWithExtraWeight);

        // Bodyweight exercise should require weight for users with show_extra_weight enabled
        $this->assertArrayHasKey('weight', $validationRules);
        $this->assertStringContainsString('required', $validationRules['weight']);
    }

    /** @test */
    public function lift_log_controller_processes_regular_exercise_data_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => 'resistance',
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
            'is_bodyweight' => true,
            'band_type' => null,
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
    public function exercise_controller_processes_regular_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Bench Press',
            'is_bodyweight' => true, // Should be set to false
            'band_type' => 'resistance', // Should be set to null
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Bench Press', $processedData['title']);
        $this->assertFalse($processedData['is_bodyweight']);
        $this->assertNull($processedData['band_type']);
    }

    /** @test */
    public function exercise_controller_processes_banded_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Banded Squat',
            'is_bodyweight' => true, // Should be set to false when band_type is set
            'band_type' => 'resistance',
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Banded Squat', $processedData['title']);
        $this->assertFalse($processedData['is_bodyweight']);
        $this->assertEquals('resistance', $processedData['band_type']);
    }

    /** @test */
    public function exercise_controller_processes_bodyweight_exercise_creation_data()
    {
        $exercise = Exercise::factory()->make([
            'is_bodyweight' => true,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        $inputData = [
            'title' => 'Push-ups',
            'is_bodyweight' => true,
            'band_type' => 'resistance', // Should be set to null when is_bodyweight is true
        ];

        $processedData = $strategy->processExerciseData($inputData);

        $this->assertEquals('Push-ups', $processedData['title']);
        $this->assertTrue($processedData['is_bodyweight']);
        $this->assertNull($processedData['band_type']);
    }

    /** @test */
    public function controllers_maintain_backward_compatibility_with_existing_exercise_properties()
    {
        // Create exercises with different types
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => null,
        ]);

        // Test that existing model methods still work
        $this->assertFalse($regularExercise->isBandedResistance());
        $this->assertFalse($regularExercise->isBandedAssistance());
        
        $this->assertTrue($bandedExercise->isBandedResistance());
        $this->assertFalse($bandedExercise->isBandedAssistance());
        
        $this->assertFalse($bodyweightExercise->isBandedResistance());
        $this->assertFalse($bodyweightExercise->isBandedAssistance());

        // Test that strategies are created correctly for each type
        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);

        $this->assertEquals('regular', $regularStrategy->getTypeName());
        $this->assertEquals('banded', $bandedStrategy->getTypeName());
        $this->assertEquals('bodyweight', $bodyweightStrategy->getTypeName());
    }

    /** @test */
    public function controllers_handle_form_fields_correctly_for_each_exercise_type()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => true,
            'band_type' => null,
        ]);

        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);

        // Regular exercise should show weight and reps fields
        $regularFields = $regularStrategy->getFormFields();
        $this->assertContains('weight', $regularFields);
        $this->assertContains('reps', $regularFields);

        // Banded exercise should show band_color and reps fields
        $bandedFields = $bandedStrategy->getFormFields();
        $this->assertContains('band_color', $bandedFields);
        $this->assertContains('reps', $bandedFields);

        // Bodyweight exercise should show weight (for extra weight) and reps fields
        $bodyweightFields = $bodyweightStrategy->getFormFields();
        $this->assertContains('weight', $bodyweightFields);
        $this->assertContains('reps', $bodyweightFields);
    }

    /** @test */
    public function controllers_handle_edge_cases_in_data_processing()
    {
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($regularExercise);

        // Test that band_color is always nullified for regular exercises
        $dataWithBandColor = ['weight' => 100, 'reps' => 5, 'band_color' => 'red'];
        $processed = $strategy->processLiftData($dataWithBandColor);
        $this->assertEquals(100, $processed['weight']);
        $this->assertNull($processed['band_color']);
    }
}