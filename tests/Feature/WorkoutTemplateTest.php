<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Models\ExerciseAlias;

class WorkoutTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_their_templates()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertOk();
        $response->assertSee($template->name);
    }

    /** @test */
    public function user_can_create_template()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workout-templates.store'), [
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workout_templates', [
            'user_id' => $user->id,
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
        ]);
    }

    /** @test */
    public function user_can_add_global_exercise_to_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]); // Global exercise

        $response = $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise added!');
        $this->assertDatabaseHas('workout_template_exercises', [
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_can_add_their_own_exercise_to_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise added!');
        $this->assertDatabaseHas('workout_template_exercises', [
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_cannot_add_duplicate_exercise_to_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        // Add exercise first time
        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Try to add again
        $response = $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('warning', 'Exercise already in template.');
    }

    /** @test */
    public function exercises_are_added_in_priority_order()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);
        $exercise3 = Exercise::factory()->create(['user_id' => null]);

        // Add exercises
        $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise1->id,
        ]));
        $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise2->id,
        ]));
        $this->actingAs($user)->get(route('workout-templates.add-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $exercise3->id,
        ]));

        $template->refresh();
        $exercises = $template->exercises()->orderBy('order')->get();

        $this->assertEquals(1, $exercises[0]->order);
        $this->assertEquals(2, $exercises[1]->order);
        $this->assertEquals(3, $exercises[2]->order);
    }

    /** @test */
    public function user_can_create_new_exercise_and_add_to_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('workout-templates.create-exercise', $template->id), [
            'exercise_name' => 'New Custom Exercise',
        ]);

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise created and added!');
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Custom Exercise',
            'user_id' => $user->id,
        ]);

        $exercise = Exercise::where('title', 'New Custom Exercise')->first();
        $this->assertDatabaseHas('workout_template_exercises', [
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function template_edit_view_shows_aliased_exercise_names()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
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

        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.edit', $template->id));

        $response->assertOk();
        $response->assertSee('BP'); // Should see alias, not original name
    }

    /** @test */
    public function template_index_shows_exercise_names_with_aliases()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create([
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

        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertOk();
        $response->assertSee('2 exercises:');
        $response->assertSee('BP'); // Aliased name
        $response->assertSee('Squat'); // Original name
    }

    /** @test */
    public function template_index_shows_exercise_count_and_names()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create([
            'user_id' => $user->id,
            'name' => 'Push Day',
        ]);
        
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Dips', 'user_id' => null]);
        $exercise3 = Exercise::factory()->create(['title' => 'Tricep Extensions', 'user_id' => null]);

        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);
        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise3->id,
            'order' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertOk();
        $response->assertSee('3 exercises:');
        $response->assertSee('Bench Press, Dips, Tricep Extensions');
    }

    /** @test */
    public function user_can_remove_exercise_from_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $templateExercise = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('workout-templates.remove-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $templateExercise->id,
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise removed!');
        $this->assertDatabaseMissing('workout_template_exercises', [
            'id' => $templateExercise->id,
        ]);
    }

    /** @test */
    public function user_can_delete_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('workout-templates.destroy', $template->id));

        $response->assertRedirect(route('workout-templates.index'));
        $response->assertSessionHas('success', 'Template deleted!');
        $this->assertDatabaseMissing('workout_templates', [
            'id' => $template->id,
        ]);
    }

    /** @test */
    public function user_cannot_edit_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->get(route('workout-templates.edit', $template->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_delete_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->delete(route('workout-templates.destroy', $template->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_move_exercise_up_in_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);

        $templateEx1 = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        $templateEx2 = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.move-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $templateEx2->id,
            'direction' => 'up',
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise order updated!');

        $templateEx1->refresh();
        $templateEx2->refresh();

        $this->assertEquals(2, $templateEx1->order);
        $this->assertEquals(1, $templateEx2->order);
    }

    /** @test */
    public function user_can_move_exercise_down_in_template()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);

        $templateEx1 = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        $templateEx2 = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.move-exercise', [
            'workoutTemplate' => $template->id,
            'exercise' => $templateEx1->id,
            'direction' => 'down',
        ]));

        $response->assertRedirect(route('workout-templates.edit', $template->id));
        $response->assertSessionHas('success', 'Exercise order updated!');

        $templateEx1->refresh();
        $templateEx2->refresh();

        $this->assertEquals(2, $templateEx1->order);
        $this->assertEquals(1, $templateEx2->order);
    }
}
