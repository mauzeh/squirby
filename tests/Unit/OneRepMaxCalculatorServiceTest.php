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
use App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException;

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
    public function it_throws_exception_for_banded_exercise_in_calculate_one_rep_max()
    {
        $this->expectException(\App\Services\NotApplicableException::class);
        $this->expectExceptionMessage('1RM calculation is not applicable for banded exercises.');

        $this->calculator->calculateOneRepMax(100, 5, false, null, null, 'resistance');
    }

    /** @test */
    public function it_throws_exception_for_banded_exercise_in_get_lift_log_one_rep_max()
    {
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for banded exercises');

        $exercise = Exercise::factory()->create(['band_type' => 'resistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 5, 'band_color' => 'red']);

        $this->calculator->getLiftLogOneRepMax($liftLog);
    }

    /** @test */
    public function it_throws_exception_for_banded_exercise_in_get_best_lift_log_one_rep_max()
    {
        $this->expectException(\App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException::class);
        $this->expectExceptionMessage('1RM calculation not supported for banded exercises');

        $exercise = Exercise::factory()->create(['band_type' => 'assistance']);
        $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 5, 'band_color' => 'blue']);

        $this->calculator->getBestLiftLogOneRepMax($liftLog);
    }
}
