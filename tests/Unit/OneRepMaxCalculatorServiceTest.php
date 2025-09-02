<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OneRepMaxCalculatorService;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OneRepMaxCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OneRepMaxCalculatorService();
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
    public function it_calculates_workout_one_rep_max_for_uniform_sets_correctly()
    {
        $workout = Workout::factory()->create();
        $workout->workoutSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 2'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 3'],
        ]);

        $expected1RM = 100 * (1 + (0.0333 * 5));

        $this->assertEquals($expected1RM, $this->calculator->getWorkoutOneRepMax($workout));
    }

    /** @test */
    public function it_calculates_workout_one_rep_max_for_non_uniform_sets_using_first_set()
    {
        $workout = Workout::factory()->create();
        $workout->workoutSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'],
            ['weight' => 110, 'reps' => 3, 'notes' => 'Set 2'],
            ['weight' => 120, 'reps' => 1, 'notes' => 'Set 3'],
        ]);

        // Should use the first set's data for calculation
        $expected1RM = 100 * (1 + (0.0333 * 5));

        $this->assertEquals($expected1RM, $this->calculator->getWorkoutOneRepMax($workout));
    }

    /** @test */
    public function it_calculates_best_workout_one_rep_max_correctly()
    {
        $workout = Workout::factory()->create();
        $workout->workoutSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Set 1'], // 116.65
            ['weight' => 110, 'reps' => 3, 'notes' => 'Set 2'], // 120.99
            ['weight' => 120, 'reps' => 1, 'notes' => 'Set 3'], // 123.996
        ]);

        $expectedBest1RM = 120 * (1 + (0.0333 * 1));

        $this->assertEquals($expectedBest1RM, $this->calculator->getBestWorkoutOneRepMax($workout));
    }

    /** @test */
    public function it_returns_zero_for_empty_workout_sets_for_workout_one_rep_max()
    {
        $workout = Workout::factory()->create();

        $this->assertEquals(0, $this->calculator->getWorkoutOneRepMax($workout));
    }

    /** @test */
    public function it_returns_zero_for_empty_workout_sets_for_best_workout_one_rep_max()
    {
        $workout = Workout::factory()->create();

        $this->assertEquals(0, $this->calculator->getBestWorkoutOneRepMax($workout));
    }
}
