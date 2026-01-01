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
    public function create_page_returns_500_if_exercise_not_found()
    {
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => 99999, // Non-existent exercise
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(500);
    }

    /** @test */
    public function create_page_returns_500_if_exercise_belongs_to_another_user()
    {
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(500);
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

    /** @test */
    public function submitting_lift_log_creates_multiple_sets_based_on_rounds()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 4, // Should create 4 sets
            'date' => $date->toDateString(),
            'logged_at' => '14:30',
        ];

        $this->post(route('lift-logs.store'), $liftLogData);

        // Should have created exactly 4 sets
        $this->assertDatabaseCount('lift_sets', 4);
        
        // All sets should have the same weight and reps
        $sets = \App\Models\LiftSet::all();
        foreach ($sets as $set) {
            $this->assertEquals(100, $set->weight);
            $this->assertEquals(5, $set->reps);
        }
    }

    /** @test */
    public function submitting_lift_log_validates_required_fields()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            // Missing required fields: reps, rounds (date is now optional and defaults to today)
        ]);

        $response->assertSessionHasErrors(['reps', 'rounds']);
    }

    /** @test */
    public function submitting_lift_log_validates_minimum_values()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 0, // Invalid: must be at least 1
            'rounds' => 0, // Invalid: must be at least 1
            'date' => Carbon::today()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['reps', 'rounds']);
    }

    /** @test */
    public function submitting_lift_log_rounds_time_to_nearest_15_minutes()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 1,
            'date' => $date->toDateString(),
            'logged_at' => '14:37', // Should round to 14:45
        ];

        $this->post(route('lift-logs.store'), $liftLogData);

        $liftLog = LiftLog::first();
        $this->assertEquals('14:45', $liftLog->logged_at->format('H:i'));
    }

    /** @test */
    public function submitting_lift_log_uses_current_time_when_not_provided()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 1,
            'date' => $date->toDateString(),
            // No logged_at provided
        ];

        $this->post(route('lift-logs.store'), $liftLogData);

        $liftLog = LiftLog::first();
        
        // Should have a logged_at time on today's date
        $this->assertEquals($date->toDateString(), $liftLog->logged_at->toDateString());
        $this->assertNotNull($liftLog->logged_at);
    }

    /** @test */
    public function submitting_banded_exercise_stores_band_color()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded'
        ]);
        $date = Carbon::today();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'band_color' => 'blue',
            'reps' => 10,
            'rounds' => 3,
            'date' => $date->toDateString(),
            'logged_at' => '14:30',
        ];

        $this->post(route('lift-logs.store'), $liftLogData);

        $this->assertDatabaseHas('lift_sets', [
            'band_color' => 'blue',
            'reps' => 10,
        ]);
    }

    /** @test */
    public function submitting_lift_log_displays_success_message()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press'
        ]);
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

        // Should have a success message mentioning the exercise
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('Bench Press', $successMessage);
    }

    /** @test */
    public function create_page_shows_progression_suggestion_when_available()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Create multiple previous logs to establish a pattern
        for ($i = 3; $i > 0; $i--) {
            $previousLog = LiftLog::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => Carbon::today()->subDays($i * 7)
            ]);
            $previousLog->liftSets()->create([
                'weight' => 100,
                'reps' => 5,
            ]);
        }

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        // Should show some kind of suggestion (the exact text depends on TrainingProgressionService)
        $response->assertSee('Try this');
    }

    /** @test */
    public function submitting_lift_log_for_past_date_uses_safe_default_time()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $pastDate = Carbon::yesterday();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 1,
            'date' => $pastDate->toDateString(),
            // No logged_at provided
        ];

        $this->post(route('lift-logs.store'), $liftLogData);

        $liftLog = LiftLog::first();
        
        // Should use 12:00 PM as safe default for past dates
        $this->assertEquals($pastDate->toDateString(), $liftLog->logged_at->toDateString());
        $this->assertEquals('12:00', $liftLog->logged_at->format('H:i'));
    }
}
