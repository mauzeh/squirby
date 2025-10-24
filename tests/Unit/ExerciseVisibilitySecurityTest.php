<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;
use App\Services\ExerciseService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExerciseVisibilitySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role for testing
        Role::factory()->create(['name' => 'Admin']);
    }

    /** @test */
    public function unauthenticated_requests_see_no_exercises()
    {
        $user = User::factory()->create();
        
        // Ensure no user is authenticated
        Auth::logout();
        
        // Create various exercises
        Exercise::factory()->create(['title' => 'Global Exercise']);
        Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        
        // Test direct model query
        $exercises = Exercise::availableToUser()->get();
        $this->assertCount(0, $exercises);
        
        // Test with explicit null user
        $exercises = Exercise::availableToUser(null)->get();
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function non_existent_user_id_returns_no_exercises()
    {
        $user = User::factory()->create();
        
        // Create exercises
        Exercise::factory()->create(['title' => 'Global Exercise']);
        Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        
        // Test with non-existent user ID
        $exercises = Exercise::availableToUser(99999)->get();
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function deleted_user_cannot_access_exercises()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        
        // Verify user can access exercises
        $exercises = Exercise::availableToUser($user->id)->get();
        $this->assertCount(2, $exercises);
        
        // Delete the user
        $userId = $user->id;
        $user->delete();
        
        // Verify deleted user cannot access exercises
        $exercises = Exercise::availableToUser($userId)->get();
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function user_cannot_access_other_users_exercises_when_global_disabled()
    {
        $user1 = User::factory()->create(['show_global_exercises' => false]);
        $user2 = User::factory()->create(['show_global_exercises' => false]);
        
        // Create exercises for each user
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        
        // User 1 should only see their own exercises
        $user1Exercises = Exercise::availableToUser($user1->id)->get();
        $this->assertCount(1, $user1Exercises);
        $this->assertEquals('User 1 Exercise', $user1Exercises->first()->title);
        
        // User 2 should only see their own exercises
        $user2Exercises = Exercise::availableToUser($user2->id)->get();
        $this->assertCount(1, $user2Exercises);
        $this->assertEquals('User 2 Exercise', $user2Exercises->first()->title);
    }

    /** @test */
    public function admin_role_check_is_secure()
    {
        $regularUser = User::factory()->create(['show_global_exercises' => false]);
        $adminUser = User::factory()->create(['show_global_exercises' => false]);
        
        // Only attach admin role to admin user
        $adminRole = Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $regularUser->id, 'title' => 'Regular User Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $adminUser->id, 'title' => 'Admin User Exercise']);
        
        // Regular user should only see their own exercises
        $regularUserExercises = Exercise::availableToUser($regularUser->id)->get();
        $this->assertCount(1, $regularUserExercises);
        $this->assertEquals('Regular User Exercise', $regularUserExercises->first()->title);
        
        // Admin user should see all exercises
        $adminUserExercises = Exercise::availableToUser($adminUser->id)->get();
        $this->assertCount(3, $adminUserExercises);
        $exerciseTitles = $adminUserExercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('Regular User Exercise', $exerciseTitles);
        $this->assertContains('Admin User Exercise', $exerciseTitles);
    }

    /** @test */
    public function preference_override_parameter_works_securely()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        
        // Test override to show global exercises
        $exercises = Exercise::availableToUser($user->id, true)->get();
        $this->assertCount(2, $exercises);
        
        // Test override to hide global exercises
        $user->update(['show_global_exercises' => true]);
        $exercises = Exercise::availableToUser($user->id, false)->get();
        $this->assertCount(1, $exercises);
        $this->assertEquals('User Exercise', $exercises->first()->title);
    }

    /** @test */
    public function exercise_service_respects_security_constraints()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        $otherUser = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id, 'title' => 'Other User Exercise']);
        
        // Create lift logs to make exercises appear in top exercises
        \App\Models\LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $userExercise->id]);
        
        $this->actingAs($user);
        $exerciseService = app(ExerciseService::class);
        
        $displayExercises = $exerciseService->getDisplayExercises(5);
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        
        // Should only see user's own exercises
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }



    /** @test */
    public function sql_injection_protection_in_user_id_parameter()
    {
        $user = User::factory()->create();
        
        // Create exercises
        Exercise::factory()->create(['title' => 'Global Exercise']);
        Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        
        // Test with potential SQL injection attempts
        $maliciousInputs = [
            "1 OR 1=1",
            "1; DROP TABLE exercises;",
            "1 UNION SELECT * FROM users",
            "' OR '1'='1",
            "1' OR '1'='1' --",
        ];
        
        foreach ($maliciousInputs as $maliciousInput) {
            $exercises = Exercise::availableToUser($maliciousInput)->get();
            // Should return no exercises for invalid user IDs
            $this->assertCount(0, $exercises, "SQL injection attempt failed for input: {$maliciousInput}");
        }
    }

    /** @test */
    public function concurrent_user_sessions_maintain_separate_visibility()
    {
        $user1 = User::factory()->create(['show_global_exercises' => true]);
        $user2 = User::factory()->create(['show_global_exercises' => false]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        
        // Simulate concurrent requests
        $user1Exercises = Exercise::availableToUser($user1->id)->get();
        $user2Exercises = Exercise::availableToUser($user2->id)->get();
        
        // User 1 should see global + their own
        $this->assertCount(2, $user1Exercises);
        $user1Titles = $user1Exercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $user1Titles);
        $this->assertContains('User 1 Exercise', $user1Titles);
        
        // User 2 should see only their own
        $this->assertCount(1, $user2Exercises);
        $user2Titles = $user2Exercises->pluck('title')->toArray();
        $this->assertNotContains('Global Exercise', $user2Titles);
        $this->assertContains('User 2 Exercise', $user2Titles);
    }

    /** @test */
    public function preference_changes_do_not_affect_other_users()
    {
        $user1 = User::factory()->create(['show_global_exercises' => true]);
        $user2 = User::factory()->create(['show_global_exercises' => true]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        
        // Both users initially see global exercises
        $user1Exercises = Exercise::availableToUser($user1->id)->get();
        $user2Exercises = Exercise::availableToUser($user2->id)->get();
        $this->assertCount(2, $user1Exercises);
        $this->assertCount(2, $user2Exercises);
        
        // User 1 changes preference
        $user1->update(['show_global_exercises' => false]);
        
        // User 1 should now see only their own exercises
        $user1Exercises = Exercise::availableToUser($user1->id)->get();
        $this->assertCount(1, $user1Exercises);
        $this->assertEquals('User 1 Exercise', $user1Exercises->first()->title);
        
        // User 2 should still see global exercises
        $user2Exercises = Exercise::availableToUser($user2->id)->get();
        $this->assertCount(2, $user2Exercises);
        $user2Titles = $user2Exercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $user2Titles);
        $this->assertContains('User 2 Exercise', $user2Titles);
    }
}