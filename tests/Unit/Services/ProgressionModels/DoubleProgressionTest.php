<?php

namespace Tests\Unit\Services\ProgressionModels;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\OneRepMaxCalculatorService;
use App\Services\ProgressionModels\DoubleProgression;
use App\Services\TrainingProgressionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleProgressionTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create();
    }

    public function test_suggest_returns_null_when_no_recent_lift_logs()
    {
        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);
        $this->assertNull($suggestion);
    }

    public function test_suggest_increases_reps_when_in_rep_range()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 10,
        ]);

        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(100, $suggestion->suggestedWeight);
        $this->assertEquals(11, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_suggest_increases_weight_when_max_reps_reached()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 12,
        ]);

        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(105, $suggestion->suggestedWeight);
        $this->assertEquals(8, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_suggest_increases_reps_when_below_rep_range()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 7,
        ]);

        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(100, $suggestion->suggestedWeight);
        $this->assertEquals(8, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_suggest_increases_weight_when_above_rep_range()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 13,
        ]);

        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(105, $suggestion->suggestedWeight);
        $this->assertEquals(8, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_bug_repro_suggests_correct_reps()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 40,
            'reps' => 11,
        ]);

        $progressionModel = new DoubleProgression($this->createMock(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(40, $suggestion->suggestedWeight);
        $this->assertEquals(12, $suggestion->reps);
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_training_progression_service_chooses_correct_model()
    {
        $exercise1 = Exercise::factory()->create();
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => Carbon::now(),
        ]);
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 40,
            'reps' => 11,
        ]);

        $exercise2 = Exercise::factory()->create();
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => Carbon::now(),
        ]);
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 80,
            'reps' => 5,
        ]);

        $mockOneRepMaxService = $this->createMock(OneRepMaxCalculatorService::class);
        $mockOneRepMaxService->method('calculateOneRepMax')->willReturn(100.0);
        $mockOneRepMaxService->method('getWeightFromOneRepMax')->willReturn(85.0);

        $service = new TrainingProgressionService($mockOneRepMaxService);

        // Test exercise 1 (should use DoubleProgression)
        $suggestion1 = $service->getSuggestionDetails($this->user->id, $exercise1->id);
        $this->assertEquals(12, $suggestion1->reps);

        // Test exercise 2 (should use LinearProgression)
        $suggestion2 = $service->getSuggestionDetails($this->user->id, $exercise2->id);
        $this->assertEquals(5, $suggestion2->reps);
    }
}