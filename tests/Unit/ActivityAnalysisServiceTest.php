<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\ActivityAnalysisService;
use App\Services\UserActivityAnalysis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ActivityAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivityAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityAnalysisService();
    }

    /** @test */
    public function analyze_lift_logs_returns_user_activity_analysis()
    {
        $user = User::factory()->create();
        
        $analysis = $this->service->analyzeLiftLogs($user->id);
        
        $this->assertInstanceOf(UserActivityAnalysis::class, $analysis);
        $this->assertIsArray($analysis->muscleWorkload);
        $this->assertIsArray($analysis->movementArchetypes);
        $this->assertIsArray($analysis->recentExercises);
        $this->assertInstanceOf(Carbon::class, $analysis->analysisDate);
    }

    /** @test */
    public function calculate_muscle_workload_processes_lift_logs_correctly()
    {
        $user = User::factory()->create();
        
        // Create exercise with intelligence
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'anterior_deltoid',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create recent lift log
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(2)
        ]);

        // Create lift sets
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'weight' => 100
        ]);

        $liftLogs = collect([$liftLog->load(['exercise.intelligence', 'liftSets'])]);
        $muscleWorkload = $this->service->calculateMuscleWorkload($liftLogs);

        $this->assertArrayHasKey('pectoralis_major', $muscleWorkload);
        $this->assertArrayHasKey('anterior_deltoid', $muscleWorkload);
        
        // Primary mover should have higher workload than synergist
        $this->assertGreaterThan($muscleWorkload['anterior_deltoid'], $muscleWorkload['pectoralis_major']);
        
        // Both should be between 0 and 1
        $this->assertGreaterThan(0, $muscleWorkload['pectoralis_major']);
        $this->assertLessThanOrEqual(1, $muscleWorkload['pectoralis_major']);
        $this->assertGreaterThan(0, $muscleWorkload['anterior_deltoid']);
        $this->assertLessThanOrEqual(1, $muscleWorkload['anterior_deltoid']);
    }

    /** @test */
    public function calculate_muscle_workload_handles_different_muscle_roles()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'anterior_deltoid',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'core_stabilizers',
                        'role' => 'stabilizer',
                        'contraction_type' => 'isometric'
                    ]
                ]
            ]
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDay()
        ]);

        LiftSet::factory()->count(3)->create(['lift_log_id' => $liftLog->id]);

        $liftLogs = collect([$liftLog->load(['exercise.intelligence', 'liftSets'])]);
        $muscleWorkload = $this->service->calculateMuscleWorkload($liftLogs);

        // Primary mover should have highest workload
        $this->assertGreaterThan($muscleWorkload['anterior_deltoid'], $muscleWorkload['pectoralis_major']);
        $this->assertGreaterThan($muscleWorkload['core_stabilizers'], $muscleWorkload['pectoralis_major']);
        
        // Synergist should have higher workload than stabilizer
        $this->assertGreaterThan($muscleWorkload['core_stabilizers'], $muscleWorkload['anterior_deltoid']);
    }

    /** @test */
    public function calculate_muscle_workload_applies_recency_factor()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create two separate exercises to avoid workload accumulation
        $recentExercise = Exercise::factory()->create(['user_id' => null]);
        $recentIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $recentExercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        $oldExercise = Exercise::factory()->create(['user_id' => null]);
        $oldIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $oldExercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Recent lift log (1 day ago)
        $recentLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $recentExercise->id,
            'logged_at' => Carbon::now()->subDay()
        ]);
        LiftSet::factory()->count(2)->create(['lift_log_id' => $recentLog->id]);

        // Old lift log (25 days ago)
        $oldLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $oldExercise->id,
            'logged_at' => Carbon::now()->subDays(25)
        ]);
        LiftSet::factory()->count(2)->create(['lift_log_id' => $oldLog->id]);

        $recentLogs = collect([$recentLog->load(['exercise.intelligence', 'liftSets'])]);
        $oldLogs = collect([$oldLog->load(['exercise.intelligence', 'liftSets'])]);

        $recentWorkload = $this->service->calculateMuscleWorkload($recentLogs);
        $oldWorkload = $this->service->calculateMuscleWorkload($oldLogs);

        // Verify both workloads exist
        $this->assertArrayHasKey('pectoralis_major', $recentWorkload);
        $this->assertArrayHasKey('pectoralis_major', $oldWorkload);
        
        // Recent workout should have higher workload than old workout
        $this->assertGreaterThan($oldWorkload['pectoralis_major'], $recentWorkload['pectoralis_major']);
    }

    /** @test */
    public function identify_movement_patterns_counts_archetypes_correctly()
    {
        $user = User::factory()->create();
        
        // Create exercises with different archetypes
        $pushExercise = Exercise::factory()->create(['user_id' => null]);
        $pushIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $pushExercise->id,
            'movement_archetype' => 'push'
        ]);

        $pullExercise = Exercise::factory()->create(['user_id' => null]);
        $pullIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $pullExercise->id,
            'movement_archetype' => 'pull'
        ]);

        // Create lift logs
        $pushLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $pushExercise->id,
            'logged_at' => Carbon::now()->subDay()
        ]);
        $pushLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $pushExercise->id,
            'logged_at' => Carbon::now()->subDays(2)
        ]);
        $pullLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $pullExercise->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);

        $liftLogs = collect([
            $pushLog1->load('exercise.intelligence'),
            $pushLog2->load('exercise.intelligence'),
            $pullLog->load('exercise.intelligence')
        ]);

        $archetypes = $this->service->identifyMovementPatterns($liftLogs);

        $this->assertEquals(2, $archetypes['push']);
        $this->assertEquals(1, $archetypes['pull']);
    }

    /** @test */
    public function find_recent_exercises_returns_unique_exercise_ids()
    {
        $user = User::factory()->create();
        
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);

        // Create multiple logs for same exercise
        $log1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => Carbon::now()->subDay()
        ]);
        $log2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => Carbon::now()->subDays(2)
        ]);
        $log3 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);

        $liftLogs = collect([$log1, $log2, $log3]);
        $recentExercises = $this->service->findRecentExercises($liftLogs);

        $this->assertCount(2, $recentExercises);
        $this->assertContains($exercise1->id, $recentExercises);
        $this->assertContains($exercise2->id, $recentExercises);
    }

    /** @test */
    public function analyze_lift_logs_filters_by_date_range()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'movement_archetype' => 'push'
        ]);

        // Create lift log within 31 days
        $recentLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(15)
        ]);

        // Create lift log older than 31 days
        $oldLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(35)
        ]);

        $analysis = $this->service->analyzeLiftLogs($user->id);

        // Should only include recent exercise
        $this->assertContains($exercise->id, $analysis->recentExercises);
        $this->assertEquals(1, $analysis->getArchetypeFrequency('push'));
    }

    /** @test */
    public function calculate_muscle_workload_handles_exercises_without_intelligence()
    {
        $user = User::factory()->create();
        
        // Exercise without intelligence
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDay()
        ]);

        $liftLogs = collect([$liftLog->load(['exercise.intelligence', 'liftSets'])]);
        $muscleWorkload = $this->service->calculateMuscleWorkload($liftLogs);

        // Should return empty array when no intelligence data
        $this->assertEmpty($muscleWorkload);
    }

    /** @test */
    public function calculate_muscle_workload_caps_at_one()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create many recent lift logs to test capping
        for ($i = 0; $i < 10; $i++) {
            $liftLog = LiftLog::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => Carbon::now()->subDays($i)
            ]);
            
            // Create many sets to increase intensity
            LiftSet::factory()->count(10)->create(['lift_log_id' => $liftLog->id]);
        }

        $analysis = $this->service->analyzeLiftLogs($user->id);
        
        // Workload should be capped at 1.0
        $this->assertLessThanOrEqual(1.0, $analysis->getMuscleWorkloadScore('pectoralis_major'));
    }
}