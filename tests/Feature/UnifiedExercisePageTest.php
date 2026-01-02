<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Workout;
use Carbon\Carbon;

class UnifiedExercisePageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function both_pages_show_identical_three_tab_interface()
    {
        // Test lift-logs/create page
        $createResponse = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        // Test exercises/{id}/logs page
        $logsResponse = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        // Both should have the same tab structure
        foreach ([$createResponse, $logsResponse] as $response) {
            $response->assertStatus(200);
            $response->assertSee('Help', false);
            $response->assertSee('My Metrics', false);
            $response->assertSee('Log Now', false);
            $response->assertSee('fa-question-circle', false);
            $response->assertSee('fa-chart-line', false);
            $response->assertSee('fa-plus', false);
        }
    }

    /** @test */
    public function lift_logs_create_defaults_to_log_now_tab()
    {
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Check that Log Now tab is active (this would be in the HTML structure)
        $response->assertSee('Log Now', false);
        // The form should be visible (indicating Log Now tab is active)
        $response->assertSee('name="weight"', false);
        $response->assertSee('name="reps"', false);
    }

    /** @test */
    public function exercises_logs_defaults_to_my_metrics_tab()
    {
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        // Check that My Metrics tab content is visible
        $response->assertSee('My Metrics', false);
        // Should show empty state message since no logs exist
        $response->assertSee('No training data yet', false);
    }

    /** @test */
    public function lift_log_controller_validates_exercise_ownership()
    {
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $otherExercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'Exercise not found or not accessible.');
    }

    /** @test */
    public function lift_log_controller_handles_missing_exercise_id()
    {
        $response = $this->get(route('lift-logs.create', [
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'No exercise specified.');
    }

    /** @test */
    public function lift_log_controller_handles_nonexistent_exercise()
    {
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => 99999,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'Exercise not found or not accessible.');
    }

    /** @test */
    public function exercise_controller_passes_correct_parameters_to_service()
    {
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id,
            'from' => 'mobile-entry-lifts',
            'date' => '2026-01-01'
        ]));

        $response->assertStatus(200);
        // Verify the page loads successfully with the parameters
        $response->assertSee($this->exercise->title);
    }

    /** @test */
    public function exercise_controller_handles_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $otherExercise->id
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function back_button_works_with_workouts_redirect()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ]));

        $response->assertStatus(200);
        // Check that the back button URL points to workouts
        $response->assertSee(route('workouts.index', ['workout_id' => $workout->id]), false);
    }

    /** @test */
    public function back_button_works_with_mobile_entry_redirect()
    {
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => $date,
            'redirect_to' => 'mobile-entry-lifts'
        ]));

        $response->assertStatus(200);
        // Check that the back button URL points to mobile-entry.lifts with date
        $response->assertSee('mobile-entry/lifts', false);
        $response->assertSee($date, false);
    }

    /** @test */
    public function back_button_works_from_exercises_logs_context()
    {
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id,
            'from' => 'mobile-entry-lifts',
            'date' => '2026-01-01'
        ]));

        $response->assertStatus(200);
        // Should have back button to mobile-entry.lifts
        $response->assertSee('mobile-entry/lifts', false);
    }

    /** @test */
    public function default_back_behavior_works()
    {
        // Test lift-logs/create without redirect params
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Should default to mobile-entry.lifts
        $response->assertSee('mobile-entry/lifts', false);

        // Test exercises/{id}/logs without from param
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        // Should default to lift-logs.index
        $response->assertSee('lift-logs', false);
    }

    /** @test */
    public function date_subtitle_shows_when_date_provided()
    {
        $date = Carbon::parse('2026-01-01');
        $formattedDate = $date->format('l, F j, Y'); // "Wednesday, January 1, 2026"

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => $date->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee($formattedDate, false);
    }

    /** @test */
    public function missing_date_parameter_defaults_to_today()
    {
        $todayFormatted = Carbon::today()->format('l, F j, Y');

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id
            // No date parameter
        ]));

        $response->assertStatus(200);
        $response->assertSee($todayFormatted, false);
    }

    /** @test */
    public function exercises_logs_shows_date_when_provided()
    {
        $date = Carbon::parse('2026-01-01');
        $formattedDate = $date->format('l, F j, Y');

        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id,
            'date' => $date->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee($formattedDate, false);
    }

    /** @test */
    public function exercises_logs_shows_no_subtitle_when_no_date()
    {
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        // Should not show any date subtitle
        $response->assertDontSee(Carbon::today()->format('l, F j, Y'), false);
        $response->assertDontSee(Carbon::yesterday()->format('l, F j, Y'), false);
    }
}