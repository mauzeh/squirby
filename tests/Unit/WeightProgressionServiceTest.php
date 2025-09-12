<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WeightProgressionService;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\OneRepMaxCalculatorService;

class WeightProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WeightProgressionService $service;
    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oneRepMaxCalculatorService = new OneRepMaxCalculatorService();
        $this->service = new WeightProgressionService($this->oneRepMaxCalculatorService);
    }

    /** @test */
    public function it_suggests_default_weight_when_no_history_exists()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        $this->assertFalse($suggestedWeight);
    }

    /** @test */
    public function it_suggests_incremented_weight_when_history_exists_within_lookback_period()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // Create a recent LiftLog with a set matching target reps
        $liftLog = LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);

        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        // Calculate expected 1RM from historical data
        $historicalWeight = 100.0;
        $historicalReps = 5;
        $expected1RM = $this->oneRepMaxCalculatorService->calculateOneRepMax($historicalWeight, $historicalReps);

        // Calculate expected predicted weight for target reps
        $expectedPredictedWeight = $this->oneRepMaxCalculatorService->getWeightFromOneRepMax($expected1RM, 5);

        $this->assertEquals($expectedPredictedWeight + WeightProgressionService::DEFAULT_INCREMENT, $suggestedWeight);
    }

    /** @test */
    public function it_does_not_suggest_weight_when_history_is_too_old()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // Create an old LiftLog
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subWeeks(WeightProgressionService::LOOKBACK_WEEKS + 1),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 5,
            'weight' => 100.0,
        ]);

        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        $this->assertFalse($suggestedWeight); // Should fall back to default
    }

    /** @test */
    public function it_only_considers_non_bodyweight_movements()
    {
        $user = User::factory()->create();
        $bodyweightExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);
        $nonBodyweightExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // Create a recent LiftLog for a bodyweight exercise
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bodyweightExercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 5,
            'weight' => 100.0,
        ]);

        // Should still suggest default weight for nonBodyweightExercise as bodyweight history is ignored
        $suggestedWeight = $this->service->suggestNextWeight($user->id, $nonBodyweightExercise->id, 5);

        $this->assertFalse($suggestedWeight);
    }

    /** @test */
    public function it_finds_heaviest_weight_for_target_reps_from_multiple_sets()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $liftLog = LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 90.0,
        ]), 'liftSets')->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->has(LiftSet::factory()->state([
            'reps' => 8,
            'weight' => 80.0,
        ]), 'liftSets')->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);

        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        // Calculate expected 1RM from historical data (heaviest for 5 reps)
        $historicalWeight = 100.0;
        $historicalReps = 5;
        $expected1RM = $this->oneRepMaxCalculatorService->calculateOneRepMax($historicalWeight, $historicalReps);

        // Calculate expected predicted weight for target reps
        $expectedPredictedWeight = $this->oneRepMaxCalculatorService->getWeightFromOneRepMax($expected1RM, 5);

        $this->assertEquals($expectedPredictedWeight + WeightProgressionService::DEFAULT_INCREMENT, $suggestedWeight);
    }

    /** @test */
    public function it_handles_multiple_lift_logs_for_same_exercise_and_date()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // First LiftLog (older, lighter)
        $liftLog1 = LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 95.0,
        ]), 'liftSets')->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(2),
        ]);

        // Second LiftLog (more recent, heavier)
        $liftLog2 = LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 105.0,
        ]), 'liftSets')->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);

        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        // Calculate expected 1RM from historical data (heaviest overall)
        $historicalWeight = 105.0;
        $historicalReps = 5;
        $expected1RM = $this->oneRepMaxCalculatorService->calculateOneRepMax($historicalWeight, $historicalReps);

        // Calculate expected predicted weight for target reps
        $expectedPredictedWeight = $this->oneRepMaxCalculatorService->getWeightFromOneRepMax($expected1RM, 5);

        $this->assertEquals($expectedPredictedWeight + WeightProgressionService::DEFAULT_INCREMENT, $suggestedWeight);
    }

    /** @test */
    public function it_predicts_weight_even_if_no_sets_exactly_match_target_reps()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 80.0,
        ]);

        // A set with 8 reps exists, so a 1RM can be calculated and a prediction made for 5 reps
        $suggestedWeight = $this->service->suggestNextWeight($user->id, $exercise->id, 5);

        $this->assertIsFloat($suggestedWeight);
        $this->assertGreaterThan(0, $suggestedWeight);
    }
}