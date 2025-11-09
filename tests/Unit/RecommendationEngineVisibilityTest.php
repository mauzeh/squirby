<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Services\RecommendationEngine;
use App\Services\ActivityAnalysisService;
use App\Services\UserActivityAnalysis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RecommendationEngineVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected RecommendationEngine $recommendationEngine;
    protected User $user;
    protected User $otherUser;
    protected User $adminUser;
    protected Exercise $globalExercise;
    protected Exercise $userExercise;
    protected Exercise $otherUserExercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role
        Role::factory()->create(['name' => 'Admin']);
        
        // Create users
        $this->user = User::factory()->create(['show_global_exercises' => true]);
        $this->otherUser = User::factory()->create(['show_global_exercises' => true]);
        
        $this->adminUser = User::factory()->create(['show_global_exercises' => false]);
        $adminRole = Role::where('name', 'Admin')->first();
        $this->adminUser->roles()->attach($adminRole);
        
        // Create exercises
        $this->globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $this->userExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'User Exercise'
        ]);
        $this->otherUserExercise = Exercise::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Exercise'
        ]);
        
        // Create exercise intelligence for all exercises
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->globalExercise->id,
            'movement_archetype' => 'push',
            'difficulty_level' => 3,
            'primary_mover' => 'pectoralis_major'
        ]);
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->userExercise->id,
            'movement_archetype' => 'pull',
            'difficulty_level' => 2,
            'primary_mover' => 'latissimus_dorsi'
        ]);
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->otherUserExercise->id,
            'movement_archetype' => 'squat',
            'difficulty_level' => 4,
            'primary_mover' => 'quadriceps'
        ]);
        
        // Create lift logs so exercises can be recommended (only exercises user has performed are recommended)
        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->globalExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $this->otherUserExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        LiftLog::factory()->create([
            'user_id' => $this->adminUser->id,
            'exercise_id' => $this->globalExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        LiftLog::factory()->create([
            'user_id' => $this->adminUser->id,
            'exercise_id' => $this->userExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        LiftLog::factory()->create([
            'user_id' => $this->adminUser->id,
            'exercise_id' => $this->otherUserExercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        
        // Create a real UserActivityAnalysis instance with empty data
        $mockAnalysis = new UserActivityAnalysis(
            muscleWorkload: [], // Empty workload to trigger recommendations
            movementArchetypes: [],
            recentExercises: [],
            analysisDate: now(),
            muscleLastWorked: []
        );
        
        $mockActivityService = $this->createMock(ActivityAnalysisService::class);
        $mockActivityService->method('analyzeLiftLogs')->willReturn($mockAnalysis);
        
        $this->recommendationEngine = new RecommendationEngine($mockActivityService);
    }

    /** @test */
    public function getRecommendations_respects_user_global_exercise_preference_enabled()
    {
        $this->user->update(['show_global_exercises' => true]);
        
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        
        // Should include both global and user exercises
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getRecommendations_respects_user_global_exercise_preference_disabled()
    {
        $this->user->update(['show_global_exercises' => false]);
        
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        
        // Should include only user exercises
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getRecommendations_admin_user_sees_all_exercises()
    {
        // Admin has show_global_exercises = false but should still see all exercises
        $this->assertFalse($this->adminUser->show_global_exercises);
        
        $recommendations = $this->recommendationEngine->getRecommendations($this->adminUser->id, 10);
        
        // Should include all exercises
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getRecommendations_override_parameter_works()
    {
        $this->user->update(['show_global_exercises' => false]);
        
        // Override to show global exercises
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10, true);
        
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
        
        // Override to hide global exercises
        $this->user->update(['show_global_exercises' => true]);
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10, false);
        
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getRecommendations_returns_empty_array_for_non_existent_user()
    {
        $recommendations = $this->recommendationEngine->getRecommendations(99999, 10);
        
        $this->assertEmpty($recommendations);
    }

    /** @test */
    public function getRecommendations_returns_empty_array_when_no_exercises_with_intelligence()
    {
        // Delete all exercise intelligence
        ExerciseIntelligence::truncate();
        
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        
        $this->assertEmpty($recommendations);
    }

    /** @test */
    public function getRecommendations_only_includes_exercises_with_intelligence()
    {
        // Create an exercise without intelligence
        $exerciseWithoutIntelligence = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Exercise Without Intelligence'
        ]);
        
        $this->user->update(['show_global_exercises' => true]);
        
        $recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        
        $exerciseTitles = array_column($recommendations, 'exercise');
        $exerciseTitles = array_map(fn($ex) => $ex->title, $exerciseTitles);
        
        // Should not include exercise without intelligence
        $this->assertNotContains('Exercise Without Intelligence', $exerciseTitles);
        
        // Should include exercises with intelligence
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getRecommendations_respects_count_limit()
    {
        // Create additional exercises with intelligence
        $additionalExercises = Exercise::factory()->count(5)->create(['user_id' => $this->user->id]);
        foreach ($additionalExercises as $exercise) {
            ExerciseIntelligence::factory()->create([
                'exercise_id' => $exercise->id,
                'movement_archetype' => 'push',
                'difficulty_level' => 3,
                'primary_mover' => 'pectoralis_major'
            ]);
            LiftLog::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => Carbon::now()->subDays(10)
            ]);
        }
        
        $this->user->update(['show_global_exercises' => true]);
        
        // Test different limits
        $recommendations3 = $this->recommendationEngine->getRecommendations($this->user->id, 3);
        $this->assertCount(3, $recommendations3);
        
        $recommendations5 = $this->recommendationEngine->getRecommendations($this->user->id, 5);
        $this->assertCount(5, $recommendations5);
        
        $recommendations10 = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        $this->assertLessThanOrEqual(10, count($recommendations10));
    }

    /** @test */
    public function getRecommendations_maintains_security_across_users()
    {
        $this->user->update(['show_global_exercises' => false]);
        $this->otherUser->update(['show_global_exercises' => false]);
        
        // Get recommendations for user 1
        $user1Recommendations = $this->recommendationEngine->getRecommendations($this->user->id, 10);
        $user1ExerciseTitles = array_column($user1Recommendations, 'exercise');
        $user1ExerciseTitles = array_map(fn($ex) => $ex->title, $user1ExerciseTitles);
        
        // Get recommendations for user 2
        $user2Recommendations = $this->recommendationEngine->getRecommendations($this->otherUser->id, 10);
        $user2ExerciseTitles = array_column($user2Recommendations, 'exercise');
        $user2ExerciseTitles = array_map(fn($ex) => $ex->title, $user2ExerciseTitles);
        
        // User 1 should only see their own exercises
        $this->assertNotContains('Global Exercise', $user1ExerciseTitles);
        $this->assertContains('User Exercise', $user1ExerciseTitles);
        $this->assertNotContains('Other User Exercise', $user1ExerciseTitles);
        
        // User 2 should only see their own exercises
        $this->assertNotContains('Global Exercise', $user2ExerciseTitles);
        $this->assertNotContains('User Exercise', $user2ExerciseTitles);
        $this->assertContains('Other User Exercise', $user2ExerciseTitles);
    }
}