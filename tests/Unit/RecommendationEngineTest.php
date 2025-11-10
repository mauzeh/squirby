<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\RecommendationEngine;
use App\Services\ActivityAnalysisService;
use App\Services\UserActivityAnalysis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationEngine $recommendationEngine;
    private ActivityAnalysisService $mockActivityService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockActivityService = Mockery::mock(ActivityAnalysisService::class);
        $this->recommendationEngine = new RecommendationEngine($this->mockActivityService);
    }

    /**
     * Helper method to create a lift log for an exercise so it can be recommended
     * (Recommendation engine only recommends exercises the user has performed)
     */
    private function createLiftLogForExercise($userId, $exerciseId, $daysAgo = 10)
    {
        return LiftLog::factory()->create([
            'user_id' => $userId,
            'exercise_id' => $exerciseId,
            'logged_at' => Carbon::now()->subDays($daysAgo)
        ]);
    }

    /** @test */
    public function get_recommendations_returns_empty_array_when_no_exercises_with_intelligence()
    {
        $user = User::factory()->create();
        
        // Create exercises without intelligence
        Exercise::factory()->count(3)->create(['user_id' => null]);
        
        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id);

        $this->assertEmpty($recommendations);
    }

    /** @test */
    public function get_recommendations_returns_scored_exercises()
    {
        $user = User::factory()->create();
        
        // Create global exercises with intelligence
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $intelligence1 = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise1->id,
            'movement_archetype' => 'push',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
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

        $exercise2 = Exercise::factory()->create(['user_id' => null]);
        $intelligence2 = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise2->id,
            'movement_archetype' => 'pull',
            'difficulty_level' => 2,
            'recovery_hours' => 24,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'latissimus_dorsi',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create lift logs so exercises are recommended (only exercises user has performed are recommended)
        $this->createLiftLogForExercise($user->id, $exercise1->id);
        $this->createLiftLogForExercise($user->id, $exercise2->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: ['pectoralis_major' => 0.1], // Low workload = underworked
            movementArchetypes: ['push' => 1, 'pull' => 0],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 2);

        $this->assertCount(2, $recommendations);
        $this->assertArrayHasKey('exercise', $recommendations[0]);
        $this->assertArrayHasKey('intelligence', $recommendations[0]);
        $this->assertArrayHasKey('score', $recommendations[0]);
        $this->assertArrayHasKey('reasoning', $recommendations[0]);
        
        // Verify exercises are included
        $exerciseIds = array_column($recommendations, 'exercise');
        $exerciseIds = array_map(fn($ex) => $ex->id, $exerciseIds);
        $this->assertContains($exercise1->id, $exerciseIds);
        $this->assertContains($exercise2->id, $exerciseIds);
    }

    /** @test */
    public function get_recommendations_filters_exercises_in_recovery()
    {
        $user = User::factory()->create();
        
        // Create exercise with 48-hour recovery period
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'recovery_hours' => 48,
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

        // Create lift log so exercise can be recommended
        $this->createLiftLogForExercise($user->id, $exercise->id);

        // Mock analysis showing recent workout (1 day ago, still in 48-hour recovery)
        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: ['pectoralis_major' => 0.8],
            movementArchetypes: [],
            recentExercises: [$exercise->id],
            analysisDate: Carbon::now(),
            muscleLastWorked: ['pectoralis_major' => Carbon::now()->subDay()]
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id);

        // Exercise should be filtered out due to recovery period
        $this->assertEmpty($recommendations);
    }

    /** @test */
    public function get_recommendations_prioritizes_underworked_muscles()
    {
        $user = User::factory()->create();
        
        // Exercise targeting underworked muscle
        $underworkedExercise = Exercise::factory()->create(['user_id' => null]);
        $underworkedIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $underworkedExercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'posterior_deltoid', // Underworked muscle
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);
        $this->createLiftLogForExercise($user->id, $underworkedExercise->id);

        // Exercise targeting well-worked muscle
        $workedExercise = Exercise::factory()->create(['user_id' => null]);
        $workedIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $workedExercise->id,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major', // Well-worked muscle
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);
        $this->createLiftLogForExercise($user->id, $workedExercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [
                'posterior_deltoid' => 0.1, // Underworked
                'pectoralis_major' => 0.8   // Well-worked
            ],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 2);

        $this->assertCount(2, $recommendations);
        
        // Exercise targeting underworked muscle should have higher score
        $scores = array_column($recommendations, 'score');
        $exerciseIds = array_map(fn($rec) => $rec['exercise']->id, $recommendations);
        
        $underworkedIndex = array_search($underworkedExercise->id, $exerciseIds);
        $workedIndex = array_search($workedExercise->id, $exerciseIds);
        
        $this->assertGreaterThan($scores[$workedIndex], $scores[$underworkedIndex]);
    }

    /** @test */
    public function get_recommendations_encourages_movement_archetype_diversity()
    {
        $user = User::factory()->create();
        
        // Create exercises with different archetypes
        $pushExercise = Exercise::factory()->create(['user_id' => null]);
        $pushIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $pushExercise->id,
            'movement_archetype' => 'push'
        ]);
        $this->createLiftLogForExercise($user->id, $pushExercise->id);

        $pullExercise = Exercise::factory()->create(['user_id' => null]);
        $pullIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $pullExercise->id,
            'movement_archetype' => 'pull'
        ]);
        $this->createLiftLogForExercise($user->id, $pullExercise->id);

        // Mock analysis showing heavy push usage, no pull usage
        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [
                'push' => 10, // Heavily used
                'pull' => 0   // Not used
            ],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 2);

        $this->assertCount(2, $recommendations);
        
        // Pull exercise should have higher score due to archetype diversity
        $scores = array_column($recommendations, 'score');
        $exerciseIds = array_map(fn($rec) => $rec['exercise']->id, $recommendations);
        
        $pushIndex = array_search($pushExercise->id, $exerciseIds);
        $pullIndex = array_search($pullExercise->id, $exerciseIds);
        
        $this->assertGreaterThan($scores[$pushIndex], $scores[$pullIndex]);
    }

    /** @test */
    public function get_recommendations_penalizes_recently_performed_exercises()
    {
        $user = User::factory()->create();
        
        $recentExercise = Exercise::factory()->create(['user_id' => null]);
        $recentIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $recentExercise->id
        ]);
        $this->createLiftLogForExercise($user->id, $recentExercise->id);

        $newExercise = Exercise::factory()->create(['user_id' => null]);
        $newIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $newExercise->id
        ]);
        $this->createLiftLogForExercise($user->id, $newExercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [$recentExercise->id], // Only recent exercise is in recent list
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 2);

        $this->assertCount(2, $recommendations);
        
        // New exercise should have higher score than recently performed one
        $scores = array_column($recommendations, 'score');
        $exerciseIds = array_map(fn($rec) => $rec['exercise']->id, $recommendations);
        
        $recentIndex = array_search($recentExercise->id, $exerciseIds);
        $newIndex = array_search($newExercise->id, $exerciseIds);
        
        $this->assertGreaterThan($scores[$recentIndex], $scores[$newIndex]);
    }

    /** @test */
    public function get_recommendations_includes_reasoning()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'primary_mover' => 'pectoralis_major',
            'difficulty_level' => 3,
            'movement_archetype' => 'push',
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
        $this->createLiftLogForExercise($user->id, $exercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: ['pectoralis_major' => 0.1], // Underworked
            movementArchetypes: ['push' => 0], // New archetype
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 1);

        $this->assertCount(1, $recommendations);
        
        $reasoning = $recommendations[0]['reasoning'];
        $this->assertIsArray($reasoning);
        $this->assertNotEmpty($reasoning);
        
        // Should contain information about underworked muscles, movement pattern, difficulty, and primary focus
        $reasoningText = implode(' ', $reasoning);
        $this->assertStringContainsString('pectoralis major', $reasoningText);
        $this->assertStringContainsString('Difficulty level: 3', $reasoningText);
    }

    /** @test */
    public function get_recommendations_respects_count_parameter()
    {
        $user = User::factory()->create();
        
        // Create 5 exercises with intelligence
        for ($i = 0; $i < 5; $i++) {
            $exercise = Exercise::factory()->create(['user_id' => null]);
            ExerciseIntelligence::factory()->create(['exercise_id' => $exercise->id]);
            $this->createLiftLogForExercise($user->id, $exercise->id);
        }

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 3);

        $this->assertCount(3, $recommendations);
    }

    /** @test */
    public function get_recommendations_returns_available_exercises_when_show_global_is_true()
    {
        $user = User::factory()->create();
        
        // Create global exercises with intelligence
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $globalIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $globalExercise->id]);
        $this->createLiftLogForExercise($user->id, $globalExercise->id);
        
        // Create user exercises with intelligence
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $userIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $userExercise->id]);
        $this->createLiftLogForExercise($user->id, $userExercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 5, true);

        // Should return both global and user exercises when show_global_exercises is true
        $this->assertCount(2, $recommendations);
        $exerciseIds = array_column($recommendations, 'exercise');
        $exerciseIds = array_map(fn($ex) => $ex->id, $exerciseIds);
        $this->assertContains($globalExercise->id, $exerciseIds);
        $this->assertContains($userExercise->id, $exerciseIds);
    }

    /** @test */
    public function get_recommendations_returns_user_exercises_when_show_global_is_false()
    {
        $user = User::factory()->create();
        
        // Create global exercises with intelligence
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $globalIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $globalExercise->id]);
        $this->createLiftLogForExercise($user->id, $globalExercise->id);
        
        // Create user exercises with intelligence
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $userIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $userExercise->id]);
        $this->createLiftLogForExercise($user->id, $userExercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 5, false);

        // Should include both exercises because global exercise has lift logs
        $this->assertCount(2, $recommendations);
        $exerciseIds = array_column($recommendations, 'exercise');
        $exerciseIds = array_map(fn($ex) => $ex->id, $exerciseIds);
        $this->assertContains($userExercise->id, $exerciseIds);
        $this->assertContains($globalExercise->id, $exerciseIds);
    }

    /** @test */
    public function get_recommendations_defaults_to_user_preference_when_show_global_not_specified()
    {
        $user = User::factory()->create(['show_global_exercises' => true]); // Explicitly set to true
        
        // Create global exercises with intelligence
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $globalIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $globalExercise->id]);
        $this->createLiftLogForExercise($user->id, $globalExercise->id);
        
        // Create user exercises with intelligence
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $userIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $userExercise->id]);
        $this->createLiftLogForExercise($user->id, $userExercise->id);

        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [],
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: Carbon::now()
        );
        
        $this->mockActivityService
            ->shouldReceive('analyzeLiftLogs')
            ->with($user->id)
            ->once()
            ->andReturn($mockAnalysis);

        // Call without showGlobalExercises parameter (should use user's preference)
        $recommendations = $this->recommendationEngine->getRecommendations($user->id, 5);

        // Should return both global and user exercises since user has show_global_exercises = true
        $this->assertCount(2, $recommendations);
        $exerciseIds = array_column($recommendations, 'exercise');
        $exerciseIds = array_map(fn($ex) => $ex->id, $exerciseIds);
        $this->assertContains($globalExercise->id, $exerciseIds);
        $this->assertContains($userExercise->id, $exerciseIds);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}