<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class PrefillSuggestedValuesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'prefill_suggested_values' => true,
        ]);
        
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Bench Press',
        ]);
    }

    /** @test */
    public function user_preference_defaults_to_true()
    {
        $newUser = User::factory()->create();
        
        $this->assertTrue($newUser->shouldPrefillSuggestedValues());
    }

    /** @test */
    public function user_can_update_prefill_suggested_values_preference()
    {
        $this->actingAs($this->user);
        
        $response = $this->patch(route('profile.update-preferences'), [
            'prefill_suggested_values' => false,
            'show_global_exercises' => true,
            'show_extra_weight' => false,
        ]);
        
        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'preferences-updated');
        
        $this->user->refresh();
        $this->assertFalse($this->user->shouldPrefillSuggestedValues());
    }

    /** @test */
    public function profile_page_shows_prefill_suggested_values_checkbox()
    {
        $this->actingAs($this->user);
        
        $response = $this->get(route('profile.edit'));
        
        $response->assertStatus(200);
        $response->assertSee('Prefill suggested progression values');
        $response->assertSee('prefill_suggested_values');
    }

    /** @test */
    public function lift_log_create_form_prefills_suggested_values_when_preference_enabled()
    {
        $this->user->update(['prefill_suggested_values' => true]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        // The form should show suggested weight (progression from 100)
        // This will be 105 based on the default progression logic
        $response->assertSee('value="105"', false);
    }

    /** @test */
    public function lift_log_create_form_prefills_last_workout_values_when_preference_disabled()
    {
        $this->user->update(['prefill_suggested_values' => false]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        // The form should show last workout weight (100, not 105)
        $response->assertSee('value="100"', false);
    }

    /** @test */
    public function try_this_message_shown_when_preference_enabled()
    {
        $this->user->update(['prefill_suggested_values' => true]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        // Should show the "Try this" suggestion message
        $response->assertSee('Try this');
    }

    /** @test */
    public function try_this_message_not_shown_when_preference_disabled()
    {
        $this->user->update(['prefill_suggested_values' => false]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        // Should NOT show the "Try this" suggestion message
        $response->assertDontSee('Try this');
    }

    /** @test */
    public function last_workout_message_always_shown_regardless_of_preference()
    {
        // Test with preference enabled
        $this->user->update(['prefill_suggested_values' => true]);
        
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertSee('Last workout');
        
        // Test with preference disabled
        $this->user->update(['prefill_suggested_values' => false]);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertSee('Last workout');
    }

    /** @test */
    public function bodyweight_exercise_respects_preference()
    {
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight',
            'title' => 'Pull-ups',
        ]);
        
        // Create previous log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bodyweightExercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 0,
            'reps' => 8,
        ]);
        
        $this->actingAs($this->user);
        
        // With preference enabled - should show suggestion
        $this->user->update(['prefill_suggested_values' => true]);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $bodyweightExercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertSee('Try this');
        
        // With preference disabled - should not show suggestion
        $this->user->update(['prefill_suggested_values' => false]);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $bodyweightExercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertDontSee('Try this');
    }

    /** @test */
    public function banded_exercise_respects_preference()
    {
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded',
            'title' => 'Banded Squats',
        ]);
        
        // Create previous log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => Carbon::now()->subDays(3),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'red',
        ]);
        
        $this->actingAs($this->user);
        
        // With preference enabled - should show suggestion
        $this->user->update(['prefill_suggested_values' => true]);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $bandedExercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertSee('Try this');
        
        // With preference disabled - should not show suggestion
        $this->user->update(['prefill_suggested_values' => false]);
        
        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $bandedExercise->id,
            'date' => Carbon::today()->toDateString(),
        ]));
        
        $response->assertStatus(200);
        $response->assertDontSee('Try this');
    }
}
