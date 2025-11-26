<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseLogsBackButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        
        // Create some lift logs for the exercise
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now()
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);
    }

    /** @test */
    public function back_button_routes_to_mobile_entry_lifts_when_from_parameter_is_mobile_entry_lifts()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', [
                'exercise' => $this->exercise,
                'from' => 'mobile-entry-lifts'
            ]));

        $response->assertStatus(200);
        
        // Check that the back button URL contains mobile-entry/lifts
        $response->assertSee(route('mobile-entry.lifts'), false);
    }

    /** @test */
    public function back_button_routes_to_lift_logs_index_when_from_parameter_is_lift_logs_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', [
                'exercise' => $this->exercise,
                'from' => 'lift-logs-index'
            ]));

        $response->assertStatus(200);
        
        // Check that the back button URL contains lift-logs
        $response->assertSee(route('lift-logs.index'), false);
    }

    /** @test */
    public function back_button_defaults_to_lift_logs_index_when_no_from_parameter()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', ['exercise' => $this->exercise]));

        $response->assertStatus(200);
        
        // Check that the back button URL contains lift-logs
        $response->assertSee(route('lift-logs.index'), false);
    }

    /** @test */
    public function back_button_preserves_date_when_coming_from_mobile_entry_lifts()
    {
        $date = '2025-11-26';
        
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', [
                'exercise' => $this->exercise,
                'from' => 'mobile-entry-lifts',
                'date' => $date
            ]));

        $response->assertStatus(200);
        
        // Check that the back button URL contains the date parameter
        $expectedUrl = route('mobile-entry.lifts', ['date' => $date]);
        $response->assertSee($expectedUrl, false);
    }

    /** @test */
    public function back_button_routes_to_mobile_entry_lifts_without_date_when_date_not_provided()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', [
                'exercise' => $this->exercise,
                'from' => 'mobile-entry-lifts'
            ]));

        $response->assertStatus(200);
        
        // Check that the back button URL is mobile-entry/lifts without date
        $expectedUrl = route('mobile-entry.lifts');
        $response->assertSee($expectedUrl, false);
    }
}
