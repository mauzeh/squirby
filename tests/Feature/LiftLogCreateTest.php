<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use Carbon\Carbon;

class LiftLogCreateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function user_can_view_lift_log_create_page()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);
        
        // Refresh to ensure we have the latest data
        $exercise->refresh();

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee('Log ' . $exercise->title);
    }

    /** @test */
    public function create_page_shows_back_button_to_mobile_entry_by_default()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => $date
        ]));

        $response->assertStatus(200);
        // Check that the back button URL is present
        $response->assertSee(route('mobile-entry.lifts', ['date' => $date]));
    }

    /** @test */
    public function create_page_shows_back_button_to_workouts_when_redirect_param_is_workouts()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $workout = \App\Models\Workout::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ]));

        $response->assertStatus(200);
        // Check that the back button URL points to workouts
        $response->assertSee(route('workouts.index', ['workout_id' => $workout->id]));
    }

    /** @test */
    public function create_page_displays_notes_from_previous_workout()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Create a previous lift log with comments
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Felt strong today',
            'logged_at' => Carbon::yesterday()
        ]);
        $previousLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
        ]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee('Felt strong today');
    }

    /** @test */
    public function create_page_displays_suggested_weight_and_reps()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday()
        ]);
        $previousLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
        ]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Should show last workout info
        $response->assertSee('100 lbs');
        $response->assertSee('5 reps');
    }

    /** @test */
    public function create_page_shows_instructional_message_for_first_time_exercise()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Should show "How to log" message for first-time exercises
        $response->assertSee('How to log:');
    }

    /** @test */
    public function create_page_redirects_with_error_if_no_exercise_id_provided()
    {
        $response = $this->get(route('lift-logs.create', [
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'No exercise specified.');
    }

    /** @test */
    public function create_page_redirects_with_error_if_exercise_not_found()
    {
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => 99999, // Non-existent exercise
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'Exercise not found or not accessible.');
    }

    /** @test */
    public function create_page_redirects_with_error_if_exercise_belongs_to_another_user()
    {
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts'));
        $response->assertSessionHas('error', 'Exercise not found or not accessible.');
    }

    /** @test */
    public function create_page_uses_today_as_default_date_if_not_provided()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id
        ]));

        $response->assertStatus(200);
        $response->assertSee(Carbon::today()->format('l, F j, Y'));
    }

    /** @test */
    public function create_page_displays_custom_date_when_provided()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $customDate = Carbon::yesterday();

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => $customDate->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee($customDate->format('l, F j, Y'));
    }

    /** @test */
    public function create_page_form_includes_redirect_parameters()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $workout = \App\Models\Workout::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ]));

        $response->assertStatus(200);
        // Check that redirect parameters are in the form
        $response->assertSee('redirect_to', false);
        $response->assertSee('workouts', false);
        $response->assertSee('workout_id', false);
    }

    /** @test */
    public function create_page_does_not_show_delete_button()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Standalone forms should not have a delete button
        $response->assertDontSee('fa-trash');
    }

    /** @test */
    public function user_can_submit_lift_log_from_create_page()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 135,
            'reps' => 8,
            'rounds' => 3,
            'comments' => 'Great workout!',
            'date' => $date->toDateString(),
            'logged_at' => '14:30',
            'redirect_to' => 'mobile-entry-lifts'
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Great workout!',
        ]);

        $this->assertDatabaseCount('lift_sets', 3);
        $this->assertDatabaseHas('lift_sets', [
            'weight' => 135,
            'reps' => 8,
        ]);
    }

    /** @test */
    public function submitting_lift_log_redirects_to_mobile_entry_by_default()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 135,
            'reps' => 8,
            'rounds' => 3,
            'date' => $date->toDateString(),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $response->assertRedirect(route('exercises.show-logs', ['exercise' => $exercise->id]));
    }

    /** @test */
    public function submitting_lift_log_redirects_to_workouts_when_redirect_param_is_workouts()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $workout = \App\Models\Workout::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 135,
            'reps' => 8,
            'rounds' => 3,
            'date' => $date->toDateString(),
            'logged_at' => '14:30',
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $response->assertRedirect(route('workouts.index', ['workout_id' => $workout->id]));
    }

    /** @test */
    public function create_page_displays_exercise_alias_if_exists()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Back Squat'
        ]);
        
        // Create an alias for this exercise
        \App\Models\ExerciseAlias::create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'My Squat'
        ]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee('Log My Squat');
        $response->assertDontSee('Log Back Squat');
    }

    /** @test */
    public function create_page_works_for_bodyweight_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee('Log ' . $exercise->title);
    }

    /** @test */
    public function create_page_works_for_banded_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded'
        ]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        $response->assertSee('Log ' . $exercise->title);
    }
}
