<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;

class AdminExerciseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_user_can_see_all_exercises_in_exercise_list()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create exercises from different users
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
        $adminExercise = Exercise::factory()->create([
            'title' => 'Admin Exercise',
            'user_id' => $admin->id,
        ]);
        $user1Exercise = Exercise::factory()->create([
            'title' => 'User 1 Exercise',
            'user_id' => $user1->id,
        ]);
        $user2Exercise = Exercise::factory()->create([
            'title' => 'User 2 Exercise',
            'user_id' => $user2->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Admin should see all exercises
        $response->assertSee('Global Exercise');
        $response->assertSee('Admin Exercise');
        $response->assertSee('User 1 Exercise');
        $response->assertSee('User 2 Exercise');
    }

    /** @test */
    public function regular_user_continues_to_see_only_scoped_exercises()
    {
        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create another user
        $otherUser = User::factory()->create();

        // Create exercises
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Regular user should see global and own exercises only
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function admin_can_see_user_identification_for_exercises()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular user
        $user = User::factory()->create(['name' => 'Regular User']);

        // Create exercises
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Should see exercises with user identification
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertSee('Regular User'); // User name should be visible
        $response->assertSee('Global'); // Global badge should be visible
    }

    /** @test */
    public function admin_exercise_list_maintains_existing_functionality()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create exercises
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
        $adminExercise = Exercise::factory()->create([
            'title' => 'Admin Exercise',
            'user_id' => $admin->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Should maintain existing functionality like edit/delete buttons
        $response->assertSee(route('exercises.edit', $globalExercise->id));
        $response->assertSee(route('exercises.edit', $adminExercise->id));
        // Should see create button
        $response->assertSee(route('exercises.create'));
    }

    /** @test */
    public function mixed_exercise_dataset_displays_correctly_for_admin()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create multiple users
        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        // Create mixed dataset
        $globalExercise1 = Exercise::factory()->create([
            'title' => 'Global Bench Press',
            'user_id' => null,
        ]);
        $globalExercise2 = Exercise::factory()->create([
            'title' => 'Global Squat',
            'user_id' => null,
        ]);
        $adminExercise = Exercise::factory()->create([
            'title' => 'Admin Deadlift',
            'user_id' => $admin->id,
        ]);
        $user1Exercise1 = Exercise::factory()->create([
            'title' => 'User1 Push-ups',
            'user_id' => $user1->id,
        ]);
        $user1Exercise2 = Exercise::factory()->create([
            'title' => 'User1 Pull-ups',
            'user_id' => $user1->id,
        ]);
        $user2Exercise = Exercise::factory()->create([
            'title' => 'User2 Dips',
            'user_id' => $user2->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Admin should see all exercises
        $response->assertSee('Global Bench Press');
        $response->assertSee('Global Squat');
        $response->assertSee('Admin Deadlift');
        $response->assertSee('User1 Push-ups');
        $response->assertSee('User1 Pull-ups');
        $response->assertSee('User2 Dips');
        
        // Should see user identification
        $response->assertSee('User One');
        $response->assertSee('User Two');
        // Admin's own exercise shows "You" instead of the admin's name
        $response->assertSee('You');
    }

    /** @test */
    public function regular_user_with_mixed_dataset_sees_only_scoped_exercises()
    {
        // Create regular user
        $user = User::factory()->create(['name' => 'Regular User']);
        $this->actingAs($user);

        // Create other users
        $otherUser1 = User::factory()->create(['name' => 'Other User 1']);
        $otherUser2 = User::factory()->create(['name' => 'Other User 2']);

        // Create mixed dataset
        $globalExercise1 = Exercise::factory()->create([
            'title' => 'Global Bench Press',
            'user_id' => null,
        ]);
        $globalExercise2 = Exercise::factory()->create([
            'title' => 'Global Squat',
            'user_id' => null,
        ]);
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);
        $otherUser1Exercise = Exercise::factory()->create([
            'title' => 'Other User 1 Exercise',
            'user_id' => $otherUser1->id,
        ]);
        $otherUser2Exercise = Exercise::factory()->create([
            'title' => 'Other User 2 Exercise',
            'user_id' => $otherUser2->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Regular user should see only global and own exercises
        $response->assertSee('Global Bench Press');
        $response->assertSee('Global Squat');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User 1 Exercise');
        $response->assertDontSee('Other User 2 Exercise');
        
        // Should not see other users' names
        $response->assertDontSee('Other User 1');
        $response->assertDontSee('Other User 2');
    }

    /** @test */
    public function admin_visibility_works_with_edge_case_scenarios()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create user with empty name
        $userWithoutName = User::factory()->create(['name' => '']);
        
        // Create exercises including edge cases
        $exerciseWithNullUser = Exercise::factory()->create([
            'title' => 'Exercise with Null User',
            'user_id' => null,
        ]);
        $exerciseWithUserWithoutName = Exercise::factory()->create([
            'title' => 'Exercise by User Without Name',
            'user_id' => $userWithoutName->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Admin should see all exercises including edge cases
        $response->assertSee('Exercise with Null User');
        $response->assertSee('Exercise by User Without Name');
    }

    /** @test */
    public function admin_can_access_show_logs_for_any_exercise()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular user and their exercise
        $user = User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        // Admin should be able to access showLogs for any exercise
        $response = $this->get(route('exercises.show-logs', $userExercise));
        $response->assertStatus(200);
        $response->assertSee('User Exercise');
    }

    /** @test */
    public function regular_user_cannot_access_show_logs_for_other_users_exercises()
    {
        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create another user and their exercise
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        // Regular user should not be able to access showLogs for other user's exercise
        $response = $this->get(route('exercises.show-logs', $otherUserExercise));
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_edit_exercises_from_other_users()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular user and their exercise
        $user = User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        // Admin should be able to edit other user's exercise
        $response = $this->get(route('exercises.edit', $userExercise));
        $response->assertStatus(200);
        $response->assertSee('User Exercise');

        // Admin should be able to update other user's exercise
        $response = $this->put(route('exercises.update', $userExercise), [
            'title' => 'Updated User Exercise',
            'description' => 'Updated description',
            'exercise_type' => 'regular'
        ]);
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Updated User Exercise',
            'description' => 'Updated description',
        ]);
    }

    /** @test */
    public function regular_user_cannot_edit_other_users_exercises()
    {
        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create another user and their exercise
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        // Regular user should not be able to edit other user's exercise
        $response = $this->get(route('exercises.edit', $otherUserExercise));
        $response->assertStatus(403);

        // Regular user should not be able to update other user's exercise
        $response = $this->put(route('exercises.update', $otherUserExercise), [
            'title' => 'Hacked Exercise',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_exercises_from_other_users()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular user and their exercise
        $user = User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        // Admin should be able to delete other user's exercise
        $response = $this->delete(route('exercises.destroy', $userExercise));
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted($userExercise);
    }

    public function regular_user_cannot_delete_other_users_exercises()
    {
        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create another user and their exercise
        $otherUser = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        // Regular user should not be able to delete other user's exercise
        $response = $this->delete(route('exercises.destroy', $otherUserExercise));
        $response->assertStatus(403);

        // Exercise should still exist and not be soft deleted
        $this->assertDatabaseHas('exercises', [
            'id' => $otherUserExercise->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function admin_can_promote_exercises_from_other_users()
    {
        // Create admin user with role
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create regular user and their exercise
        $user = User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        // Admin should be able to promote other user's exercise
        $response = $this->post(route('exercises.promote', $userExercise));
        $response->assertRedirect(route('exercises.edit', $userExercise));
        $response->assertSessionHas('success', "Exercise 'User Exercise' promoted to global status successfully.");

        // Exercise should now be global
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function regular_user_cannot_promote_other_users_exercises()
    {
        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create another user and their exercise
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        // Regular user should not be able to promote other user's exercise
        $response = $this->post(route('exercises.promote', $otherUserExercise));
        $response->assertStatus(403);
    }

    /** @test */
    public function role_verification_handles_edge_cases_properly()
    {
        // Test with user that has no role
        $userWithoutRole = User::factory()->create();
        
        // Test availableToUser scope with user without role
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $userExercise = Exercise::factory()->create(['user_id' => $userWithoutRole->id]);
        $otherUserExercise = Exercise::factory()->create(['user_id' => User::factory()->create()->id]);

        $availableExercises = Exercise::availableToUser($userWithoutRole->id)->get();
        
        // Should behave like regular user (global + own exercises only)
        $this->assertCount(2, $availableExercises);
        $this->assertTrue($availableExercises->contains($globalExercise));
        $this->assertTrue($availableExercises->contains($userExercise));
        $this->assertFalse($availableExercises->contains($otherUserExercise));
    }

    /** @test */
    public function invalid_user_id_defaults_to_regular_user_behavior()
    {
        // Create users first
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $userExercise = Exercise::factory()->create(['user_id' => $user1->id]);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $user2->id]);

        // Test with non-existent user ID
        $availableExercises = Exercise::availableToUser(999)->get();
        
        // Should return no exercises for security (invalid user ID)
        $this->assertCount(0, $availableExercises);
        $this->assertFalse($availableExercises->contains($globalExercise));
        $this->assertFalse($availableExercises->contains($userExercise));
        $this->assertFalse($availableExercises->contains($otherUserExercise));
    }


}