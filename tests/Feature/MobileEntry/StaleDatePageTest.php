<?php

namespace Tests\Feature\MobileEntry;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleDatePageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press'
        ]);
    }

    /** @test */
    public function stale_today_page_logs_entry_against_old_date_demonstrating_the_bug()
    {
        // The real issue is when a user gets a form with a stale date embedded in it
        // Let's simulate this by directly testing the form submission with a stale date
        
        $yesterday = Carbon::yesterday();
        $today = Carbon::today();
        
        // Simulate a form submission that came from a stale page
        // (as if the user opened the page yesterday but submitted today)
        $response = $this->actingAs($this->user)->post(route('lift-logs.store'), [
            'exercise_id' => $this->exercise->id,
            'date' => $yesterday->toDateString(), // Stale date from yesterday's page
            'weight' => 135,
            'reps' => 5,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // The bug: entry gets logged against yesterday's date
        // even though the user submitted it today
        $liftLog = LiftLog::where('exercise_id', $this->exercise->id)->first();
        $this->assertNotNull($liftLog);
        
        // This demonstrates the bug - the entry is logged against yesterday's date
        $this->assertEquals($yesterday->toDateString(), $liftLog->logged_at->toDateString());
        $this->assertNotEquals($today->toDateString(), $liftLog->logged_at->toDateString());
        
        // This is the problematic behavior: user expects today's entry but gets yesterday's
    }

    /** @test */
    public function historical_date_page_should_still_log_against_specified_date()
    {
        $twoDaysAgo = Carbon::today()->subDays(2);
        
        // User visits a specific historical date page
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts', [
            'date' => $twoDaysAgo->toDateString()
        ]));
        $response->assertOk();
        
        // Submit a lift log for that historical date
        $response = $this->actingAs($this->user)->post(route('lift-logs.store'), [
            'exercise_id' => $this->exercise->id,
            'date' => $twoDaysAgo->toDateString(),
            'weight' => 135,
            'reps' => 5,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // Entry should be logged against the historical date
        $liftLog = LiftLog::where('exercise_id', $this->exercise->id)->first();
        $this->assertNotNull($liftLog);
        $this->assertEquals($twoDaysAgo->toDateString(), $liftLog->logged_at->toDateString());
    }

    /** @test */
    public function after_fix_stale_today_page_should_log_against_current_date()
    {
        // This test verifies that the fix works correctly
        $yesterday = Carbon::yesterday();
        $today = Carbon::today();
        
        // Simulate a form submission from a "today" page (no date parameter)
        // This represents the fixed behavior where date is omitted for today pages
        $response = $this->actingAs($this->user)->post(route('lift-logs.store'), [
            'exercise_id' => $this->exercise->id,
            // No 'date' parameter - this simulates the fix where today pages omit the date
            'weight' => 135,
            'reps' => 5,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // After the fix: entry should be logged against today's date
        $liftLog = LiftLog::where('exercise_id', $this->exercise->id)->first();
        $this->assertNotNull($liftLog);
        
        // This verifies the fix - entry is logged against today's date
        $this->assertEquals($today->toDateString(), $liftLog->logged_at->toDateString());
        $this->assertNotEquals($yesterday->toDateString(), $liftLog->logged_at->toDateString());
    }
}