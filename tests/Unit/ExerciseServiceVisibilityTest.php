<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ExerciseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExerciseServiceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected ExerciseService $exerciseService;
    protected User $user;
    protected User $otherUser;
    protected Exercise $globalExercise;
    protected Exercise $userExercise;
    protected Exercise $otherUserExercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exerciseService = app(ExerciseService::class);
        
        // Create users
        $this->user = User::factory()->create(['show_global_exercises' => true]);
        $this->otherUser = User::factory()->create(['show_global_exercises' => true]);
        
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
    }

    /** @test */
    public function getTopExercises_respects_user_visibility_preferences()
    {
        // Create lift logs for different exercises
        LiftLog::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->globalExercise->id
        ]);
        LiftLog::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        LiftLog::factory()->count(10)->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $this->otherUserExercise->id
        ]);
        
        $this->actingAs($this->user);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $topExercises = $this->exerciseService->getTopExercises(5);
        
        $exerciseTitles = $topExercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $topExercises = $this->exerciseService->getTopExercises(5);
        
        $exerciseTitles = $topExercises->pluck('title')->toArray();
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getDisplayExercises_respects_user_visibility_preferences()
    {
        // Create lift logs to establish "top" exercises
        LiftLog::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->globalExercise->id
        ]);
        LiftLog::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        $this->actingAs($this->user);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function getDisplayExercises_fills_with_recent_exercises_respecting_visibility()
    {
        // Create only one lift log so we need to fill with recent exercises
        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        // Create additional exercises with different creation times
        $recentGlobalExercise = Exercise::factory()->create([
            'title' => 'Recent Global Exercise',
            'created_at' => now()->subHour()
        ]);
        $recentUserExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Recent User Exercise',
            'created_at' => now()->subMinutes(30)
        ]);
        
        $this->actingAs($this->user);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertContains('User Exercise', $exerciseTitles); // From top exercises
        $this->assertContains('Recent Global Exercise', $exerciseTitles); // From recent fill
        $this->assertContains('Recent User Exercise', $exerciseTitles); // From recent fill
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertContains('User Exercise', $exerciseTitles); // From top exercises
        $this->assertNotContains('Recent Global Exercise', $exerciseTitles); // Should be filtered out
        $this->assertContains('Recent User Exercise', $exerciseTitles); // From recent fill
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function service_works_with_admin_users()
    {
        // Create admin user
        Role::factory()->create(['name' => 'Admin']);
        $adminUser = User::factory()->create(['show_global_exercises' => false]);
        $adminRole = Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);
        
        // Create lift logs for admin user
        LiftLog::factory()->create([
            'user_id' => $adminUser->id,
            'exercise_id' => $this->globalExercise->id
        ]);
        LiftLog::factory()->create([
            'user_id' => $adminUser->id,
            'exercise_id' => $this->userExercise->id
        ]);
        LiftLog::factory()->create([
            'user_id' => $adminUser->id,
            'exercise_id' => $this->otherUserExercise->id
        ]);
        
        $this->actingAs($adminUser);
        
        // Admin should see all exercises regardless of preference
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function service_handles_no_authenticated_user_gracefully()
    {
        // Ensure no user is authenticated
        Auth::logout();
        
        // Service should handle this gracefully and return empty results
        $topExercises = $this->exerciseService->getTopExercises(5);
        $this->assertCount(0, $topExercises);
        
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        $this->assertCount(0, $displayExercises);
    }

    /** @test */
    public function service_respects_limit_parameter()
    {
        // Create multiple exercises and lift logs
        $exercises = Exercise::factory()->count(10)->create(['user_id' => $this->user->id]);
        
        foreach ($exercises as $exercise) {
            LiftLog::factory()->count(rand(1, 5))->create([
                'user_id' => $this->user->id,
                'exercise_id' => $exercise->id
            ]);
        }
        
        $this->actingAs($this->user);
        
        // Test different limits
        $topExercises3 = $this->exerciseService->getTopExercises(3);
        $this->assertCount(3, $topExercises3);
        
        $topExercises5 = $this->exerciseService->getTopExercises(5);
        $this->assertCount(5, $topExercises5);
        
        $displayExercises2 = $this->exerciseService->getDisplayExercises(2);
        $this->assertCount(2, $displayExercises2);
    }

    /** @test */
    public function service_excludes_already_selected_exercises_from_recent_fill()
    {
        // Create lift logs for specific exercises to make them "top"
        LiftLog::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        // Create additional recent exercises
        $recentExercise1 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Recent Exercise 1',
            'created_at' => now()->subHour()
        ]);
        $recentExercise2 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Recent Exercise 2',
            'created_at' => now()->subMinutes(30)
        ]);
        
        $this->actingAs($this->user);
        
        $displayExercises = $this->exerciseService->getDisplayExercises(3);
        
        // Should include the top exercise and recent ones, but not duplicate the top exercise
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertContains('Recent Exercise 1', $exerciseTitles);
        $this->assertContains('Recent Exercise 2', $exerciseTitles);
        
        // Should not have duplicates
        $this->assertEquals(3, $displayExercises->count());
        $this->assertEquals(3, $displayExercises->unique('id')->count());
    }

    /** @test */
    public function service_maintains_correct_ordering()
    {
        // Create exercises with specific lift log counts to control ordering
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Exercise 2']);
        $exercise3 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Exercise 3']);
        
        // Create lift logs with different counts (exercise2 should be first, then exercise3, then exercise1)
        LiftLog::factory()->count(1)->create(['user_id' => $this->user->id, 'exercise_id' => $exercise1->id]);
        LiftLog::factory()->count(5)->create(['user_id' => $this->user->id, 'exercise_id' => $exercise2->id]);
        LiftLog::factory()->count(3)->create(['user_id' => $this->user->id, 'exercise_id' => $exercise3->id]);
        
        $this->actingAs($this->user);
        
        $topExercises = $this->exerciseService->getTopExercises(3);
        
        // Should be ordered by lift log count descending
        $this->assertEquals('Exercise 2', $topExercises->first()->title);
        $this->assertEquals('Exercise 3', $topExercises->skip(1)->first()->title);
        $this->assertEquals('Exercise 1', $topExercises->last()->title);
    }
}