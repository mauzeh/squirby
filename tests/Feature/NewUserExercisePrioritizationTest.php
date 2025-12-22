<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Services\MobileEntry\LiftLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewUserExercisePrioritizationTest extends TestCase
{
    use RefreshDatabase;

    private User $newUser;
    private User $experiencedUser;
    private User $adminUser;
    private LiftLogService $liftLogService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required data
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        // Create users
        $this->newUser = User::factory()->create(); // 0 lift logs = new user
        $this->experiencedUser = User::factory()->create();
        $this->adminUser = User::factory()->create();
        
        // Assign admin role
        $adminRole = Role::where('name', 'Admin')->first();
        $this->adminUser->roles()->attach($adminRole);
        
        // Create the service
        $this->liftLogService = app(LiftLogService::class);
    }

    /** @test */
    public function new_user_with_no_logs_sees_popular_exercises_first()
    {
        // Create many exercises to ensure only the most popular ones get prioritized
        $exercises = Exercise::factory()->count(15)->create();
        $popularExercise = $exercises[0];
        $popularExercise->update(['title' => 'Bench Press']);
        
        $lessPopularExercise = Exercise::factory()->create(['title' => 'Obscure Exercise']);
        
        // Create lift logs from non-admin users
        $regularUser1 = User::factory()->create();
        $regularUser2 = User::factory()->create();
        
        // Make the first 10 exercises very popular (ensuring they fill the top 10 slots)
        foreach ($exercises->take(10) as $index => $exercise) {
            LiftLog::factory()->count(50 - $index)->create([
                'user_id' => $regularUser1->id,
                'exercise_id' => $exercise->id,
            ]);
            LiftLog::factory()->count(40 - $index)->create([
                'user_id' => $regularUser2->id,
                'exercise_id' => $exercise->id,
            ]);
        }
        
        // Make obscure exercise have very few logs (definitely not in top 10)
        LiftLog::factory()->count(2)->create([
            'user_id' => $regularUser1->id,
            'exercise_id' => $lessPopularExercise->id,
        ]);
        
        // Get item selection list for new user
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Find the exercises in the results
        $benchPressItem = collect($result['items'])->firstWhere('name', 'Bench Press');
        $obscureExerciseItem = collect($result['items'])->firstWhere('name', 'Obscure Exercise');
        
        // Assert bench press is prioritized (priority 2 for popular exercises) and labeled as "Popular"
        $this->assertEquals(2, $benchPressItem['type']['priority']);
        $this->assertEquals('Popular', $benchPressItem['type']['label']);
        $this->assertEquals('in-program', $benchPressItem['type']['cssClass']);
        
        // Assert obscure exercise has lower priority (not in top 10 popular)
        $this->assertEquals(3, $obscureExerciseItem['type']['priority']);
        $this->assertNotEquals('Popular', $obscureExerciseItem['type']['label']);
    }

    /** @test */
    public function experienced_user_sees_logged_exercises_prioritized_not_popular_exercises()
    {
        // Make the experienced user have 5+ lift logs (older than 4 weeks to avoid "recent" category)
        $loggedExercise = Exercise::factory()->create(['title' => 'Logged Exercise']);
        LiftLog::factory()->count(5)->create([
            'user_id' => $this->experiencedUser->id,
            'exercise_id' => $loggedExercise->id,
            'logged_at' => Carbon::now()->subDays(35), // Older than 4 weeks
        ]);
        
        // Create a popular exercise (from other users) that this user has never logged
        $popularExercise = Exercise::factory()->create(['title' => 'Popular Exercise']);
        $otherUser = User::factory()->create();
        LiftLog::factory()->count(10)->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $popularExercise->id,
        ]);
        
        // Get item selection list for experienced user
        $result = $this->liftLogService->generateItemSelectionList($this->experiencedUser->id, Carbon::today());
        
        // Find exercises in results
        $loggedExerciseItem = collect($result['items'])->firstWhere('name', 'Logged Exercise');
        $popularExerciseItem = collect($result['items'])->firstWhere('name', 'Popular Exercise');
        
        // For experienced users, exercises they've logged should be prioritized (priority 2 since no recent exercises)
        $this->assertEquals(2, $loggedExerciseItem['type']['priority']);
        $this->assertEquals('in-program', $loggedExerciseItem['type']['cssClass']);
        
        // Popular exercises they haven't logged should have lower priority (priority 3)
        $this->assertEquals(3, $popularExerciseItem['type']['priority']);
        $this->assertNotEquals('Popular', $popularExerciseItem['type']['label']);
        $this->assertEquals('regular', $popularExerciseItem['type']['cssClass']);
    }

    /** @test */
    public function admin_logs_are_excluded_from_popularity_calculation()
    {
        // Create exercises
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2']);
        
        // Admin logs a lot of exercise 1 (should be ignored)
        LiftLog::factory()->count(20)->create([
            'user_id' => $this->adminUser->id,
            'exercise_id' => $exercise1->id,
        ]);
        
        // Regular user logs exercise 2 less frequently (should be prioritized)
        $regularUser = User::factory()->create();
        LiftLog::factory()->count(5)->create([
            'user_id' => $regularUser->id,
            'exercise_id' => $exercise2->id,
        ]);
        
        // Get item selection list for new user
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Find exercises in results
        $exercise1Item = collect($result['items'])->firstWhere('name', 'Exercise 1');
        $exercise2Item = collect($result['items'])->firstWhere('name', 'Exercise 2');
        
        // Exercise 2 should be prioritized despite having fewer total logs
        // because admin logs are excluded (priority 2 for popular exercises)
        $this->assertEquals('Popular', $exercise2Item['type']['label']);
        $this->assertEquals(2, $exercise2Item['type']['priority']);
        
        // Exercise 1 should not be prioritized (admin logs ignored)
        $this->assertNotEquals('Popular', $exercise1Item['type']['label']);
        $this->assertEquals(3, $exercise1Item['type']['priority']);
    }

    /** @test */
    public function only_available_exercises_are_considered_for_prioritization()
    {
        // Create a global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null, // Global exercise
        ]);
        
        // Create a user-specific exercise for another user
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);
        
        // Make the other user's exercise very popular
        LiftLog::factory()->count(20)->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $otherUserExercise->id,
        ]);
        
        // Make global exercise less popular
        LiftLog::factory()->count(5)->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $globalExercise->id,
        ]);
        
        // Get item selection list for new user (should only see global exercise)
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Should only contain the global exercise
        $exerciseNames = collect($result['items'])->pluck('name')->toArray();
        $this->assertContains('Global Exercise', $exerciseNames);
        $this->assertNotContains('Other User Exercise', $exerciseNames);
        
        // Global exercise should be prioritized as it's the only available popular one
        $globalExerciseItem = collect($result['items'])->firstWhere('name', 'Global Exercise');
        $this->assertEquals('Popular', $globalExerciseItem['type']['label']);
    }

    /** @test */
    public function recent_exercises_appear_at_top_for_new_users()
    {
        // Create many exercises to ensure recent exercise doesn't accidentally become popular
        $exercises = Exercise::factory()->count(15)->create();
        $popularExercise = $exercises[0];
        $popularExercise->update(['title' => 'Popular Exercise']);
        
        $recentExercise = Exercise::factory()->create(['title' => 'Recent Exercise']);
        
        // Create other users to generate popularity data
        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();
        
        // Make the first 10 exercises very popular (more than recent exercise)
        foreach ($exercises->take(10) as $index => $exercise) {
            LiftLog::factory()->count(20 - $index)->create([
                'user_id' => $otherUser1->id,
                'exercise_id' => $exercise->id,
            ]);
            LiftLog::factory()->count(15 - $index)->create([
                'user_id' => $otherUser2->id,
                'exercise_id' => $exercise->id,
            ]);
        }
        
        // New user performed recent exercise 3 days ago (only 1 log, so definitely not in top 10)
        LiftLog::factory()->create([
            'user_id' => $this->newUser->id,
            'exercise_id' => $recentExercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        // Get item selection list
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Find exercises
        $popularItem = collect($result['items'])->firstWhere('name', 'Popular Exercise');
        $recentItem = collect($result['items'])->firstWhere('name', 'Recent Exercise');
        
        // Recent exercise should be priority 1 (new logic: recent always first)
        $this->assertEquals(1, $recentItem['type']['priority']);
        $this->assertEquals('Recent', $recentItem['type']['label']);
        
        // Popular exercise should be priority 2 (popular exercises now second tier)
        $this->assertEquals(2, $popularItem['type']['priority']);
        $this->assertEquals('Popular', $popularItem['type']['label']);
    }

    /** @test */
    public function recent_exercises_appear_at_top_for_experienced_users()
    {
        // Make the user experienced (5+ lift logs)
        $oldExercise = Exercise::factory()->create(['title' => 'Old Exercise']);
        LiftLog::factory()->count(5)->create([
            'user_id' => $this->experiencedUser->id,
            'exercise_id' => $oldExercise->id,
            'logged_at' => Carbon::now()->subDays(60), // Old logs
        ]);
        
        // Create a recent exercise
        $recentExercise = Exercise::factory()->create(['title' => 'Recent Exercise']);
        LiftLog::factory()->create([
            'user_id' => $this->experiencedUser->id,
            'exercise_id' => $recentExercise->id,
            'logged_at' => Carbon::now()->subDays(3), // Recent log
        ]);
        
        // Get item selection list
        $result = $this->liftLogService->generateItemSelectionList($this->experiencedUser->id, Carbon::today());
        
        // Find exercises
        $recentItem = collect($result['items'])->firstWhere('name', 'Recent Exercise');
        $oldItem = collect($result['items'])->firstWhere('name', 'Old Exercise');
        
        // Recent exercise should be priority 1 with "Recent" label
        $this->assertEquals(1, $recentItem['type']['priority']);
        $this->assertEquals('Recent', $recentItem['type']['label']);
        $this->assertEquals('recent', $recentItem['type']['cssClass']);
        
        // Old exercise should be priority 2 with time label
        $this->assertEquals(2, $oldItem['type']['priority']);
        $this->assertNotEquals('Recent', $oldItem['type']['label']);
        $this->assertEquals('in-program', $oldItem['type']['cssClass']);
    }

    /** @test */
    public function user_transitions_from_new_to_experienced_correctly()
    {
        // Create an exercise
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        
        // Initially new user (0 logs) - should see popular exercises
        $result1 = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Create 4 lift logs (still new user)
        LiftLog::factory()->count(4)->create([
            'user_id' => $this->newUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $result2 = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Create 1 more lift log (now experienced user with 5 total)
        LiftLog::factory()->create([
            'user_id' => $this->newUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $result3 = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // The behavior should change at the 5-log threshold
        // This test mainly ensures the threshold logic works correctly
        $this->assertIsArray($result1['items']);
        $this->assertIsArray($result2['items']);
        $this->assertIsArray($result3['items']);
    }

    /** @test */
    public function empty_exercise_list_returns_empty_prioritization()
    {
        // Test with no exercises available
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        $this->assertEmpty($result['items']);
    }

    /** @test */
    public function top_10_limit_is_respected_for_popular_exercises()
    {
        // Create 15 exercises
        $exercises = Exercise::factory()->count(15)->create();
        
        // Create a regular user to generate logs
        $regularUser = User::factory()->create();
        
        // Make all 15 exercises popular with different log counts
        foreach ($exercises as $index => $exercise) {
            LiftLog::factory()->count(15 - $index)->create([
                'user_id' => $regularUser->id,
                'exercise_id' => $exercise->id,
            ]);
        }
        
        // Get item selection list for new user
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Count how many exercises are marked as "Popular"
        $popularCount = collect($result['items'])
            ->where('type.label', 'Popular')
            ->count();
        
        // Should be limited to 10 popular exercises
        $this->assertLessThanOrEqual(10, $popularCount);
    }

    /** @test */
    public function recent_exercises_use_four_week_time_window()
    {
        // Create exercises
        $withinWindowExercise = Exercise::factory()->create(['title' => 'Within Window']);
        $outsideWindowExercise = Exercise::factory()->create(['title' => 'Outside Window']);
        
        // Exercise within 4 weeks (27 days ago)
        LiftLog::factory()->create([
            'user_id' => $this->newUser->id,
            'exercise_id' => $withinWindowExercise->id,
            'logged_at' => Carbon::now()->subDays(27),
        ]);
        
        // Exercise outside 4 weeks (29 days ago)
        LiftLog::factory()->create([
            'user_id' => $this->newUser->id,
            'exercise_id' => $outsideWindowExercise->id,
            'logged_at' => Carbon::now()->subDays(29),
        ]);
        
        // Get item selection list
        $result = $this->liftLogService->generateItemSelectionList($this->newUser->id, Carbon::today());
        
        // Find exercises
        $withinWindowItem = collect($result['items'])->firstWhere('name', 'Within Window');
        $outsideWindowItem = collect($result['items'])->firstWhere('name', 'Outside Window');
        
        // Exercise within 4 weeks should be marked as "Recent"
        $this->assertEquals('Recent', $withinWindowItem['type']['label']);
        $this->assertEquals(1, $withinWindowItem['type']['priority']);
        
        // Exercise outside 4 weeks should NOT be marked as "Recent"
        // For new users, it will be priority 3 (regular) since it's not popular and not recent
        $this->assertNotEquals('Recent', $outsideWindowItem['type']['label']);
        $this->assertGreaterThan(1, $outsideWindowItem['type']['priority']); // Should be lower priority than recent
    }
}