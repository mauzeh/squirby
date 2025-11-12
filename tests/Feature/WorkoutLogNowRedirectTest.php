<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Carbon\Carbon;

class WorkoutLogNowRedirectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function log_now_workflow_persists_redirect_params_through_multiple_page_loads()
    {
        // Setup
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Push Day']);
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
            'exercise_type' => 'weighted',
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $today = Carbon::today()->toDateString();

        // Step 1: Click "Log now" from workouts index
        // This should redirect to add-lift-form with redirect params
        $response = $this->actingAs($user)->get(route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $today,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]));

        // Should redirect to mobile-entry.lifts with params preserved
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('mobile-entry/lifts', $redirectUrl);
        $this->assertStringContainsString('redirect_to=workouts', $redirectUrl);
        $this->assertStringContainsString('workout_id=' . $workout->id, $redirectUrl);

        // Step 2: Follow redirect to mobile-entry.lifts
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', [
            'date' => $today,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]));

        $response->assertOk();
        // Verify the form contains hidden fields with redirect params
        $response->assertSee('name="redirect_to"', false);
        $response->assertSee('value="workouts"', false);
        $response->assertSee('name="workout_id"', false);
        $response->assertSee('value="' . $workout->id . '"', false);

        // Step 3: Submit the lift log form
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $today,
            'weight' => 135,
            'reps' => 10,
            'rounds' => 3,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]);

        // Should redirect back to workouts.index with id parameter
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('workouts', $redirectUrl);
        $this->assertStringContainsString('id=' . $workout->id, $redirectUrl);
        $response->assertSessionHas('success');
    }

    /** @test */
    public function log_now_redirects_to_correct_workout_with_id_parameter()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create([
            'title' => 'Squat',
            'user_id' => null,
            'exercise_type' => 'weighted',
        ]);

        $today = Carbon::today()->toDateString();

        // Submit lift log with workout redirect params
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $today,
            'weight' => 225,
            'reps' => 5,
            'rounds' => 5,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]);

        // Verify redirect includes id parameter (not workout_id)
        $response->assertRedirect(route('workouts.index', ['id' => $workout->id]));
    }

    /** @test */
    public function workout_id_parameter_is_mapped_to_id_in_redirect()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create([
            'title' => 'Deadlift',
            'user_id' => null,
            'exercise_type' => 'weighted',
        ]);

        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $today,
            'weight' => 315,
            'reps' => 3,
            'rounds' => 3,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]);

        $redirectUrl = $response->headers->get('Location');
        
        // Should have 'id' parameter, not 'workout_id'
        $this->assertStringContainsString('id=' . $workout->id, $redirectUrl);
        $this->assertStringNotContainsString('workout_id=', $redirectUrl);
    }

    /** @test */
    public function add_lift_form_preserves_redirect_params_in_url()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create([
            'title' => 'Overhead Press',
            'user_id' => null,
            'exercise_type' => 'weighted',
        ]);

        $today = Carbon::today()->toDateString();

        // Simulate clicking "Log now" button
        $response = $this->actingAs($user)->get(route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $today,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]));

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        
        // Verify all params are preserved in the redirect
        $this->assertStringContainsString('date=' . $today, $redirectUrl);
        $this->assertStringContainsString('redirect_to=workouts', $redirectUrl);
        $this->assertStringContainsString('workout_id=' . $workout->id, $redirectUrl);
    }

    /** @test */
    public function mobile_entry_lifts_embeds_redirect_params_as_hidden_fields()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'user_id' => null,
            'exercise_type' => 'bodyweight',
        ]);

        $today = Carbon::today()->toDateString();

        // First add the exercise form
        $this->actingAs($user)->get(route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $today,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]));

        // Now view the mobile entry page with redirect params
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', [
            'date' => $today,
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id,
        ]));

        $response->assertOk();
        
        // Verify hidden fields are present in the form
        $content = $response->getContent();
        $this->assertStringContainsString('name="redirect_to"', $content);
        $this->assertStringContainsString('value="workouts"', $content);
        $this->assertStringContainsString('name="workout_id"', $content);
        $this->assertStringContainsString('value="' . $workout->id . '"', $content);
    }

    /** @test */
    public function default_redirect_when_no_workout_id_provided()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
            'exercise_type' => 'weighted',
        ]);

        $today = Carbon::today()->toDateString();

        // Submit without workout_id
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $today,
            'weight' => 135,
            'reps' => 10,
            'rounds' => 3,
        ]);

        // Should redirect to default (exercises.show-logs)
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('exercises', $redirectUrl);
        $this->assertStringContainsString('logs', $redirectUrl);
    }
}
