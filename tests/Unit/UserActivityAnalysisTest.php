<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserActivityAnalysis;
use Carbon\Carbon;

class UserActivityAnalysisTest extends TestCase
{
    /** @test */
    public function get_muscle_workload_score_returns_correct_values()
    {
        $muscleWorkload = [
            'pectoralis_major' => 0.8,
            'quadriceps' => 0.5,
            'biceps_brachii' => 0.0
        ];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: $muscleWorkload,
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );

        $this->assertEquals(0.8, $analysis->getMuscleWorkloadScore('pectoralis_major'));
        $this->assertEquals(0.5, $analysis->getMuscleWorkloadScore('quadriceps'));
        $this->assertEquals(0.0, $analysis->getMuscleWorkloadScore('biceps_brachii'));
        $this->assertEquals(0.0, $analysis->getMuscleWorkloadScore('nonexistent_muscle'));
    }

    /** @test */
    public function get_archetype_frequency_returns_correct_values()
    {
        $movementArchetypes = [
            'push' => 5,
            'pull' => 3,
            'squat' => 2
        ];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: $movementArchetypes,
            recentExercises: [],
            analysisDate: Carbon::now()
        );

        $this->assertEquals(5, $analysis->getArchetypeFrequency('push'));
        $this->assertEquals(3, $analysis->getArchetypeFrequency('pull'));
        $this->assertEquals(2, $analysis->getArchetypeFrequency('squat'));
        $this->assertEquals(0, $analysis->getArchetypeFrequency('hinge'));
    }

    /** @test */
    public function was_exercise_recently_performed_returns_correct_values()
    {
        $recentExercises = [1, 3, 5, 7];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: $recentExercises,
            analysisDate: Carbon::now()
        );

        $this->assertTrue($analysis->wasExerciseRecentlyPerformed(1));
        $this->assertTrue($analysis->wasExerciseRecentlyPerformed(3));
        $this->assertTrue($analysis->wasExerciseRecentlyPerformed(5));
        $this->assertTrue($analysis->wasExerciseRecentlyPerformed(7));
        $this->assertFalse($analysis->wasExerciseRecentlyPerformed(2));
        $this->assertFalse($analysis->wasExerciseRecentlyPerformed(10));
    }

    /** @test */
    public function get_days_since_last_workout_returns_null_when_muscle_never_worked()
    {
        $analysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now(),
            muscleLastWorked: []
        );

        $this->assertNull($analysis->getDaysSinceLastWorkout('pectoralis_major'));
    }

    /** @test */
    public function get_days_since_last_workout_calculates_correctly_with_carbon_dates()
    {
        $analysisDate = Carbon::parse('2024-01-15');
        $lastWorkoutDate = Carbon::parse('2024-01-10'); // 5 days ago

        $muscleLastWorked = [
            'pectoralis_major' => $lastWorkoutDate,
        ];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: $analysisDate,
            muscleLastWorked: $muscleLastWorked
        );

        $this->assertEquals(5, $analysis->getDaysSinceLastWorkout('pectoralis_major'));
    }

    /** @test */
    public function get_days_since_last_workout_falls_back_to_workload_estimation()
    {
        $muscleWorkload = [
            'pectoralis_major' => 0.8, // High workload = recent work
            'quadriceps' => 0.2,       // Low workload = older work
            'biceps_brachii' => 0.0    // No workload = never worked
        ];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: $muscleWorkload,
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now(),
            muscleLastWorked: [] // No date data available
        );

        // High workload should estimate recent work (low days)
        $pectoralisDays = $analysis->getDaysSinceLastWorkout('pectoralis_major');
        $this->assertIsFloat($pectoralisDays);
        $this->assertLessThan(10, $pectoralisDays);

        // Low workload should estimate older work (higher days)
        $quadricepsDays = $analysis->getDaysSinceLastWorkout('quadriceps');
        $this->assertIsFloat($quadricepsDays);
        $this->assertGreaterThan(15, $quadricepsDays);

        // No workload should return null
        $this->assertNull($analysis->getDaysSinceLastWorkout('biceps_brachii'));
    }

    /** @test */
    public function get_days_since_last_workout_estimation_bounds_correctly()
    {
        $muscleWorkload = [
            'max_workload' => 1.0,  // Should give 0 days
            'min_workload' => 0.1   // Should give close to 31 days
        ];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: $muscleWorkload,
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now(),
            muscleLastWorked: []
        );

        $maxWorkloadDays = $analysis->getDaysSinceLastWorkout('max_workload');
        $minWorkloadDays = $analysis->getDaysSinceLastWorkout('min_workload');

        $this->assertGreaterThanOrEqual(0, $maxWorkloadDays);
        $this->assertLessThanOrEqual(31, $minWorkloadDays);
        $this->assertLessThan($minWorkloadDays, $maxWorkloadDays);
    }

    /** @test */
    public function constructor_sets_all_properties_correctly()
    {
        $muscleWorkload = ['pectoralis_major' => 0.5];
        $movementArchetypes = ['push' => 3];
        $recentExercises = [1, 2, 3];
        $analysisDate = Carbon::now();
        $muscleLastWorked = ['pectoralis_major' => Carbon::yesterday()];

        $analysis = new UserActivityAnalysis(
            muscleWorkload: $muscleWorkload,
            movementArchetypes: $movementArchetypes,
            recentExercises: $recentExercises,
            analysisDate: $analysisDate,
            muscleLastWorked: $muscleLastWorked
        );

        $this->assertEquals($muscleWorkload, $analysis->muscleWorkload);
        $this->assertEquals($movementArchetypes, $analysis->movementArchetypes);
        $this->assertEquals($recentExercises, $analysis->recentExercises);
        $this->assertEquals($analysisDate, $analysis->analysisDate);
        $this->assertEquals($muscleLastWorked, $analysis->muscleLastWorked);
    }
}