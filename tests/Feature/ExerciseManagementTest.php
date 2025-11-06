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
            'is_bodyweight' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'is_bodyweight' => true,
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_not_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'is_bodyweight' => false,
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => false,
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
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id]);
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
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
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
        $this->assertDatabaseMissing('exercises', ['id' => $globalExercise->id]);
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
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id]);
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

    /** @test */
    public function cannot_delete_selected_exercises_with_lift_logs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise1 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Add lift log to first exercise
        LiftLog::factory()->create(['exercise_id' => $exercise1->id, 'user_id' => $user->id]);

        $response = $this->post(route('exercises.destroy-selected'), [
            'exercise_ids' => [$exercise1->id, $exercise2->id]
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('exercises', ['id' => $exercise1->id]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise2->id]);
    }

    /** @test */
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
            'is_bodyweight' => false,
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
            'is_bodyweight' => false,
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

    /** @test */
    public function authenticated_user_can_create_exercise_with_exercise_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Running',
            'description' => 'A cardio exercise for endurance',
            'exercise_type' => 'cardio',
        ];

        $response = $this->from(route('exercises.create'))
                         ->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
            'exercise_type' => 'cardio',
        ]);
    }

    /** @test */
    public function authenticated_user_can_create_exercise_with_different_exercise_types()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseTypes = [
            ['type' => 'regular', 'title' => 'Bench Press'],
            ['type' => 'cardio', 'title' => 'Running'],
            ['type' => 'bodyweight', 'title' => 'Push-ups'],
            ['type' => 'banded', 'title' => 'Band Pull-apart'],
        ];

        foreach ($exerciseTypes as $exerciseType) {
            $exerciseData = [
                'title' => $exerciseType['title'],
                'description' => 'Test exercise',
                'exercise_type' => $exerciseType['type'],
            ];

            $response = $this->from(route('exercises.create'))
                             ->post(route('exercises.store'), $exerciseData);

            $response->assertRedirect(route('exercises.index'));
            $this->assertDatabaseHas('exercises', [
                'user_id' => $user->id,
                'title' => $exerciseType['title'],
                'exercise_type' => $exerciseType['type'],
            ]);
        }
    }

    /** @test */
    public function authenticated_user_cannot_create_exercise_with_invalid_exercise_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Invalid Exercise',
            'description' => 'Test exercise with invalid type',
            'exercise_type' => 'invalid_type',
        ];

        $response = $this->from(route('exercises.create'))
                         ->post(route('exercises.store'), $exerciseData);

        $response->assertSessionHasErrors('exercise_type');
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Invalid Exercise',
            'exercise_type' => 'invalid_type',
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_exercise_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'user_id' => $user->id,
            'exercise_type' => 'regular',
        ]);

        $updatedData = [
            'title' => 'Test Exercise',
            'description' => 'Updated to cardio',
            'exercise_type' => 'cardio',
        ];

        $response = $this->from(route('exercises.edit', $exercise))
                         ->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'cardio',
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_update_exercise_with_invalid_exercise_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'user_id' => $user->id,
            'exercise_type' => 'regular',
        ]);

        $updatedData = [
            'title' => 'Test Exercise',
            'description' => 'Trying invalid type',
            'exercise_type' => 'invalid_type',
        ];

        $response = $this->from(route('exercises.edit', $exercise))
                         ->put(route('exercises.update', $exercise), $updatedData);

        $response->assertSessionHasErrors('exercise_type');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'regular', // Should remain unchanged
        ]);
    }

    /** @test */
    public function exercise_create_form_displays_exercise_type_field()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('exercises.create'));

        $response->assertStatus(200);
        $response->assertSee('Exercise Type');
        $response->assertSee('name="exercise_type"', false);
        $response->assertSee('<option value="regular">Regular</option>', false);
        $response->assertSee('<option value="cardio">Cardio</option>', false);
        $response->assertSee('<option value="bodyweight">Bodyweight</option>', false);
        $response->assertSee('<option value="banded">Banded</option>', false);
    }

    /** @test */
    public function exercise_edit_form_displays_exercise_type_field_with_current_value()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'user_id' => $user->id,
            'exercise_type' => 'cardio',
        ]);

        $response = $this->get(route('exercises.edit', $exercise));

        $response->assertStatus(200);
        $response->assertSee('Exercise Type');
        $response->assertSee('name="exercise_type"', false);
        $response->assertSee('<option value="cardio" selected>Cardio</option>', false);
    }

    /** @test */
    public function exercise_type_field_is_optional_and_defaults_appropriately()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Exercise Without Type',
            'description' => 'Test exercise without specifying type',
            // exercise_type not provided
        ];

        $response = $this->from(route('exercises.create'))
                         ->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        
        // Should be created successfully and have a default exercise_type
        $exercise = Exercise::where('title', 'Exercise Without Type')->first();
        $this->assertNotNull($exercise);
        $this->assertNotNull($exercise->exercise_type);
    }
}
