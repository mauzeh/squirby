<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExerciseBulkPromotionTest extends TestCase
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
    public function admin_can_bulk_promote_user_exercises_to_global()
    {
        // Create user exercises for promotion
        $userExercise1 = Exercise::factory()->create([
            'title' => 'User Exercise 1',
            'user_id' => $this->regularUser->id,
        ]);
        
        $userExercise2 = Exercise::factory()->create([
            'title' => 'User Exercise 2', 
            'user_id' => $this->adminUser->id,
        ]);

        $userExercise3 = Exercise::factory()->create([
            'title' => 'User Exercise 3',
            'user_id' => $this->regularUser->id,
        ]);

        // Admin promotes selected exercises
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise1->id, $userExercise2->id, $userExercise3->id]
            ]);

        // Assert successful response
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Successfully promoted 3 exercise(s) to global status.');

        // Assert exercises are now global (user_id is null)
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise1->id,
            'title' => 'User Exercise 1',
            'user_id' => null,
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise2->id,
            'title' => 'User Exercise 2',
            'user_id' => null,
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise3->id,
            'title' => 'User Exercise 3',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function non_admin_user_cannot_access_bulk_promotion()
    {
        // Create user exercises
        $userExercise1 = Exercise::factory()->create([
            'title' => 'User Exercise 1',
            'user_id' => $this->regularUser->id,
        ]);
        
        $userExercise2 = Exercise::factory()->create([
            'title' => 'User Exercise 2',
            'user_id' => $this->regularUser->id,
        ]);

        // Regular user attempts to promote exercises
        $response = $this->actingAs($this->regularUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise1->id, $userExercise2->id]
            ]);

        // Assert permission denied
        $response->assertStatus(403);

        // Assert exercises remain user-specific
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise1->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise2->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function bulk_promotion_fails_when_trying_to_promote_already_global_exercises()
    {
        // Create user exercise owned by admin (so they can authorize it)
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->adminUser->id,
        ]);
        
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Admin attempts to promote both (should fail due to authorization on global exercise)
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise->id, $globalExercise->id]
            ]);

        // Assert authorization failure (403) because admin cannot promote global exercises
        $response->assertStatus(403);

        // Assert user exercise remains unchanged (no partial promotion)
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => $this->adminUser->id,
        ]);
        
        // Assert global exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function bulk_promotion_validates_required_exercise_ids()
    {
        // Admin attempts promotion without exercise IDs
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), []);

        // Assert validation error
        $response->assertSessionHasErrors(['exercise_ids']);
    }

    /** @test */
    public function bulk_promotion_validates_exercise_ids_exist()
    {
        // Admin attempts promotion with non-existent exercise IDs
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [999, 1000] // Non-existent IDs
            ]);

        // Assert validation errors for each invalid ID
        $response->assertSessionHasErrors(['exercise_ids.0', 'exercise_ids.1']);
    }

    /** @test */
    public function bulk_promotion_preserves_existing_lift_logs()
    {
        // Create user exercise with lift logs
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise With Logs',
            'user_id' => $this->regularUser->id,
        ]);
        
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $userExercise->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Admin promotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise->id]
            ]);

        // Assert successful promotion
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Successfully promoted 1 exercise(s) to global status.');

        // Assert exercise is now global
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => null,
        ]);

        // Assert lift logs are preserved and still reference the exercise
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog1->id,
            'exercise_id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog2->id,
            'exercise_id' => $userExercise->id,
            'user_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function bulk_promotion_maintains_exercise_metadata()
    {
        // Create user exercise with specific metadata
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'description' => 'Original description',
            'is_bodyweight' => true,
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(3),
        ]);

        $originalCreatedAt = $userExercise->created_at;
        $originalUpdatedAt = $userExercise->updated_at;

        // Admin promotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise->id]
            ]);

        // Assert successful promotion
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Successfully promoted 1 exercise(s) to global status.');

        // Refresh exercise from database
        $userExercise->refresh();

        // Assert metadata is preserved
        $this->assertNull($userExercise->user_id); // Now global
        $this->assertEquals('User Exercise', $userExercise->title);
        $this->assertEquals('Original description', $userExercise->description);
        $this->assertTrue($userExercise->is_bodyweight);
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $userExercise->created_at->format('Y-m-d H:i:s'));
        // Updated_at should change due to the promotion
        $this->assertNotEquals($originalUpdatedAt->format('Y-m-d H:i:s'), $userExercise->updated_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function promoted_exercises_become_visible_to_all_users_immediately()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'Soon To Be Global Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Create another user who shouldn't see the exercise initially
        $otherUser = User::factory()->create();

        // Verify other user cannot see the user-specific exercise initially
        $this->actingAs($otherUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertDontSee('Soon To Be Global Exercise');

        // Admin promotes the exercise
        $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$userExercise->id]
            ]);

        // Verify other user can now see the promoted exercise
        $this->actingAs($otherUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertSee('Soon To Be Global Exercise');
        
        // Verify it shows as global in the UI
        $indexResponse->assertSee('Global'); // Badge text
    }

    /** @test */
    public function bulk_promotion_ui_is_only_visible_to_admin_users()
    {
        // Create some user exercises for regular user
        Exercise::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
        ]);

        // Create some user exercises for admin user (so they have exercises to see)
        Exercise::factory()->count(2)->create([
            'user_id' => $this->adminUser->id,
        ]);

        // Regular user should not see promotion UI
        $this->actingAs($this->regularUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Promote'); // Promotion button text
        $indexResponse->assertDontSee('fa-globe'); // Globe icon

        // Admin user should see promotion UI (when they have exercises)
        $this->actingAs($this->adminUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Promote'); // Promotion button text
        $indexResponse->assertSee('fa-globe'); // Globe icon
    }

    /** @test */
    public function complete_admin_bulk_promotion_workflow_from_ui_to_database()
    {
        // Create multiple user exercises owned by admin (so they can see and promote them)
        $userExercise1 = Exercise::factory()->create([
            'title' => 'User Exercise 1',
            'description' => 'First exercise to promote',
            'user_id' => $this->adminUser->id,
        ]);
        
        $userExercise2 = Exercise::factory()->create([
            'title' => 'User Exercise 2',
            'description' => 'Second exercise to promote',
            'user_id' => $this->adminUser->id,
        ]);
        
        $userExercise3 = Exercise::factory()->create([
            'title' => 'User Exercise 3',
            'description' => 'Third exercise to promote',
            'user_id' => $this->adminUser->id,
        ]);

        // Create a global exercise that should not be affected
        $globalExercise = Exercise::factory()->create([
            'title' => 'Existing Global Exercise',
            'user_id' => null,
        ]);

        // Step 1: Admin views exercise index and sees promotion UI
        $this->actingAs($this->adminUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(200);
        
        // Should see admin's own exercises and global exercises (availableToUser scope)
        $indexResponse->assertSee('User Exercise 1');
        $indexResponse->assertSee('User Exercise 2');
        $indexResponse->assertSee('User Exercise 3');
        $indexResponse->assertSee('Existing Global Exercise');
        
        // Should see promotion UI elements
        $indexResponse->assertSee('Promote');
        $indexResponse->assertSee('fa-globe');
        
        // Should see checkboxes for selection
        $indexResponse->assertSee('exercise-checkbox');

        // Step 2: Admin submits bulk promotion form
        $promotionResponse = $this->post(route('exercises.promote-selected'), [
            'exercise_ids' => [$userExercise1->id, $userExercise2->id, $userExercise3->id]
        ]);

        // Step 3: Verify successful response and redirect
        $promotionResponse->assertRedirect(route('exercises.index'));
        $promotionResponse->assertSessionHas('success', 'Successfully promoted 3 exercise(s) to global status.');
        $promotionResponse->assertSessionHasNoErrors();

        // Step 4: Verify database changes
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise1->id,
            'title' => 'User Exercise 1',
            'user_id' => null, // Now global
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise2->id,
            'title' => 'User Exercise 2',
            'user_id' => null, // Now global
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise3->id,
            'title' => 'User Exercise 3',
            'user_id' => null, // Now global
        ]);

        // Existing global exercise should remain unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Existing Global Exercise',
            'user_id' => null,
        ]);

        // Step 5: Verify UI refresh shows updated exercise status
        $refreshedIndexResponse = $this->get(route('exercises.index'));
        $refreshedIndexResponse->assertStatus(200);
        
        // Should still see all exercises (now all global)
        $refreshedIndexResponse->assertSee('User Exercise 1');
        $refreshedIndexResponse->assertSee('User Exercise 2');
        $refreshedIndexResponse->assertSee('User Exercise 3');
        $refreshedIndexResponse->assertSee('Existing Global Exercise');
        
        // Should see Global badges for promoted exercises
        $refreshedIndexResponse->assertSee('Global');

        // Step 6: Verify other users can now see promoted exercises
        $this->actingAs($this->regularUser);
        $userIndexResponse = $this->get(route('exercises.index'));
        $userIndexResponse->assertStatus(200);
        
        // Regular user should now see promoted exercises as global
        $userIndexResponse->assertSee('User Exercise 1');
        $userIndexResponse->assertSee('User Exercise 2');
        $userIndexResponse->assertSee('User Exercise 3');
        $userIndexResponse->assertSee('Existing Global Exercise');
        $userIndexResponse->assertSee('Global'); // Badge for global exercises
    }

    /** @test */
    public function bulk_promotion_handles_mixed_valid_and_invalid_selections_correctly()
    {
        // Create valid user exercises owned by admin
        $validExercise1 = Exercise::factory()->create([
            'title' => 'Valid Exercise 1',
            'user_id' => $this->adminUser->id,
        ]);
        
        $validExercise2 = Exercise::factory()->create([
            'title' => 'Valid Exercise 2',
            'user_id' => $this->adminUser->id,
        ]);

        // Create invalid exercise (already global)
        $invalidExercise = Exercise::factory()->create([
            'title' => 'Already Global Exercise',
            'user_id' => null,
        ]);

        // Admin attempts to promote mix of valid and invalid exercises
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$validExercise1->id, $validExercise2->id, $invalidExercise->id]
            ]);

        // Should fail with authorization error (403) because admin cannot promote global exercises
        $response->assertStatus(403);

        // All exercises should remain in their original state (no partial promotion)
        $this->assertDatabaseHas('exercises', [
            'id' => $validExercise1->id,
            'user_id' => $this->adminUser->id, // Still user-specific
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $validExercise2->id,
            'user_id' => $this->adminUser->id, // Still user-specific
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $invalidExercise->id,
            'user_id' => null, // Still global
        ]);
    }

    /** @test */
    public function admin_gets_error_message_when_trying_to_promote_already_global_exercise_they_created()
    {
        // Create a user exercise and promote it to global first
        $exercise = Exercise::factory()->create([
            'title' => 'Exercise To Promote',
            'user_id' => $this->adminUser->id,
        ]);

        // First promotion should succeed
        $firstResponse = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$exercise->id]
            ]);

        $firstResponse->assertRedirect(route('exercises.index'));
        $firstResponse->assertSessionHas('success', 'Successfully promoted 1 exercise(s) to global status.');

        // Verify exercise is now global
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'user_id' => null,
        ]);

        // Second promotion attempt should fail with authorization error
        // because the policy prevents promoting global exercises
        $secondResponse = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$exercise->id]
            ]);

        $secondResponse->assertStatus(403);
    }

    /** @test */
    public function bulk_promotion_success_message_reflects_actual_count()
    {
        // Test with single exercise
        $singleExercise = Exercise::factory()->create([
            'title' => 'Single Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        $singleResponse = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => [$singleExercise->id]
            ]);

        $singleResponse->assertSessionHas('success', 'Successfully promoted 1 exercise(s) to global status.');

        // Test with multiple exercises
        $multipleExercises = Exercise::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
        ]);

        $multipleResponse = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote-selected'), [
                'exercise_ids' => $multipleExercises->pluck('id')->toArray()
            ]);

        $multipleResponse->assertSessionHas('success', 'Successfully promoted 5 exercise(s) to global status.');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_bulk_promotion()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Unauthenticated user attempts promotion
        $response = $this->post(route('exercises.promote-selected'), [
            'exercise_ids' => [$userExercise->id]
        ]);

        // Should redirect to login
        $response->assertRedirect(route('login'));

        // Exercise should remain unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
    }
}