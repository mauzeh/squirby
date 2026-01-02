<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsFirstLoggingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function default_flow_links_directly_to_lift_log_creation()
    {
        $this->actingAs($this->user);
        
        // User has default setting (metrics_first_logging_flow = false)
        $this->assertFalse($this->user->shouldUseMetricsFirstLoggingFlow());
        
        // Visit mobile-entry/lifts
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that exercise links go directly to lift-logs.create
        $response->assertSee('lift-logs/create');
        $response->assertSee('exercise_id=' . $this->exercise->id);
        $response->assertSee('redirect_to=mobile-entry-lifts');
    }

    /** @test */
    public function metrics_first_flow_links_to_exercise_logs_page()
    {
        $this->actingAs($this->user);
        
        // Enable metrics-first flow
        $this->user->update(['metrics_first_logging_flow' => true]);
        $this->assertTrue($this->user->shouldUseMetricsFirstLoggingFlow());
        
        // Create a lift log from a previous day to establish history
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        
        // Visit mobile-entry/lifts
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that exercise with history links go to exercises.show-logs first
        $response->assertSee('exercises/' . $this->exercise->id . '/logs');
        $response->assertSee('from=mobile-entry-lifts');
    }

    /** @test */
    public function metrics_first_flow_automatically_expands_exercise_selection_when_no_logs_today()
    {
        $this->actingAs($this->user);
        
        // Enable metrics-first flow
        $this->user->update(['metrics_first_logging_flow' => true]);
        
        // Visit mobile-entry/lifts (no logs yet today)
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that the selection list is expanded (Log Now button should not be present)
        $response->assertDontSee('btn-add-item');
    }

    /** @test */
    public function metrics_first_flow_does_not_expand_when_logs_exist_today()
    {
        $this->actingAs($this->user);
        
        // Enable metrics-first flow
        $this->user->update(['metrics_first_logging_flow' => true]);
        
        // Create a lift log for today
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now(),
        ]);
        
        // Visit mobile-entry/lifts
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that the selection list is NOT expanded (Log Now button should be present)
        $response->assertSee('btn-add-item');
    }

    /** @test */
    public function metrics_first_flow_skips_metrics_page_for_exercises_with_no_history()
    {
        $this->actingAs($this->user);
        
        // Enable metrics-first flow
        $this->user->update(['metrics_first_logging_flow' => true]);
        
        // Create an exercise with no logs
        $newExercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Visit mobile-entry/lifts
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that the new exercise links directly to lift-logs.create (not metrics page)
        $response->assertSee('lift-logs/create');
        $response->assertSee('exercise_id=' . $newExercise->id);
    }

    /** @test */
    public function metrics_first_flow_shows_metrics_page_for_exercises_with_history()
    {
        $this->actingAs($this->user);
        
        // Enable metrics-first flow
        $this->user->update(['metrics_first_logging_flow' => true]);
        
        // Create a lift log from a previous day (to establish history)
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        
        // Visit mobile-entry/lifts
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Check that the exercise with history links to metrics page first
        $response->assertSee('exercises/' . $this->exercise->id . '/logs');
        $response->assertSee('from=mobile-entry-lifts');
    }

    /** @test */
    public function exercise_logs_page_shows_correct_back_button_for_metrics_first_flow()
    {
        $this->actingAs($this->user);
        
        $date = now()->toDateString();
        
        // Visit exercise logs page with mobile-entry-lifts context
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id,
            'from' => 'mobile-entry-lifts',
            'date' => $date
        ]));
        
        $response->assertStatus(200);
        
        // Check that back button goes to mobile-entry/lifts
        $expectedBackUrl = route('mobile-entry.lifts', ['date' => $date]);
        $response->assertSee($expectedBackUrl, false);
    }

    /** @test */
    public function user_can_update_metrics_first_preference()
    {
        $this->actingAs($this->user);
        
        // Initially false
        $this->assertFalse($this->user->shouldUseMetricsFirstLoggingFlow());
        
        // Update preference
        $response = $this->patch(route('profile.update-preferences'), [
            'metrics_first_logging_flow' => true,
            'show_global_exercises' => true,
            'show_extra_weight' => false,
            'prefill_suggested_values' => true,
        ]);
        
        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        
        // Verify preference was updated
        $this->user->refresh();
        $this->assertTrue($this->user->shouldUseMetricsFirstLoggingFlow());
    }

    /** @test */
    public function profile_page_shows_metrics_first_preference_checkbox()
    {
        $this->actingAs($this->user);
        
        $response = $this->get(route('profile.edit'));
        
        $response->assertStatus(200);
        $response->assertSee('metrics_first_logging_flow');
        $response->assertSee('View metrics before logging');
    }
}
