<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\LiftLog;
use Carbon\Carbon;

class LiftLogWorkflowIntegrationTest extends TestCase
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
    public function complete_workflow_from_workout_to_lift_log_and_back()
    {
        // Setup: Create a workout with an exercise
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1
        ]);

        // Step 1: View workouts page
        $response = $this->get(route('workouts.index'));
        $response->assertOk();
        // Simple workouts show generated label, not stored name
        $response->assertSee($exercise->title);

        // Step 2: Click "Log now" button (simulated by following the link)
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ]));

        $response->assertOk();
        $response->assertSee('Log ' . $exercise->title);
        $response->assertSee(route('workouts.index', ['workout_id' => $workout->id])); // Back button

        // Step 3: Submit the lift log
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 135,
            'reps' => 8,
            'rounds' => 3,
            'comments' => 'Felt strong!',
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '14:30',
            'redirect_to' => 'workouts',
            'workout_id' => $workout->id
        ]);

        // Should create the lift log
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Felt strong!'
        ]);

        // Should redirect back to workouts page
        $response->assertRedirect(route('workouts.index', ['workout_id' => $workout->id]));

        // Step 4: Verify lift log was created (already checked in database assertion above)
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->first();
        $this->assertNotNull($liftLog);
        $this->assertEquals('Felt strong!', $liftLog->comments);
    }

    /** @test */
    public function complete_workflow_from_mobile_entry_to_lift_log_and_back()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Step 1: View mobile-entry page
        $response = $this->get(route('mobile-entry.lifts', ['date' => $date]));
        $response->assertOk();

        // Step 2: Click on an exercise from the list (simulated)
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => $date,
            'redirect_to' => 'mobile-entry-lifts'
        ]));

        $response->assertOk();
        $response->assertSee('Log ' . $exercise->title);
        $response->assertSee(route('mobile-entry.lifts', ['date' => $date])); // Back button

        // Step 3: Submit the lift log
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 10,
            'rounds' => 3,
            'date' => $date,
            'logged_at' => '10:00',
            'redirect_to' => 'mobile-entry-lifts'
        ]);

        // Should create the lift log
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id
        ]);

        // Should redirect back to mobile-entry (with submitted_lift_log_id)
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $response->assertRedirect(route('mobile-entry.lifts', [
            'date' => $date,
            'submitted_lift_log_id' => $liftLog->id
        ]));

        // Step 4: Verify we're back at mobile-entry with the logged exercise
        $response = $this->get(route('mobile-entry.lifts', ['date' => $date]));
        $response->assertOk();
        $response->assertSee($exercise->title);
        $response->assertSee('100 lbs');
    }

    /** @test */
    public function user_can_click_back_button_without_submitting()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Go to lift-logs/create
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => $date,
            'redirect_to' => 'mobile-entry-lifts'
        ]));

        $response->assertOk();

        // Click back button (simulated by going back to mobile-entry)
        $response = $this->get(route('mobile-entry.lifts', ['date' => $date]));
        $response->assertOk();

        // Should not have created any lift log
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function workout_shows_edit_button_after_exercise_is_logged()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1
        ]);

        // Log the exercise
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()
        ]);

        // View workout edit page (where exercise actions are shown)
        $response = $this->get(route('workouts.edit-simple', $workout->id));
        $response->assertOk();

        // Should show that exercise was logged (no play button, shows completed message)
        $response->assertDontSee('fa-play');
        $response->assertSee('Completed:');
    }

    /** @test */
    public function mobile_entry_shows_logged_exercises_in_table()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today();

        // Log an exercise
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date,
            'comments' => 'Great workout!'
        ]);

        $liftLog->liftSets()->create([
            'weight' => 150,
            'reps' => 5
        ]);

        // View mobile-entry page
        $response = $this->get(route('mobile-entry.lifts', ['date' => $date->toDateString()]));
        $response->assertOk();

        // Should show the logged exercise in the table
        $response->assertSee($exercise->title);
        $response->assertSee('150 lbs');
        $response->assertSee('Great workout!');
    }
}
