<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OneRepMaxCalculatorService;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\BodyLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OneRepMaxCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $calculator;
    protected $user;
    protected $bodyweightType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OneRepMaxCalculatorService();
        $this->user = User::factory()->create();
        $this->bodyweightType = MeasurementType::firstOrCreate(
            ['name' => 'Bodyweight', 'user_id' => $this->user->id],
            ['default_unit' => 'lbs']
        );

        // Log a bodyweight for the user
        BodyLog::create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->bodyweightType->id,
            'value' => 180, // Example bodyweight in lbs
            'logged_at' => Carbon::now(),
        ]);
    }

    /** @test */
    public function it_calculates_one_rep_max_for_a_single_set_correctly()
    {
        $weight = 100;
        $reps = 5;
        $expected1RM = 100 * (1 + (0.0333 * 5));

        $this->assertEquals($expected1RM, $this->calculator->calculateOneRepMax($weight, $reps));
    }

    /** @test */
    public function it_returns_the_weight_when_reps_is_one()
    {
        $weight = 100;
        $reps = 1;

        $this->assertEquals($weight, $this->calculator->calculateOneRepMax($weight, $reps));
    }

    /** @test */
    public function it_calculates_lift_log_one_rep_max_for_uniform_sets_correctly()
    {
        $liftLog = LiftLog::factory()->create();
        $liftLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 2'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 3'],
        ]);

        $expected1RM = 100 * (1 + (0.0333 * 5));

        $this->assertEquals($expected1RM, $this->calculator->getLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_calculates_lift_log_one_rep_max_for_non_uniform_sets_using_first_set()
    {
        $liftLog = LiftLog::factory()->create();
        $liftLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'],
            ['weight' => 110, 'reps' => 3, 'notes' => 'Set 2'],
            ['weight' => 120, 'reps' => 1, 'notes' => 'Set 3'],
        ]);

        // Should use the first set's data for calculation
        $expected1RM = 100 * (1 + (0.0333 * 5));

        $this->assertEquals($expected1RM, $this->calculator->getLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_calculates_best_lift_log_one_rep_max_correctly()
    {
        $liftLog = LiftLog::factory()->create();
        $liftLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'], // 116.65
            ['weight' => 110, 'reps' => 3, 'notes' => 'Set 2'], // 120.989
            ['weight' => 120, 'reps' => 1, 'notes' => 'Set 3'], // 120
        ]);

        $expectedBest1RM = 110 * (1 + (0.0333 * 3));

        $this->assertEquals($expectedBest1RM, $this->calculator->getBestLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_returns_zero_for_empty_lift_sets_for_lift_log_one_rep_max()
    {
        $liftLog = LiftLog::factory()->create();

        $this->assertEquals(0, $this->calculator->getLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_returns_zero_for_empty_lift_sets_for_best_lift_log_one_rep_max()
    {
        $liftLog = LiftLog::factory()->create();

        $this->assertEquals(0, $this->calculator->getBestLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_calculates_one_rep_max_for_bodyweight_exercise_correctly()
    {
        $weight = 0; // Bodyweight exercise, no external weight
        $reps = 5;
        $expected1RM = 180 * (1 + (0.0333 * 5)); // Assuming bodyweight is 180

        $this->assertEquals($expected1RM, $this->calculator->calculateOneRepMax($weight, $reps, true, $this->user->id, Carbon::now()));
    }

    /** @test */
    public function it_calculates_lift_log_one_rep_max_for_bodyweight_exercise_correctly()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id, 'exercise_id' => $exercise->id, 'logged_at' => Carbon::now()]);
        $liftLog->liftSets()->createMany([
            ['weight' => 0, 'reps' => 5, 'notes' => 'Set 1'],
            ['weight' => 0, 'reps' => 5, 'notes' => 'Set 2'],
        ]);

        $expected1RM = 180 * (1 + (0.0333 * 5)); // Assuming bodyweight is 180

        $this->assertEquals($expected1RM, $this->calculator->getLiftLogOneRepMax($liftLog));
    }

    /** @test */
    public function it_calculates_best_lift_log_one_rep_max_for_bodyweight_exercise_correctly()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id, 'exercise_id' => $exercise->id, 'logged_at' => Carbon::now()]);
        $liftLog->liftSets()->createMany([
            ['weight' => 0, 'reps' => 5, 'notes' => 'Set 1'], // 180 * (1 + (0.0333 * 5)) = 209.97
            ['weight' => 0, 'reps' => 8, 'notes' => 'Set 2'], // 180 * (1 + (0.0333 * 8)) = 227.952
            ['weight' => 0, 'reps' => 3, 'notes' => 'Set 3'], // 180 * (1 + (0.0333 * 3)) = 197.982
        ]);

        $expectedBest1RM = 180 * (1 + (0.0333 * 8)); // Best is from 8 reps

        $this->assertEquals($expectedBest1RM, $this->calculator->getBestLiftLogOneRepMax($liftLog));
    }
}
