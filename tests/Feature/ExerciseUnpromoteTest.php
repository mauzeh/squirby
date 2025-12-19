<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExerciseUnpromoteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $regularUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role and users
        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($this->adminRole);
        $this->regularUser = User::factory()->create();
    }

    /** @test */
    public function unpromote_button_appears_for_admin_users_on_global_exercises()
    {
        // Create global exercise with lift logs to establish original owner
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create lift log to establish original owner
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->adminUser->id,
            'logged_at' => Carbon::now()->subDays(5),
        ]);

        // Admin views exercise edit page
        $this->actingAs($this->adminUser);
        $response = $this->get(route('exercises.edit', $globalExercise));

        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        
        // Should see unpromote button with user icon in quick actions
        $response->assertSee('fa-user');
        $response->assertSee('Unpromote');
        
        // Should see the unpromote form for this exercise
        $response->assertSee(route('exercises.unpromote', $globalExercise));
    }

    /** @test */
    public function unpromote_button_does_not_appear_for_non_admin_users()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Regular user views exercise index
        $this->actingAs($this->regularUser);
        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        
        // Should NOT see unpromote button or user icon for unpromote
        $response->assertDontSee('Unpromote');
        
        // Should NOT see the unpromote form
        $response->assertDontSee(route('exercises.unpromote', $globalExercise));
    }

    /** @test */
    public function unpromote_button_does_not_appear_for_user_exercises()
    {
        // Create user exercise owned by admin (so they can see it)
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->adminUser->id,
        ]);

        // Admin views exercise edit page
        $this->actingAs($this->adminUser);
        $response = $this->get(route('exercises.edit', $userExercise));

        $response->assertStatus(200);
        $response->assertSee('User Exercise');
        
        // Should NOT see unpromote button for user exercise
        $response->assertDontSee(route('exercises.unpromote', $userExercise));
    }

    /** @test */
    public function successful_unpromote_updates_exercise_ownership()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create lift log to establish original owner (only this user has logs)
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => Carbon::now()->subDays(5),
        ]);

        // Admin unpromotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert successful response
        $response->assertRedirect(route('exercises.edit', $globalExercise));
        $response->assertSessionHas('success', "Exercise 'Global Exercise' unpromoted to personal exercise successfully.");

        // Assert exercise is now owned by original owner
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Verify the exercise is still visible to admin (admin sees all exercises)
        $indexResponse = $this->actingAs($this->adminUser)->get(route('exercises.index'));
        $indexResponse->assertSee('Global Exercise');
        // Should not show "Everyone" badge anymore since it's now user-specific
        $indexResponse->assertDontSee('Everyone');
        // Should show the regular user's name since admin is viewing someone else's exercise
        $indexResponse->assertSee($this->regularUser->name);

        // Verify the exercise is visible to the original owner
        $ownerResponse = $this->actingAs($this->regularUser)->get(route('exercises.index'));
        $ownerResponse->assertSee('Global Exercise');
        // Should show "You" since the regular user is viewing their own exercise
        $ownerResponse->assertSee('You');
    }

    /** @test */
    public function error_scenario_displays_appropriate_message_when_other_users_have_logs()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create lift logs from multiple users
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => Carbon::now()->subDays(5), // Original owner (earliest)
        ]);

        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->adminUser->id,
            'logged_at' => Carbon::now()->subDays(3), // Other user
        ]);

        // Admin attempts to unpromote exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert error response (back() redirects to previous page)
        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Check specific error message
        $errors = session('errors');
        $errorMessage = $errors->first();
        $this->assertStringContainsString("Cannot unpromote exercise 'Global Exercise'", $errorMessage);
        $this->assertStringContainsString("1 other user has workout logs", $errorMessage);
        $this->assertStringContainsString("The exercise must remain global to preserve their data", $errorMessage);

        // Assert exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function error_scenario_displays_appropriate_message_when_no_original_owner_found()
    {
        // Create global exercise with no lift logs
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Admin attempts to unpromote exercise (no lift logs exist)
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert error response (back() redirects to previous page)
        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Check specific error message
        $errors = session('errors');
        $errorMessage = $errors->first();
        $this->assertStringContainsString("Cannot determine original owner for exercise 'Global Exercise'", $errorMessage);

        // Assert exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function error_scenario_displays_appropriate_message_for_non_global_exercise()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Admin attempts to unpromote non-global exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $userExercise));

        // Assert authorization error (403) because admin cannot unpromote non-global exercises
        $response->assertStatus(403);

        // Assert exercise remains user-specific
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function non_admin_user_cannot_access_unpromote_functionality()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Regular user attempts to unpromote exercise
        $response = $this->actingAs($this->regularUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert permission denied
        $response->assertStatus(403);

        // Assert exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_unpromote_functionality()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Unauthenticated user attempts unpromote
        $response = $this->post(route('exercises.unpromote', $globalExercise));

        // Should redirect to login
        $response->assertRedirect(route('login'));

        // Exercise should remain unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function unpromote_preserves_exercise_metadata_and_lift_logs()
    {
        // Create global exercise with specific metadata
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'description' => 'Original description',
            'exercise_type' => 'bodyweight',
            'user_id' => null,
            'created_at' => now()->subDays(10),
        ]);

        $originalCreatedAt = $globalExercise->created_at;

        // Create lift logs to establish original owner
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => Carbon::now()->subDays(5),
        ]);

        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);

        // Admin unpromotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert successful unpromote
        $response->assertRedirect(route('exercises.edit', $globalExercise));
        $response->assertSessionHas('success', "Exercise 'Global Exercise' unpromoted to personal exercise successfully.");

        // Refresh exercise from database
        $globalExercise->refresh();

        // Assert metadata is preserved
        $this->assertEquals($this->regularUser->id, $globalExercise->user_id); // Now owned by original owner
        $this->assertEquals('Global Exercise', $globalExercise->title);
        $this->assertEquals('Original description', $globalExercise->description);
        $this->assertEquals('bodyweight', $globalExercise->exercise_type);
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $globalExercise->created_at->format('Y-m-d H:i:s'));

        // Assert lift logs are preserved and still reference the exercise
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog1->id,
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog2->id,
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function unpromote_handles_multiple_users_error_message_correctly()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create another user
        $thirdUser = User::factory()->create();

        // Create lift logs from multiple users
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => Carbon::now()->subDays(5), // Original owner (earliest)
        ]);

        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->adminUser->id,
            'logged_at' => Carbon::now()->subDays(3), // Other user 1
        ]);

        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $thirdUser->id,
            'logged_at' => Carbon::now()->subDays(1), // Other user 2
        ]);

        // Admin attempts to unpromote exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.unpromote', $globalExercise));

        // Assert error response (back() redirects to previous page)
        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Check specific error message uses plural form
        $errors = session('errors');
        $errorMessage = $errors->first();
        $this->assertStringContainsString("Cannot unpromote exercise 'Global Exercise'", $errorMessage);
        $this->assertStringContainsString("2 other users have workout logs", $errorMessage);
        $this->assertStringContainsString("The exercise must remain global to preserve their data", $errorMessage);

        // Assert exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
    }
}