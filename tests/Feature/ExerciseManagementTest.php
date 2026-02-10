<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\LiftLog;

class ExerciseManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /** @test */
    public function authenticated_user_can_create_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'exercise_type' => 'regular',
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_create_exercise_with_missing_title()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => '', // Missing title
            'description' => $this->faker->paragraph(),
            'exercise_type' => 'regular',
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertSessionHasErrors('title');
    }

    /** @test */
    public function user_cannot_create_exercise_with_same_name_as_global_exercise()
    {
        // Create global exercise
        Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Bench Press',
            'description' => 'User trying to create duplicate',
            'exercise_type' => 'regular',
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertSessionHasErrors('title');
    }

    /** @test */
    public function user_can_edit_their_own_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $userExercise = Exercise::factory()->create([
            'title' => 'Original Title',
            'user_id' => $user->id,
        ]);

        $updatedData = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'exercise_type' => 'regular',
        ];

        $response = $this->put(route('exercises.update', $userExercise), $updatedData);

        $response->assertRedirect(route('exercises.edit', $userExercise));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Updated Title',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_cannot_edit_other_users_exercise()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        $updatedData = [
            'title' => 'Hacked Title',
            'description' => 'Hacked description',
        ];

        $response = $this->put(route('exercises.update', $otherUserExercise), $updatedData);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_delete_exercise_without_lift_logs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->delete(route('exercises.destroy', $exercise));

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise deleted successfully.');
        $this->assertSoftDeleted($exercise);
    }

    /** @test */
    public function cannot_delete_exercise_with_lift_logs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        LiftLog::factory()->create(['exercise_id' => $exercise->id, 'user_id' => $user->id]);

        $response = $this->delete(route('exercises.destroy', $exercise));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'deleted_at' => null]);
    }

    /** @test */
    public function admin_can_view_exercise_index_with_all_exercises()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);
        
        $user = User::factory()->create();

        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
    }

    /** @test */
    public function regular_users_cannot_access_exercise_index()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_edit_global_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $globalExercise = Exercise::factory()->create([
            'title' => 'Original Title',
            'user_id' => null,
        ]);

        $updatedData = [
            'title' => 'Updated Global Title',
            'description' => 'Updated description',
            'exercise_type' => 'regular',
        ];

        $response = $this->put(route('exercises.update', $globalExercise), $updatedData);

        $response->assertRedirect(route('exercises.edit', $globalExercise));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Updated Global Title',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function non_admin_cannot_edit_global_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        $updatedData = [
            'title' => 'Hacked Title',
            'description' => 'Hacked description',
        ];

        $response = $this->put(route('exercises.update', $globalExercise), $updatedData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_promote_user_exercise_to_global()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $userExercise = Exercise::factory()->create([
            'user_id' => $admin->id,
            'title' => 'User Exercise',
        ]);

        $response = $this->post(route('exercises.promote', $userExercise));

        $response->assertRedirect(route('exercises.edit', $userExercise));
        $response->assertSessionHas('success', "Exercise 'User Exercise' promoted to global status successfully.");
        
        $userExercise->refresh();
        $this->assertNull($userExercise->user_id);
        $this->assertTrue($userExercise->isGlobal());
    }

    /** @test */
    public function admin_can_unpromote_global_exercise_to_user_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create a global exercise with lift logs from admin (to determine original owner)
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
        
        // Create lift log to establish original ownership
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $admin->id,
        ]);

        $response = $this->post(route('exercises.unpromote', $globalExercise));

        $response->assertRedirect(route('exercises.edit', $globalExercise));
        $response->assertSessionHas('success', "Exercise 'Global Exercise' unpromoted to personal exercise successfully.");
        
        $globalExercise->refresh();
        $this->assertEquals($admin->id, $globalExercise->user_id);
        $this->assertFalse($globalExercise->isGlobal());
    }

    /** @test */
    public function admin_editing_user_exercise_preserves_original_user_id()
    {
        // Create a regular user and an admin
        $regularUser = User::factory()->create();
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);

        // Create a user exercise owned by the regular user
        $userExercise = Exercise::factory()->create([
            'user_id' => $regularUser->id,
            'title' => 'User Exercise',
            'description' => 'Original description',
            'exercise_type' => 'regular'
        ]);

        // Admin edits the exercise (without changing global status)
        $this->actingAs($admin);
        $response = $this->put(route('exercises.update', $userExercise), [
            'title' => 'Updated User Exercise',
            'description' => 'Updated description',
            'exercise_type' => 'regular',
        ]);

        $response->assertRedirect(route('exercises.edit', $userExercise));
        $response->assertSessionHas('success', 'Exercise updated successfully.');

        // Verify the exercise still belongs to the original user
        $userExercise->refresh();
        $this->assertEquals($regularUser->id, $userExercise->user_id);
        $this->assertFalse($userExercise->isGlobal());
        $this->assertEquals('Updated User Exercise', $userExercise->title);
        $this->assertEquals('Updated description', $userExercise->description);
    }

    /** @test */
    public function forms_do_not_show_global_checkbox()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Test create form
        $response = $this->get(route('exercises.create'));
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertDontSee('Make this exercise available to all users');

        // Test edit form
        $exercise = Exercise::factory()->create(['user_id' => $admin->id]);
        $response = $this->get(route('exercises.edit', $exercise));
        $response->assertStatus(200);
        $response->assertDontSee('Global Exercise');
        $response->assertDontSee('Make this exercise available to all users');
    }

    /** @test */
    public function admin_cannot_promote_exercise_with_duplicate_global_name()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create a global exercise
        Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        // Create a user exercise with the same name
        $userExercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => $admin->id,
        ]);

        // Try to promote the user exercise to global (should fail due to duplicate name)
        $response = $this->post(route('exercises.promote', $userExercise));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        
        // Verify the exercise is still a user exercise
        $userExercise->refresh();
        $this->assertEquals($admin->id, $userExercise->user_id);
        $this->assertFalse($userExercise->isGlobal());
    }
}