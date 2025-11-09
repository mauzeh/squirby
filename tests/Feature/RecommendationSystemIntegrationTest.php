<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\RecommendationEngine;
use App\Services\ActivityAnalysisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecommendationSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationEngine $recommendationEngine;
    private ActivityAnalysisService $activityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityService = new ActivityAnalysisService();
        $this->recommendationEngine = new RecommendationEngine($this->activityService);
    }

    /** @test */
    public function complete_recommendation_workflow_with_real_user_activity()
    {
        $user = User::factory()->create();

        // Create exercises with intelligence data
        $benchPress = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press'
        ]);
        
        $benchIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $benchPress->id,
            'canonical_name' => 'bench_press',
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
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
                        'name' => 'triceps_brachii',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        $pullUp = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Pull Up',
            'canonical_name' => 'pull_up'
        ]);
        
        $pullUpIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $pullUp->id,
            'canonical_name' => 'pull_up',
            'movement_archetype' => 'pull',
            'primary_mover' => 'latissimus_dorsi',
            'largest_muscle' => 'latissimus_dorsi',
            'difficulty_level' => 4,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'latissimus_dorsi',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'biceps_brachii',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'posterior_deltoid',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        $squat = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Back Squat',
            'canonical_name' => 'back_squat'
        ]);
        
        $squatIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $squat->id,
            'canonical_name' => 'back_squat',
            'movement_archetype' => 'squat',
            'primary_mover' => 'quadriceps',
            'largest_muscle' => 'quadriceps',
            'difficulty_level' => 3,
            'recovery_hours' => 72,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'quadriceps',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'gluteus_maximus',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create lift logs so all exercises can be recommended (only exercises user has performed are recommended)
        // Create old lift logs for pull-ups and squats (so they can be recommended but aren't recent)
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $pullUp->id,
            'logged_at' => Carbon::now()->subDays(20)
        ]);
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $squat->id,
            'logged_at' => Carbon::now()->subDays(20)
        ]);

        // Create user activity - heavy bench press usage, no pull or squat work
        $benchLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $benchPress->id,
            'logged_at' => Carbon::now()->subDays(2)
        ]);
        
        LiftSet::factory()->count(4)->create([
            'lift_log_id' => $benchLog1->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $benchLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $benchLog2->id,
            'reps' => 10,
            'weight' => 175
        ]);

        // Get recommendations
        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 3);

        // Verify recommendations
        $this->assertNotEmpty($recommendations);
        $this->assertLessThanOrEqual(3, count($recommendations));

        // Extract exercise IDs from recommendations
        $recommendedExerciseIds = array_map(fn($rec) => $rec['exercise']->id, $recommendations);

        // Pull-ups should be highly recommended (underworked pulling muscles)
        $this->assertContains($pullUp->id, $recommendedExerciseIds);
        
        // Squats should be recommended (underworked legs)
        $this->assertContains($squat->id, $recommendedExerciseIds);

        // Verify recommendation structure
        foreach ($recommendations as $recommendation) {
            $this->assertArrayHasKey('exercise', $recommendation);
            $this->assertArrayHasKey('intelligence', $recommendation);
            $this->assertArrayHasKey('score', $recommendation);
            $this->assertArrayHasKey('reasoning', $recommendation);
            
            $this->assertInstanceOf(Exercise::class, $recommendation['exercise']);
            $this->assertInstanceOf(ExerciseIntelligence::class, $recommendation['intelligence']);
            $this->assertIsFloat($recommendation['score']);
            $this->assertIsArray($recommendation['reasoning']);
        }

        // Verify reasoning includes muscle balance information
        $pullUpRecommendation = collect($recommendations)->firstWhere('exercise.id', $pullUp->id);
        $this->assertNotNull($pullUpRecommendation);
        
        $reasoning = implode(' ', $pullUpRecommendation['reasoning']);
        $this->assertStringContainsString('latissimus dorsi', $reasoning);
    }

    /** @test */
    public function muscle_workload_calculations_with_real_exercise_data()
    {
        $user = User::factory()->create();

        // Create compound exercise with multiple muscle groups
        $deadlift = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Deadlift',
            'canonical_name' => 'deadlift'
        ]);
        
        $deadliftIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $deadlift->id,
            'canonical_name' => 'deadlift',
            'movement_archetype' => 'hinge',
            'primary_mover' => 'gluteus_maximus',
            'largest_muscle' => 'gluteus_maximus',
            'difficulty_level' => 4,
            'recovery_hours' => 72,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'gluteus_maximus',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'hamstrings',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'erector_spinae',
                        'role' => 'synergist',
                        'contraction_type' => 'isotonic'
                    ],
                    [
                        'name' => 'latissimus_dorsi',
                        'role' => 'stabilizer',
                        'contraction_type' => 'isometric'
                    ],
                    [
                        'name' => 'trapezius',
                        'role' => 'stabilizer',
                        'contraction_type' => 'isometric'
                    ]
                ]
            ]
        ]);

        // Create isolation exercise for comparison
        $bicepCurl = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bicep Curl',
            'canonical_name' => 'bicep_curl'
        ]);
        
        $bicepIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $bicepCurl->id,
            'canonical_name' => 'bicep_curl',
            'movement_archetype' => 'pull',
            'primary_mover' => 'biceps_brachii',
            'largest_muscle' => 'biceps_brachii',
            'difficulty_level' => 2,
            'recovery_hours' => 24,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'biceps_brachii',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create workout with heavy deadlifts
        $deadliftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $deadlift->id,
            'logged_at' => Carbon::now()->subDays(1)
        ]);
        
        LiftSet::factory()->count(5)->create([
            'lift_log_id' => $deadliftLog->id,
            'reps' => 5,
            'weight' => 315
        ]);

        // Create workout with light bicep curls
        $bicepLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bicepCurl->id,
            'logged_at' => Carbon::now()->subDays(1)
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $bicepLog->id,
            'reps' => 12,
            'weight' => 25
        ]);

        // Analyze user activity
        $analysis = $this->activityService->analyzeLiftLogs($user->id);

        // Verify muscle workload calculations
        $this->assertGreaterThan(0, $analysis->getMuscleWorkloadScore('gluteus_maximus'));
        $this->assertGreaterThan(0, $analysis->getMuscleWorkloadScore('hamstrings'));
        $this->assertGreaterThan(0, $analysis->getMuscleWorkloadScore('erector_spinae'));
        $this->assertGreaterThan(0, $analysis->getMuscleWorkloadScore('latissimus_dorsi'));
        $this->assertGreaterThan(0, $analysis->getMuscleWorkloadScore('biceps_brachii'));

        // Primary movers should have higher workload than stabilizers
        $this->assertGreaterThan(
            $analysis->getMuscleWorkloadScore('latissimus_dorsi'),
            $analysis->getMuscleWorkloadScore('gluteus_maximus')
        );
        
        // Compound exercise muscles should have higher workload than isolation
        $this->assertGreaterThan(
            $analysis->getMuscleWorkloadScore('biceps_brachii'),
            $analysis->getMuscleWorkloadScore('gluteus_maximus')
        );

        // Verify movement archetype tracking
        $this->assertEquals(1, $analysis->getArchetypeFrequency('hinge'));
        $this->assertEquals(1, $analysis->getArchetypeFrequency('pull'));

        // Verify recent exercises tracking
        $this->assertTrue($analysis->wasExerciseRecentlyPerformed($deadlift->id));
        $this->assertTrue($analysis->wasExerciseRecentlyPerformed($bicepCurl->id));
    }

    /** @test */
    public function recovery_period_filtering_logic_with_real_data()
    {
        $user = User::factory()->create();

        // Create exercise with 48-hour recovery period
        $benchPress = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press'
        ]);
        
        $benchIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $benchPress->id,
            'canonical_name' => 'bench_press',
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
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

        // Create exercise with 24-hour recovery period
        $bicepCurl = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bicep Curl',
            'canonical_name' => 'bicep_curl'
        ]);
        
        $bicepIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $bicepCurl->id,
            'canonical_name' => 'bicep_curl',
            'movement_archetype' => 'pull',
            'primary_mover' => 'biceps_brachii',
            'largest_muscle' => 'biceps_brachii',
            'difficulty_level' => 2,
            'recovery_hours' => 24,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'biceps_brachii',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create exercise that doesn't share muscles (should always be recommended)
        $squat = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Back Squat',
            'canonical_name' => 'back_squat'
        ]);
        
        $squatIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $squat->id,
            'canonical_name' => 'back_squat',
            'movement_archetype' => 'squat',
            'primary_mover' => 'quadriceps',
            'largest_muscle' => 'quadriceps',
            'difficulty_level' => 3,
            'recovery_hours' => 72,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'quadriceps',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create old lift log for squat so it can be recommended
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $squat->id,
            'logged_at' => Carbon::now()->subDays(20)
        ]);

        // Create recent bench press workout (within 48-hour recovery)
        $benchLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $benchPress->id,
            'logged_at' => Carbon::now()->subHours(36) // 36 hours ago, still in recovery
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $benchLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        // Create bicep curl workout (outside 24-hour recovery)
        $bicepLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bicepCurl->id,
            'logged_at' => Carbon::now()->subHours(30) // 30 hours ago, outside 24-hour recovery
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $bicepLog->id,
            'reps' => 12,
            'weight' => 25
        ]);

        // Get recommendations
        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 5);

        // Extract recommended exercise IDs
        $recommendedExerciseIds = array_map(fn($rec) => $rec['exercise']->id, $recommendations);

        // Bench press should NOT be recommended (still in recovery period)
        $this->assertNotContains($benchPress->id, $recommendedExerciseIds);

        // Bicep curl SHOULD be recommended (outside recovery period)
        $this->assertContains($bicepCurl->id, $recommendedExerciseIds);

        // Squat should be recommended (no muscle overlap, no recovery conflict)
        $this->assertContains($squat->id, $recommendedExerciseIds);

        // Test edge case: exactly at recovery boundary
        $edgeLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bicepCurl->id,
            'logged_at' => Carbon::now()->subHours(24) // Exactly 24 hours ago
        ]);
        
        LiftSet::factory()->create(['lift_log_id' => $edgeLog->id]);

        // Get fresh recommendations
        $edgeRecommendations = $this->recommendationEngine->getRecommendations($user->id, 5);
        $edgeRecommendedIds = array_map(fn($rec) => $rec['exercise']->id, $edgeRecommendations);

        // Bicep curl should still be recommended (exactly at recovery boundary)
        $this->assertContains($bicepCurl->id, $edgeRecommendedIds);
    }
}