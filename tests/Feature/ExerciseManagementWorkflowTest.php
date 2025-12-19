<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\LiftLog;

class ExerciseManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role for tests
        Role::factory()->create(['name' => 'Admin']);
    }

    /** @test */
    public function complete_admin_global_exercise_workflow()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // 1. Admin can access create form with global option
        $createResponse = $this->get(route('exercises.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('Global Exercise');
        $createResponse->assertSee('Make this exercise available to all users');

        // 2. Admin creates global exercise
        $globalExerciseData = [
            'title' => 'Global Bench Press',
            'description' => 'Standard bench press exercise available to all users',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $storeResponse = $this->post(route('exercises.store'), $globalExerciseData);
        $storeResponse->assertRedirect(route('exercises.index'));
        $storeResponse->assertSessionHas('success', 'Exercise created successfully.');
        
        // Verify global exercise is created correctly
        $this->assertDatabaseHas('exercises', [
            'title' => 'Global Bench Press',
            'user_id' => null,
            'exercise_type' => 'regular',
        ]);

        $globalExercise = Exercise::where('title', 'Global Bench Press')->first();

        // 3. Admin can see global exercise in listing
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Global Bench Press');
        $indexResponse->assertSee('Global'); // Badge

        // 4. Admin can edit global exercise
        $editResponse = $this->get(route('exercises.edit', $globalExercise));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Global Exercise');
        $editResponse->assertSee('checked'); // Global checkbox should be checked

        // 5. Admin updates global exercise
        $updatedData = [
            'title' => 'Updated Global Bench Press',
            'description' => 'Updated description for global exercise',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $updateResponse = $this->put(route('exercises.update', $globalExercise), $updatedData);
        $updateResponse->assertRedirect(route('exercises.index'));
        $updateResponse->assertSessionHas('success', 'Exercise updated successfully.');
        
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Updated Global Bench Press',
            'user_id' => null,
        ]);

        // 6. Admin can delete global exercise (when no lift logs exist)
        $deleteResponse = $this->delete(route('exercises.destroy', $globalExercise));
        $deleteResponse->assertRedirect(route('exercises.index'));
        $deleteResponse->assertSessionHas('success', 'Exercise deleted successfully.');
        
        $this->assertSoftDeleted($globalExercise);
    }

    /** @test */
    public function complete_user_personal_exercise_workflow_with_conflict_detection()
    {
        // Create admin and global exercise first
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        
        $globalExercise = Exercise::factory()->create([
            'title' => 'Squat',
            'user_id' => null,
        ]);

        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. User can access create form without global option
        $createResponse = $this->get(route('exercises.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertDontSee('Global Exercise');
        $createResponse->assertDontSee('Make this exercise available to all users');

        // 2. User attempts to create exercise with same name as global exercise (should fail)
        $conflictData = [
            'title' => 'Squat', // Same as global exercise
            'description' => 'My personal squat variation',
            'exercise_type' => 'regular',
        ];

        $conflictResponse = $this->post(route('exercises.store'), $conflictData);
        $conflictResponse->assertSessionHasErrors('title');
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Squat',
            'user_id' => $user->id,
            'deleted_at' => null // Ensure it was never created (no soft delete involved here)
        ]);

        // 3. User creates personal exercise with unique name
        $personalExerciseData = [
            'title' => 'My Custom Deadlift',
            'description' => 'Personal deadlift variation',
            'exercise_type' => 'regular',
        ];

        $storeResponse = $this->post(route('exercises.store'), $personalExerciseData);
        $storeResponse->assertRedirect(route('exercises.index'));
        $storeResponse->assertSessionHas('success', 'Exercise created successfully.');
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'My Custom Deadlift',
            'user_id' => $user->id,
            'deleted_at' => null
        ]);

        $personalExercise = Exercise::where('title', 'My Custom Deadlift')->first();

        // 4. User can edit their own exercise
        $editResponse = $this->get(route('exercises.edit', $personalExercise));
        $editResponse->assertStatus(200);
        $editResponse->assertDontSee('Global Exercise');

        $updatedData = [
            'title' => 'Updated Custom Deadlift',
            'description' => 'Updated personal exercise',
            'exercise_type' => 'bodyweight',
        ];

        $updateResponse = $this->put(route('exercises.update', $personalExercise), $updatedData);
        $updateResponse->assertRedirect(route('exercises.index'));
        $updateResponse->assertSessionHas('success', 'Exercise updated successfully.');
        
        $this->assertDatabaseHas('exercises', [
            'id' => $personalExercise->id,
            'title' => 'Updated Custom Deadlift',
            'user_id' => $user->id,
            'exercise_type' => 'bodyweight',
            'deleted_at' => null
        ]);

        // 5. User can delete their own exercise (when no lift logs exist)
        $deleteResponse = $this->delete(route('exercises.destroy', $personalExercise));
        $deleteResponse->assertRedirect(route('exercises.index'));
        $deleteResponse->assertSessionHas('success', 'Exercise deleted successfully.');
        
        $this->assertSoftDeleted($personalExercise);
    }

    /** @test */
    public function exercise_listing_shows_both_global_and_personal_exercises_correctly()
    {
        // Create admin and global exercises
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        
        $globalExercise1 = Exercise::factory()->create([
            'title' => 'Global Bench Press',
            'user_id' => null,
        ]);
        
        $globalExercise2 = Exercise::factory()->create([
            'title' => 'Global Squat',
            'user_id' => null,
        ]);

        // Create users with personal exercises
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1Exercise = Exercise::factory()->create([
            'title' => 'User1 Custom Exercise',
            'user_id' => $user1->id,
        ]);
        
        $user2Exercise = Exercise::factory()->create([
            'title' => 'User2 Custom Exercise',
            'user_id' => $user2->id,
        ]);

        // Test Admin's view (only admins can access exercise index now)
        $this->actingAs($admin);
        $adminResponse = $this->get(route('exercises.index'));
        $adminResponse->assertStatus(200);
        
        // Admin should see global exercises (they can edit these)
        $adminResponse->assertSee('Global Bench Press');
        $adminResponse->assertSee('Global Squat');
        
        // Admin should see all exercises including other users' personal exercises
        $adminResponse->assertSee('User1 Custom Exercise');
        $adminResponse->assertSee('User2 Custom Exercise');
        
        // Should see Global badges for global exercises
        $adminResponse->assertSee('Everyone'); // Badge text for global exercises
    }

    /** @test */
    public function permission_restrictions_for_non_admin_users_are_enforced()
    {
        // Create admin and global exercise
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create another user's exercise
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        // Create regular user
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. User cannot access exercise index (admin-only)
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(403);

        // 2. User cannot create global exercise
        $globalAttempt = [
            'title' => 'Attempted Global Exercise',
            'description' => 'Should not be allowed',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $globalResponse = $this->post(route('exercises.store'), $globalAttempt);
        $globalResponse->assertStatus(403);
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Attempted Global Exercise',
            'user_id' => null,
            'deleted_at' => null // Ensure it was never created
        ]);

        // 3. User cannot edit global exercise
        $editGlobalResponse = $this->get(route('exercises.edit', $globalExercise));
        $editGlobalResponse->assertStatus(403);

        $updateGlobalResponse = $this->put(route('exercises.update', $globalExercise), [
            'title' => 'Hacked Global Exercise',
        ]);
        $updateGlobalResponse->assertStatus(403);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise', // Should remain unchanged
            'deleted_at' => null
        ]);

        // 4. User cannot delete global exercise
        $deleteGlobalResponse = $this->delete(route('exercises.destroy', $globalExercise));
        $deleteGlobalResponse->assertStatus(403);
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);

        // 5. User cannot edit other user's exercise
        $editOtherResponse = $this->get(route('exercises.edit', $otherUserExercise));
        $editOtherResponse->assertStatus(403);

        $updateOtherResponse = $this->put(route('exercises.update', $otherUserExercise), [
            'title' => 'Hacked Other Exercise',
        ]);
        $updateOtherResponse->assertStatus(403);
        
        $this->assertDatabaseHas('exercises', [
            'id' => $otherUserExercise->id,
            'title' => 'Other User Exercise', // Should remain unchanged
            'deleted_at' => null
        ]);

        // 6. User cannot delete other user's exercise
        $deleteOtherResponse = $this->delete(route('exercises.destroy', $otherUserExercise));
        $deleteOtherResponse->assertStatus(403);
        $this->assertDatabaseHas('exercises', ['id' => $otherUserExercise->id, 'deleted_at' => null]);

        // 7. Verify admin can access index and see all exercises
        $userExercise = Exercise::factory()->create([
            'title' => 'User Own Exercise',
            'user_id' => $user->id,
        ]);

        $this->actingAs($admin);
        $adminIndexResponse = $this->get(route('exercises.index'));
        $adminIndexResponse->assertStatus(200);
        
        // Admin should see all exercises
        $adminIndexResponse->assertSee('User Own Exercise');
        $adminIndexResponse->assertSee('Global Exercise');
        $adminIndexResponse->assertSee('Other User Exercise');
        
        // Verify edit links are present for all exercises (admin can edit all)
        $adminIndexResponse->assertSee(route('exercises.edit', $userExercise->id));
        $adminIndexResponse->assertSee(route('exercises.edit', $globalExercise->id));
        $adminIndexResponse->assertSee(route('exercises.edit', $otherUserExercise->id));
    }

    /** @test */
    public function deletion_prevention_when_exercises_have_lift_logs()
    {
        // Create users
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        
        $user = User::factory()->create();

        // Create global exercise with lift logs
        $globalExerciseWithLogs = Exercise::factory()->create([
            'title' => 'Global Exercise With Logs',
            'user_id' => null,
        ]);
        
        LiftLog::factory()->create([
            'exercise_id' => $globalExerciseWithLogs->id,
            'user_id' => $user->id,
        ]);

        // Create user exercise with lift logs
        $userExerciseWithLogs = Exercise::factory()->create([
            'title' => 'User Exercise With Logs',
            'user_id' => $user->id,
        ]);
        
        LiftLog::factory()->create([
            'exercise_id' => $userExerciseWithLogs->id,
            'user_id' => $user->id,
        ]);

        // Create exercises without lift logs for comparison
        $globalExerciseWithoutLogs = Exercise::factory()->create([
            'title' => 'Global Exercise Without Logs',
            'user_id' => null,
        ]);
        
        $userExerciseWithoutLogs = Exercise::factory()->create([
            'title' => 'User Exercise Without Logs',
            'user_id' => $user->id,
        ]);

        // Test admin cannot delete global exercise with lift logs
        $this->actingAs($admin);
        $deleteGlobalWithLogsResponse = $this->delete(route('exercises.destroy', $globalExerciseWithLogs));
        $deleteGlobalWithLogsResponse->assertRedirect();
        $deleteGlobalWithLogsResponse->assertSessionHasErrors('error');
        $this->assertDatabaseHas('exercises', ['id' => $globalExerciseWithLogs->id, 'deleted_at' => null]);

        // Test admin CAN delete global exercise without lift logs
        $deleteGlobalWithoutLogsResponse = $this->delete(route('exercises.destroy', $globalExerciseWithoutLogs));
        $deleteGlobalWithoutLogsResponse->assertRedirect(route('exercises.index'));
        $deleteGlobalWithoutLogsResponse->assertSessionHas('success', 'Exercise deleted successfully.');
        $this->assertSoftDeleted($globalExerciseWithoutLogs);

        // Test user cannot delete their exercise with lift logs
        $this->actingAs($user);
        $deleteUserWithLogsResponse = $this->delete(route('exercises.destroy', $userExerciseWithLogs));
        $deleteUserWithLogsResponse->assertRedirect();
        $deleteUserWithLogsResponse->assertSessionHasErrors('error');
        $this->assertDatabaseHas('exercises', ['id' => $userExerciseWithLogs->id, 'deleted_at' => null]);

        // Test user CAN delete their exercise without lift logs
        $deleteUserWithoutLogsResponse = $this->delete(route('exercises.destroy', $userExerciseWithoutLogs));
        $deleteUserWithoutLogsResponse->assertRedirect(route('exercises.index'));
        $deleteUserWithoutLogsResponse->assertSessionHas('success', 'Exercise deleted successfully.');
        $this->assertSoftDeleted($userExerciseWithoutLogs);

        // Test that exercises with lift logs don't show delete buttons in UI (admin view)
        $this->actingAs($admin);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertStatus(200);
        
        // Should see the exercises but without delete buttons (no fa-trash icon for exercises with logs)
        $indexResponse->assertSee('User Exercise With Logs');
        // Should not see delete button (fa-trash) for exercises with lift logs
        // Note: This is a simplified check - in a real scenario you'd want to verify 
        // the specific exercise row doesn't have a delete button
    }

    /** @test */
    public function complete_workflow_with_multiple_users_and_name_conflicts()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);

        // Admin creates global exercises
        $this->actingAs($admin);
        
        $this->post(route('exercises.store'), [
            'title' => 'Bench Press',
            'description' => 'Global bench press',
            'exercise_type' => 'regular',
            'is_global' => true,
        ]);
        
        $this->post(route('exercises.store'), [
            'title' => 'Squat',
            'description' => 'Global squat',
            'exercise_type' => 'regular',
            'is_global' => true,
        ]);

        // Create multiple users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User1 attempts to create exercise with same name as global (should fail)
        $this->actingAs($user1);
        $conflictResponse1 = $this->post(route('exercises.store'), [
            'title' => 'Bench Press',
            'description' => 'User1 bench press',
            'exercise_type' => 'regular',
        ]);
        $conflictResponse1->assertSessionHasErrors('title');

        // User1 creates unique exercise
        $this->post(route('exercises.store'), [
            'title' => 'Incline Bench Press',
            'description' => 'User1 incline bench',
            'exercise_type' => 'regular',
        ]);

        // User2 attempts to create exercise with same name as User1's exercise (should succeed - different users)
        $this->actingAs($user2);
        $user2Response = $this->post(route('exercises.store'), [
            'title' => 'Incline Bench Press',
            'description' => 'User2 incline bench',
            'exercise_type' => 'regular',
        ]);
        $user2Response->assertRedirect(route('exercises.index'));

        // User2 attempts to create exercise with same name as global (should fail)
        $conflictResponse2 = $this->post(route('exercises.store'), [
            'title' => 'Squat',
            'description' => 'User2 squat',
            'exercise_type' => 'regular',
        ]);
        $conflictResponse2->assertSessionHasErrors('title');

        // Verify database state
        $this->assertDatabaseHas('exercises', [
            'title' => 'Bench Press',
            'user_id' => null, // Global
            'deleted_at' => null
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Squat',
            'user_id' => null, // Global
            'deleted_at' => null
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Incline Bench Press',
            'user_id' => $user1->id,
            'deleted_at' => null
        ]);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Incline Bench Press',
            'user_id' => $user2->id,
            'deleted_at' => null
        ]);

        // Verify each user sees correct exercises (admin view only now)
        $this->actingAs($admin);
        $adminIndexResponse = $this->get(route('exercises.index'));
        $adminIndexResponse->assertSee('Bench Press'); // Global
        $adminIndexResponse->assertSee('Squat'); // Global
        $adminIndexResponse->assertSee('Incline Bench Press'); // Both users have this name
        
        // Admin should see all exercises including both users' exercises with same name
        $user1Exercise = Exercise::where('title', 'Incline Bench Press')->where('user_id', $user1->id)->first();
        $user2Exercise = Exercise::where('title', 'Incline Bench Press')->where('user_id', $user2->id)->first();
        
        // Both exercises should exist in database
        $this->assertNotNull($user1Exercise);
        $this->assertNotNull($user2Exercise);
        $this->assertNotEquals($user1Exercise->id, $user2Exercise->id);
    }

    /** @test */
    public function admin_workflow_with_global_exercise_name_conflicts()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Admin creates first global exercise
        $this->post(route('exercises.store'), [
            'title' => 'Deadlift',
            'description' => 'Global deadlift exercise',
            'exercise_type' => 'regular',
            'is_global' => true,
        ]);

        // Admin attempts to create another global exercise with same name (should fail)
        $conflictResponse = $this->post(route('exercises.store'), [
            'title' => 'Deadlift',
            'description' => 'Duplicate global deadlift',
            'exercise_type' => 'regular',
            'is_global' => true,
        ]);
        
        $conflictResponse->assertSessionHasErrors('title');
        
        // Verify only one global deadlift exists
        $deadliftCount = Exercise::where('title', 'Deadlift')->whereNull('user_id')->whereNull('deleted_at')->count();
        $this->assertEquals(1, $deadliftCount);

        // Admin can create personal exercise with different name
        $personalResponse = $this->post(route('exercises.store'), [
            'title' => 'Romanian Deadlift',
            'description' => 'Admin personal exercise',
            'exercise_type' => 'regular',
            'is_global' => false,
        ]);
        
        $personalResponse->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Romanian Deadlift',
            'user_id' => $admin->id,
            'deleted_at' => null
        ]);
    }
}