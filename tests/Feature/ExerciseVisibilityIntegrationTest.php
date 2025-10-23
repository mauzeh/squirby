<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseVisibilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected User $otherUser;
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
    }

    /** @test */
    public function lift_log_controller_index_respects_user_preference()
    {
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function lift_log_controller_edit_respects_user_preference()
    {
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.edit', $liftLog));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.edit', $liftLog));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function exercise_controller_index_respects_user_preference()
    {
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('exercises.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('exercises.index'));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function exercise_controller_show_logs_respects_user_preference()
    {
        // Test with global exercises enabled - user can access global exercise
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('exercises.show-logs', $this->globalExercise));
        
        $response->assertStatus(200);
        
        // Test with global exercises disabled - user cannot access global exercise
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('exercises.show-logs', $this->globalExercise));
        
        $response->assertStatus(403);
        
        // User can always access their own exercises
        $response = $this->actingAs($this->user)->get(route('exercises.show-logs', $this->userExercise));
        $response->assertStatus(200);
    }

    /** @test */
    public function program_controller_index_respects_user_preference()
    {
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('programs.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('programs.index'));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function program_controller_create_respects_user_preference()
    {
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('programs.create'));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('programs.create'));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function program_controller_edit_respects_user_preference()
    {
        $program = \App\Models\Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('programs.edit', $program));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('programs.edit', $program));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function admin_users_see_all_exercises_regardless_of_preference()
    {
        // Admin has show_global_exercises = false but should still see all exercises
        $this->assertFalse($this->adminUser->show_global_exercises);
        
        // Test lift logs index
        $response = $this->actingAs($this->adminUser)->get(route('lift-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertSee('Other User Exercise');
        
        // Test exercises index
        $response = $this->actingAs($this->adminUser)->get(route('exercises.index'));
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertSee('Other User Exercise');
        
        // Test programs index
        $response = $this->actingAs($this->adminUser)->get(route('programs.index'));
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertSee('Other User Exercise');
    }

    /** @test */
    public function unauthenticated_users_cannot_access_exercise_related_pages()
    {
        // Test that unauthenticated users are redirected to login
        $response = $this->get(route('lift-logs.index'));
        $response->assertRedirect(route('login'));
        
        $response = $this->get(route('exercises.index'));
        $response->assertRedirect(route('login'));
        
        $response = $this->get(route('programs.index'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function exercise_service_respects_user_preference()
    {
        $exerciseService = app(\App\Services\ExerciseService::class);
        
        // Create some lift logs to make exercises appear in "top exercises"
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->globalExercise->id
        ]);
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->userExercise->id
        ]);
        
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $this->actingAs($this->user);
        
        $displayExercises = $exerciseService->getDisplayExercises(5);
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        
        $displayExercises = $exerciseService->getDisplayExercises(5);
        $exerciseTitles = $displayExercises->pluck('title')->toArray();
        
        $this->assertNotContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
        $this->assertNotContains('Other User Exercise', $exerciseTitles);
    }

    /** @test */
    public function mobile_entry_respects_user_preference()
    {
        // Test with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.mobile-entry'));
        
        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
        
        // Test with global exercises disabled
        $this->user->update(['show_global_exercises' => false]);
        $response = $this->actingAs($this->user)->get(route('lift-logs.mobile-entry'));
        
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function preference_change_immediately_affects_exercise_visibility()
    {
        // Start with global exercises enabled
        $this->user->update(['show_global_exercises' => true]);
        $this->actingAs($this->user);
        
        $exercises = Exercise::availableToUser()->get();
        $this->assertCount(2, $exercises); // Global + User exercise
        
        // Change preference to disabled
        $this->user->update(['show_global_exercises' => false]);
        
        $exercises = Exercise::availableToUser()->get();
        $this->assertCount(1, $exercises); // Only User exercise
        
        // Change back to enabled
        $this->user->update(['show_global_exercises' => true]);
        
        $exercises = Exercise::availableToUser()->get();
        $this->assertCount(2, $exercises); // Global + User exercise again
    }

    /** @test */
    public function different_users_see_different_exercises_based_on_their_preferences()
    {
        // User 1: show global exercises
        $this->user->update(['show_global_exercises' => true]);
        $this->actingAs($this->user);
        $user1Exercises = Exercise::availableToUser()->get();
        
        // User 2: hide global exercises
        $this->otherUser->update(['show_global_exercises' => false]);
        $user2Exercises = Exercise::availableToUser($this->otherUser->id)->get();
        
        // User 1 should see global + their own
        $this->assertCount(2, $user1Exercises);
        $this->assertTrue($user1Exercises->contains('title', 'Global Exercise'));
        $this->assertTrue($user1Exercises->contains('title', 'User Exercise'));
        
        // User 2 should see only their own
        $this->assertCount(1, $user2Exercises);
        $this->assertFalse($user2Exercises->contains('title', 'Global Exercise'));
        $this->assertTrue($user2Exercises->contains('title', 'Other User Exercise'));
    }
}