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
        $this->assertDatabaseMissing('exercises', [
            'user_id' => $user->id,
            'description' => $exerciseData['description'],
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_exercise()
    {
        $exerciseData = [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'exercise_type' => 'regular',
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('exercises', $exerciseData);
    }

    /** @test */
    public function authenticated_user_can_create_bodyweight_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Bodyweight Squat',
            'description' => 'A squat performed without external weight.',
            'exercise_type' => 'bodyweight',
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
            'exercise_type' => 'bodyweight',
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'regular']);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'exercise_type' => 'bodyweight',
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'bodyweight',
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_not_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'bodyweight']);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'exercise_type' => 'regular',
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'regular',
        ]);
    }

    /** @test */
    public function admin_can_create_global_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $exerciseData = [
            'title' => 'Global Bench Press',
            'description' => 'A global exercise available to all users',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
        ]);
    }

    /** @test */
    public function non_admin_cannot_create_global_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Global Bench Press',
            'description' => 'A global exercise available to all users',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('exercises', [
            'user_id' => null,
            'title' => $exerciseData['title'],
        ]);
    }

    /** @test */
    public function user_cannot_create_exercise_with_same_name_as_global_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        
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
        $this->assertDatabaseMissing('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
        ]);
    }

    /** @test */
    public function admin_cannot_create_global_exercise_with_duplicate_name()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        // Create first global exercise
        Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        $exerciseData = [
            'title' => 'Bench Press',
            'description' => 'Duplicate global exercise',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertSessionHasErrors('title');
    }

    /** @test */
    public function index_shows_both_global_and_user_exercises()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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

        // Create another user's exercise (should not be visible)
        $otherUser = User::factory()->create();
        Exercise::factory()->create([
            'title' => 'Other User Exercise',
            'user_id' => $otherUser->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertDontSee('Other User Exercise');
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
            'is_global' => true,
        ];

        $response = $this->put(route('exercises.update', $globalExercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
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
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
        ]);
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

        $response->assertRedirect(route('exercises.index'));
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
        $this->assertDatabaseHas('exercises', [
            'id' => $otherUserExercise->id,
            'title' => 'Other User Exercise',
        ]);
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
    public function admin_can_delete_global_exercise_without_lift_logs()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->delete(route('exercises.destroy', $globalExercise));

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise deleted successfully.');
        $this->assertSoftDeleted($globalExercise);
    }

    /** @test */
    public function admin_cannot_delete_global_exercise_with_lift_logs()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        LiftLog::factory()->create(['exercise_id' => $globalExercise->id, 'user_id' => $admin->id]);

        $response = $this->delete(route('exercises.destroy', $globalExercise));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);
    }

    /** @test */
    public function create_form_shows_global_option_for_admin()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $response = $this->get(route('exercises.create'));

        $response->assertStatus(200);
        $response->assertViewHas('canCreateGlobal', true);
    }

    /** @test */
    public function create_form_does_not_show_global_option_for_regular_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('exercises.create'));

        $response->assertStatus(200);
        $response->assertViewHas('canCreateGlobal', false);
    }

    /** @test */
    public function edit_form_shows_global_option_for_admin()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $exercise = Exercise::factory()->create(['user_id' => $admin->id]);

        $response = $this->get(route('exercises.edit', $exercise));

        $response->assertStatus(200);
        $response->assertViewHas('canCreateGlobal', true);
    }


    public function index_view_displays_global_badge_for_global_exercises()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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
        $response->assertSee('Global', false); // Badge text
        $response->assertSee('Personal', false); // Scope text
    }

    /** @test */
    public function index_view_shows_edit_button_only_for_editable_exercises()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create global exercise (not editable by regular user)
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Create user exercise (editable by user)
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $user->id,
        ]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Should see edit link for user exercise
        $response->assertSee(route('exercises.edit', $userExercise->id));
        // Should not see edit link for global exercise
        $response->assertDontSee(route('exercises.edit', $globalExercise->id));
    }

    /** @test */
    public function index_view_shows_delete_button_only_for_deletable_exercises()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create exercise without lift logs (deletable)
        $deletableExercise = Exercise::factory()->create([
            'title' => 'Deletable Exercise',
            'user_id' => $user->id,
        ]);

        // Create exercise with lift logs (not deletable)
        $nonDeletableExercise = Exercise::factory()->create([
            'title' => 'Non-Deletable Exercise',
            'user_id' => $user->id,
        ]);
        LiftLog::factory()->create(['exercise_id' => $nonDeletableExercise->id, 'user_id' => $user->id]);

        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        // Should see delete button for deletable exercise
        $response->assertSee('fa-trash');
        // Should see disabled delete button for non-deletable exercise
        $response->assertSee('cursor: not-allowed');
    }

    /** @test */
    public function create_form_renders_global_checkbox_for_admin()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $response = $this->get(route('exercises.create'));

        $response->assertStatus(200);
        $response->assertSee('name="is_global"', false);
        $response->assertSee('Global Exercise (Available to all users)');
    }

    /** @test */
    public function create_form_does_not_render_global_checkbox_for_regular_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('exercises.create'));

        $response->assertStatus(200);
        $response->assertDontSee('name="is_global"', false);
        $response->assertDontSee('Global Exercise (Available to all users)');
    }

    /** @test */
    public function edit_form_renders_global_checkbox_checked_for_global_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        $response = $this->get(route('exercises.edit', $globalExercise));

        $response->assertStatus(200);
        $response->assertSee('name="is_global"', false);
        $response->assertSee('checked', false);
        $response->assertSee('Global Exercise (Available to all users)');
    }

    /** @test */
    public function edit_form_renders_global_checkbox_unchecked_for_user_exercise()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $admin->id,
        ]);

        $response = $this->get(route('exercises.edit', $userExercise));

        $response->assertStatus(200);
        $response->assertSee('name="is_global"', false);
        $response->assertDontSee('checked', false);
        $response->assertSee('Global Exercise (Available to all users)');
    }

    /** @test */
    public function admin_can_submit_create_form_with_global_option()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $exerciseData = [
            'title' => 'New Global Exercise',
            'description' => 'A new global exercise',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Global Exercise',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function admin_can_submit_edit_form_to_make_exercise_global()
    {
        $admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);
        $this->actingAs($admin);

        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $admin->id,
        ]);

        $updatedData = [
            'title' => 'Now Global Exercise',
            'description' => 'Made global',
            'exercise_type' => 'regular',
            'is_global' => true,
        ];

        $response = $this->put(route('exercises.update', $userExercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Now Global Exercise',
            'user_id' => null,
        ]);
    }
}
