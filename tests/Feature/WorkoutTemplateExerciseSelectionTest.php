<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkoutTemplateExerciseSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_displays_exercise_selection_list()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Bench Press']);

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Workout Template');
        $response->assertSee('Bench Press'); // Exercise should be in selection list
        $response->assertSee('Add Exercise'); // Button to show selection
    }

    public function test_edit_form_displays_exercise_selection_list()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Squat']);
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $template->exercises()->attach($exercise->id, ['order' => 1]);

        $response = $this->actingAs($user)->get(route('workout-templates.edit', $template));

        $response->assertStatus(200);
        $response->assertSee('Edit Workout Template');
        $response->assertSee('Squat'); // Exercise should be in selection list
        $response->assertSee('Add Exercise'); // Button to show selection
    }

    public function test_exercise_selection_respects_user_visibility_settings()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'My Exercise']);
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        $response->assertSee('My Exercise'); // User's exercise should be visible
        $response->assertDontSee('Global Exercise'); // Global exercise should not be visible
    }

    public function test_exercise_selection_shows_global_exercises_when_enabled()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'My Exercise']);
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        $response->assertSee('My Exercise'); // User's exercise should be visible
        $response->assertSee('Global Exercise'); // Global exercise should be visible
    }

    public function test_exercise_selection_displays_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        $exercise->aliases()->create(['user_id' => $user->id, 'alias_name' => 'BP']);

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        $response->assertSee('BP'); // Alias should be displayed instead of original title
    }

    public function test_exercise_selection_shows_recommended_exercises()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Bench Press']);
        
        // Create lift logs to make it a recommended exercise
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(5)
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        $response->assertSee('Bench Press');
        // The exercise should appear in the selection list
    }

    public function test_exercise_selection_allows_inline_creation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('workout-templates.create'));

        $response->assertStatus(200);
        // Check for the create form elements
        $response->assertSee('create-item-form');
        $response->assertSee('+'); // Create button
    }

    public function test_selected_exercises_display_with_order()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Bench Press']);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Squat']);
        
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id]);
        $template->exercises()->attach($exercise1->id, ['order' => 1]);
        $template->exercises()->attach($exercise2->id, ['order' => 2]);

        $response = $this->actingAs($user)->get(route('workout-templates.edit', $template));

        $response->assertStatus(200);
        $response->assertSee('Bench Press');
        $response->assertSee('Squat');
        // Check for order numbers
        $response->assertSee('exercise-order');
    }
}
