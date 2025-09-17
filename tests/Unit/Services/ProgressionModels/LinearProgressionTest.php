<?php

namespace Tests\Unit\Services\ProgressionModels;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\OneRepMaxCalculatorService;
use App\Services\ProgressionModels\LinearProgression;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinearProgressionTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $exercise;
    private $oneRepMaxCalculatorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create();
        $this->oneRepMaxCalculatorService = $this->createMock(OneRepMaxCalculatorService::class);
    }

    public function test_suggest_returns_null_when_no_recent_lift_logs()
    {
        $progressionModel = new LinearProgression($this->oneRepMaxCalculatorService);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);
        $this->assertNull($suggestion);
    }

    public function test_suggest_returns_suggestion_based_on_one_rep_max()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);

        $this->oneRepMaxCalculatorService->method('calculateOneRepMax')->willReturn(112.5);
        $this->oneRepMaxCalculatorService->method('getWeightFromOneRepMax')->willReturn(102.5);

        $progressionModel = new LinearProgression($this->oneRepMaxCalculatorService);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(110, $suggestion->suggestedWeight);
        $this->assertEquals(5, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
        $this->assertEquals(100, $suggestion->lastWeight);
        $this->assertEquals(5, $suggestion->lastReps);
        $this->assertEquals(3, $suggestion->lastSets);
    }

    public function test_suggest_increases_weight_if_reps_are_higher_than_target()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 8,
        ]);

        $this->oneRepMaxCalculatorService->method('calculateOneRepMax')->willReturn(120.0);
        $this->oneRepMaxCalculatorService->method('getWeightFromOneRepMax')->willReturn(105.0);

        $progressionModel = new LinearProgression($this->oneRepMaxCalculatorService);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(110, $suggestion->suggestedWeight); // 105 + 5 (RESOLUTION)
    }

    public function test_suggest_returns_false_when_1rm_cannot_be_calculated()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0, // Invalid weight
            'reps' => 0,   // Invalid reps
        ]);

        $this->oneRepMaxCalculatorService->method('calculateOneRepMax')->will($this->throwException(new \Exception()));

        $progressionModel = new LinearProgression($this->oneRepMaxCalculatorService);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion->suggestedWeight);
    }
}