<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogControllerDateBugTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_to_correct_date_when_logging_for_tomorrow_late_at_night()
    {
        // Simulate it being late at night (11:30 PM)
        Carbon::setTestNow(Carbon::parse('2024-01-15 23:30:00'));
        
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // User wants to log for tomorrow (2024-01-16)
        $tomorrowDate = '2024-01-16';
        
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $tomorrowDate,
            'weight' => 100,
            'reps' => 10,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // Verify the lift log was created for the correct date
        $liftLog = LiftLog::where('user_id', $user->id)->first();
        $this->assertNotNull($liftLog);
        
        // The logged_at date should be 2024-01-16, NOT 2024-01-17
        $this->assertEquals('2024-01-16', $liftLog->logged_at->toDateString());
        
        // Reset Carbon test time
        Carbon::setTestNow();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_to_correct_date_when_logging_for_today()
    {
        // Simulate it being late at night (11:30 PM)
        Carbon::setTestNow(Carbon::parse('2024-01-15 23:30:00'));
        
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // User wants to log for today (2024-01-15)
        $todayDate = '2024-01-15';
        
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $todayDate,
            'weight' => 100,
            'reps' => 10,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // Verify the lift log was created for the correct date
        $liftLog = LiftLog::where('user_id', $user->id)->first();
        $this->assertNotNull($liftLog);
        
        // The logged_at date should be 2024-01-15, and time should be close to current time
        $this->assertEquals('2024-01-15', $liftLog->logged_at->toDateString());
        $this->assertEquals(23, $liftLog->logged_at->hour); // Should use current hour
        
        // Reset Carbon test time
        Carbon::setTestNow();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_time_rounding_without_crossing_date_boundaries()
    {
        // Simulate it being 11:58 PM (rounding would push to next day)
        Carbon::setTestNow(Carbon::parse('2024-01-15 23:58:00'));
        
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // User wants to log for today (2024-01-15)
        $todayDate = '2024-01-15';
        
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $todayDate,
            'weight' => 100,
            'reps' => 10,
            'rounds' => 3,
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        
        // Verify the lift log was created for the correct date
        $liftLog = LiftLog::where('user_id', $user->id)->first();
        $this->assertNotNull($liftLog);
        
        // The logged_at date should still be 2024-01-15, not 2024-01-16
        $this->assertEquals('2024-01-15', $liftLog->logged_at->toDateString());
        
        // Time should be rounded down to 23:45 instead of up to 00:00 (next day)
        $this->assertEquals(23, $liftLog->logged_at->hour);
        $this->assertEquals(45, $liftLog->logged_at->minute);
        
        // Reset Carbon test time
        Carbon::setTestNow();
    }
}