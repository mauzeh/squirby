<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\OneRepMaxCalculatorService;
use App\Services\ChartService;
use App\Services\TrainingProgressionService;
use App\Services\BandService;
use App\Presenters\LiftLogTablePresenter;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\NotApplicableException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTypeStrategyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private OneRepMaxCalculatorService $oneRepMaxService;
    private ChartService $chartService;
    private TrainingProgressionService $progressionService;
    private LiftLogTablePresenter $presenter;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->oneRepMaxService = new OneRepMaxCalculatorService();
        $this->chartService = new ChartService();
        $this->progressionService = new TrainingProgressionService($this->oneRepMaxService, new BandService());
        $this->presenter = new LiftLogTablePresenter();
        $this->user = User::factory()->create();
        
        // Clear factory cache
        ExerciseTypeFactory::clearCache();
    }

    /** @test */
    public function it_integrates_regular_exercise_strategy_with_one_rep_max_service()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        // Strategy should support 1RM calculation
        $this->assertTrue($strategy->canCalculate1RM());
        
        // OneRepMaxService should work with regular exercises
        $oneRepMax = $this->oneRepMaxService->getLiftLogOneRepMax($liftLog);
        $this->assertGreaterThan(100, $oneRepMax);
    }

    /** @test */
    public function it_integrates_banded_exercise_strategy_with_one_rep_max_service()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 8,
            'band_color' => 'red',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        // Strategy should not support 1RM calculation
        $this->assertFalse($strategy->canCalculate1RM());
        
        // OneRepMaxService should throw exception for banded exercises
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->oneRepMaxService->getLiftLogOneRepMax($liftLog);
    }

    /** @test */
    public function it_integrates_bodyweight_exercise_strategy_with_one_rep_max_service()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25, // Extra weight
            'reps' => 8,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        // Strategy should NOT support 1RM calculation for bodyweight exercises
        $this->assertFalse($strategy->canCalculate1RM());
        
        // OneRepMaxService should throw exception for bodyweight exercises
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for bodyweight exercises');
        
        $this->oneRepMaxService->getLiftLogOneRepMax($liftLog);
    }

    /** @test */
    public function it_integrates_cardio_exercise_strategy_with_one_rep_max_service()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0, // Always 0 for cardio
            'reps' => 500, // Distance in meters
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);
        
        // Strategy should NOT support 1RM calculation for cardio exercises
        $this->assertFalse($strategy->canCalculate1RM());
        
        // OneRepMaxService should throw exception for cardio exercises
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for cardio exercises');
        
        $this->oneRepMaxService->getLiftLogOneRepMax($liftLog);
    }

    /** @test */
    public function it_integrates_exercise_strategies_with_chart_service()
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

        // Each strategy should return appropriate chart type
        $this->assertEquals('weight_progression', $regularStrategy->getChartType());
        $this->assertEquals('band_progression', $bandedStrategy->getChartType());
        $this->assertEquals('bodyweight_progression', $bodyweightStrategy->getChartType());
        $this->assertEquals('cardio_progression', $cardioStrategy->getChartType());
    }

    /** @test */
    public function it_integrates_exercise_strategies_with_progression_service()
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

        // Each strategy should return appropriate progression types
        $this->assertContains('weight_progression', $regularStrategy->getSupportedProgressionTypes());
        $this->assertContains('volume_progression', $regularStrategy->getSupportedProgressionTypes());
        
        // Note: The banded strategy will be either BandedResistanceExerciseType or BandedAssistanceExerciseType
        // Both support 'band_progression'
        $this->assertContains('band_progression', $bandedStrategy->getSupportedProgressionTypes());
        
        $this->assertContains('bodyweight_progression', $bodyweightStrategy->getSupportedProgressionTypes());
        $this->assertContains('cardio_progression', $cardioStrategy->getSupportedProgressionTypes());
    }

    /** @test */
    public function it_integrates_exercise_strategies_with_lift_log_presenter()
    {
        // Regular exercise
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $regularLiftLog = LiftLog::factory()->create([
            'exercise_id' => $regularExercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $regularLiftLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);

        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $regularWeightDisplay = $regularStrategy->formatWeightDisplay($regularLiftLog);
        $this->assertStringContainsString('lbs', $regularWeightDisplay);

        // Banded exercise
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $bandedLiftLog = LiftLog::factory()->create([
            'exercise_id' => $bandedExercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $bandedLiftLog->id,
            'weight' => 0,
            'reps' => 8,
            'band_color' => 'red',
        ]);

        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bandedWeightDisplay = $bandedStrategy->formatWeightDisplay($bandedLiftLog);
        $this->assertStringContainsString('Band:', $bandedWeightDisplay);

        // Bodyweight exercise
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $bodyweightLiftLog = LiftLog::factory()->create([
            'exercise_id' => $bodyweightExercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $bodyweightLiftLog->id,
            'weight' => 25,
            'reps' => 10,
        ]);

        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);
        $bodyweightWeightDisplay = $bodyweightStrategy->formatWeightDisplay($bodyweightLiftLog);
        $this->assertStringContainsString('Bodyweight', $bodyweightWeightDisplay);

        // Cardio exercise
        $cardioExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio'
        ]);

        $cardioLiftLog = LiftLog::factory()->create([
            'exercise_id' => $cardioExercise->id,
            'user_id' => $this->user->id,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $cardioLiftLog->id,
            'weight' => 0,
            'reps' => 500, // Distance in meters
        ]);

        $cardioStrategy = ExerciseTypeFactory::create($cardioExercise);
        $cardioWeightDisplay = $cardioStrategy->formatWeightDisplay($cardioLiftLog);
        $this->assertStringContainsString('500m', $cardioWeightDisplay);
    }

    /** @test */
    public function it_maintains_backward_compatibility_with_existing_exercise_methods()
    {
        $bandedResistanceExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $bandedAssistanceExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_assistance'
        ]);

        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        // Test backward compatibility methods still work
        $this->assertTrue($bandedResistanceExercise->isBandedResistance());
        $this->assertFalse($bandedResistanceExercise->isBandedAssistance());
        
        $this->assertTrue($bandedAssistanceExercise->isBandedAssistance());
        $this->assertFalse($bandedAssistanceExercise->isBandedResistance());
        
        $this->assertFalse($regularExercise->isBandedResistance());
        $this->assertFalse($regularExercise->isBandedAssistance());

        // Test that strategies are created correctly
        $resistanceStrategy = ExerciseTypeFactory::create($bandedResistanceExercise);
        $assistanceStrategy = ExerciseTypeFactory::create($bandedAssistanceExercise);
        $regularStrategy = ExerciseTypeFactory::create($regularExercise);

        $this->assertEquals('banded_resistance', $resistanceStrategy->getTypeName());
        $this->assertEquals('banded_assistance', $assistanceStrategy->getTypeName());
        $this->assertEquals('regular', $regularStrategy->getTypeName());
    }

    /** @test */
    public function it_handles_strategy_creation_for_exercises_with_mixed_properties()
    {
        // Exercise with both bodyweight and band_type (band_type should take precedence)
        $mixedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $strategy = ExerciseTypeFactory::create($mixedExercise);
        
        // Should create banded strategy (band_type takes precedence)
        $this->assertEquals('banded_resistance', $strategy->getTypeName());
        $this->assertFalse($strategy->canCalculate1RM());
    }

    /** @test */
    public function it_processes_lift_data_correctly_across_all_exercise_types()
    {
        $regularExercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);

        $bandedExercise = Exercise::factory()->create([
            'exercise_type' => 'banded_resistance'
        ]);

        $bodyweightExercise = Exercise::factory()->create([
            'exercise_type' => 'bodyweight'
        ]);

        $cardioExercise = Exercise::factory()->create([
            'exercise_type' => 'cardio'
        ]);

        $regularStrategy = ExerciseTypeFactory::create($regularExercise);
        $bandedStrategy = ExerciseTypeFactory::create($bandedExercise);
        $bodyweightStrategy = ExerciseTypeFactory::create($bodyweightExercise);
        $cardioStrategy = ExerciseTypeFactory::create($cardioExercise);

        $inputData = [
            'weight' => 100,
            'reps' => 500, // Distance for cardio
            'band_color' => 'red',
        ];

        // Regular exercise should keep weight, nullify band_color
        $regularProcessed = $regularStrategy->processLiftData($inputData);
        $this->assertEquals(100, $regularProcessed['weight']);
        $this->assertNull($regularProcessed['band_color']);

        // Banded exercise should set weight to 0, keep band_color
        $bandedProcessed = $bandedStrategy->processLiftData($inputData);
        $this->assertEquals(0, $bandedProcessed['weight']);
        $this->assertEquals('red', $bandedProcessed['band_color']);

        // Bodyweight exercise should keep weight (as extra weight), nullify band_color
        $bodyweightProcessed = $bodyweightStrategy->processLiftData($inputData);
        $this->assertEquals(100, $bodyweightProcessed['weight']);
        $this->assertNull($bodyweightProcessed['band_color']);

        // Cardio exercise should force weight to 0, nullify band_color
        $cardioProcessed = $cardioStrategy->processLiftData($inputData);
        $this->assertEquals(0, $cardioProcessed['weight']);
        $this->assertNull($cardioProcessed['band_color']);
        $this->assertEquals(500, $cardioProcessed['reps']); // Distance preserved
    }

    /** @test */
    public function it_processes_exercise_data_correctly_across_all_exercise_types()
    {
        // Clear cache before each strategy creation to avoid cache key conflicts
        ExerciseTypeFactory::clearCache();
        $regularStrategy = ExerciseTypeFactory::create(Exercise::factory()->make([
            'exercise_type' => 'regular'
        ]));

        ExerciseTypeFactory::clearCache();
        $bandedStrategy = ExerciseTypeFactory::create(Exercise::factory()->make([
            'exercise_type' => 'banded_resistance'
        ]));

        ExerciseTypeFactory::clearCache();
        $bodyweightStrategy = ExerciseTypeFactory::create(Exercise::factory()->make([
            'exercise_type' => 'bodyweight'
        ]));

        ExerciseTypeFactory::clearCache();
        $cardioStrategy = ExerciseTypeFactory::create(Exercise::factory()->make([
            'exercise_type' => 'cardio'
        ]));

        $inputData = [
            'title' => 'Test Exercise',
            'exercise_type' => 'banded_resistance'
        ];

        // Regular exercise should set exercise_type to regular
        $regularProcessed = $regularStrategy->processExerciseData($inputData);
        $this->assertEquals('regular', $regularProcessed['exercise_type']);

        // Banded exercise
        $bandedProcessed = $bandedStrategy->processExerciseData($inputData);
        $this->assertEquals('banded_resistance', $bandedProcessed['exercise_type']);

        // Bodyweight exercise
        $bodyweightProcessed = $bodyweightStrategy->processExerciseData($inputData);
        $this->assertEquals('bodyweight', $bodyweightProcessed['exercise_type']);

        // Cardio exercise
        $cardioProcessed = $cardioStrategy->processExerciseData($inputData);
        $this->assertEquals('cardio', $cardioProcessed['exercise_type']);
    }
}