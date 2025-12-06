<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\ExerciseAlias;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_their_templates()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee($workout->name);
    }

    /** @test */
    public function user_can_create_template()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
            'wod_syntax' => '[[Bench Press]]: 3x8',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', [
            'user_id' => $user->id,
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
        ]);
    }

    /** @test */
    public function user_can_add_global_exercise_to_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]); // Global exercise

        $response = $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise added!');
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_can_add_their_own_exercise_to_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise added!');
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_cannot_add_duplicate_exercise_to_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        // Add exercise first time
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Try to add again
        $response = $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('warning', 'Exercise already in workout.');
    }

    /** @test */
    public function exercises_are_added_in_priority_order()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);
        $exercise3 = Exercise::factory()->create(['user_id' => null]);

        // Add exercises
        $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise1->id,
        ]));
        $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise2->id,
        ]));
        $this->actingAs($user)->get(route('workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise3->id,
        ]));

        $workout->refresh();
        $exercises = $workout->exercises()->orderBy('order')->get();

        $this->assertEquals(1, $exercises[0]->order);
        $this->assertEquals(2, $exercises[1]->order);
        $this->assertEquals(3, $exercises[2]->order);
    }

    /** @test */
    public function user_can_create_new_exercise_and_add_to_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('workouts.create-exercise', $workout->id), [
            'exercise_name' => 'New Custom Exercise',
        ]);

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise created and added!');
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Custom Exercise',
            'user_id' => $user->id,
        ]);

        $exercise = Exercise::where('title', 'New Custom Exercise')->first();
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function template_edit_view_shows_aliased_exercise_names()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        // Create alias
        ExerciseAlias::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP',
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'name' => 'Test Workout',
            'wod_syntax' => '[[Bench Press]]: 3x8',
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Bench Press',
                                'loggable' => true,
                                'scheme' => '3x8'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout->id));

        $response->assertOk();
        $response->assertSee('BP'); // Should see alias, not original name
    }

    /** @test */
    public function template_index_shows_exercise_names_with_aliases()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Template',
        ]);
        
        $exercise1 = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);
        $exercise2 = Exercise::factory()->create([
            'title' => 'Squat',
            'user_id' => null,
        ]);

        // Create alias for first exercise
        ExerciseAlias::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'BP',
        ]);

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee('2 exercises:');
        $response->assertSee('BP'); // Aliased name
        $response->assertSee('Squat'); // Original name
    }

    /** @test */
    public function template_index_shows_exercise_count_and_names()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Push Day',
        ]);
        
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Dips', 'user_id' => null]);
        $exercise3 = Exercise::factory()->create(['title' => 'Tricep Extensions', 'user_id' => null]);

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise3->id,
            'order' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee('3 exercises:');
        $response->assertSee('Bench Press, Dips, Tricep Extensions');
    }

    /** @test */
    public function user_can_remove_exercise_from_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $workoutExercise = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('workouts.remove-exercise', [
            'workout' => $workout->id,
            'exercise' => $workoutExercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise removed!');
        $this->assertDatabaseMissing('workout_exercises', [
            'id' => $workoutExercise->id,
        ]);
    }

    /** @test */
    public function user_can_delete_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout->id));

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'Workout deleted!');
        $this->assertDatabaseMissing('workouts', [
            'id' => $workout->id,
        ]);
    }

    /** @test */
    public function user_cannot_edit_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->get(route('workouts.edit', $workout->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_delete_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->delete(route('workouts.destroy', $workout->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_move_exercise_up_in_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);

        $workoutEx1 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        $workoutEx2 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.move-exercise', [
            'workout' => $workout->id,
            'exercise' => $workoutEx2->id,
            'direction' => 'up',
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise order updated!');

        $workoutEx1->refresh();
        $workoutEx2->refresh();

        $this->assertEquals(2, $workoutEx1->order);
        $this->assertEquals(1, $workoutEx2->order);
    }

    /** @test */
    public function user_can_move_exercise_down_in_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);

        $workoutEx1 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        $workoutEx2 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.move-exercise', [
            'workout' => $workout->id,
            'exercise' => $workoutEx1->id,
            'direction' => 'down',
        ]));

        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success', 'Exercise order updated!');

        $workoutEx1->refresh();
        $workoutEx2->refresh();

        $this->assertEquals(2, $workoutEx1->order);
        $this->assertEquals(1, $workoutEx2->order);
    }

    /** @test */
    public function single_workout_is_expanded_by_default()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Only Workout']);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        
        // Check that the workout row has expanded class
        $response->assertSee('is-collapsible expanded', false);
    }

    /** @test */
    public function multiple_workouts_are_collapsed_by_default()
    {
        $user = User::factory()->create();
        $workout1 = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Workout 1']);
        $workout2 = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Workout 2']);
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        WorkoutExercise::create([
            'workout_id' => $workout1->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout2->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        
        // Both workouts should be collapsible but not expanded
        $response->assertSee('is-collapsible', false);
        $response->assertDontSee('is-collapsible expanded', false);
    }

    /** @test */
    public function workout_with_logged_exercise_today_is_expanded()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Active Workout']);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Create a lift log for today
        $liftLog = \App\Models\LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        
        // Check that the workout row has expanded class
        $response->assertSee('is-collapsible expanded', false);
    }

    /** @test */
    public function workout_is_expanded_when_workout_id_in_query_string()
    {
        $user = User::factory()->create();
        $workout1 = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Workout 1']);
        $workout2 = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Workout 2']);
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        WorkoutExercise::create([
            'workout_id' => $workout1->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout2->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Request with workout_id query parameter (e.g., after deleting a lift log)
        $response = $this->actingAs($user)->get(route('workouts.index', ['workout_id' => $workout2->id]));

        $response->assertOk();
        
        // The specified workout should be expanded
        $response->assertSee('is-collapsible expanded', false);
    }

    /** @test */
    public function workout_without_logged_exercises_shows_log_now_buttons()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee('Log now');
        $response->assertSee('btn-log-now');
    }

    /** @test */
    public function workout_with_logged_exercises_shows_edit_and_delete_buttons()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Create a lift log for today
        $liftLog = \App\Models\LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee('Edit lift log');
        $response->assertSee('Delete lift log');
    }
}
