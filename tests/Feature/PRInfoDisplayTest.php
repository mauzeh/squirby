<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PRInfoDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);
    }

    /** @test */
    public function non_pr_lifts_show_current_records()
    {
        // Create a previous PR lift log
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(7)
        ]);
        $oldLog->liftSets()->create(['weight' => 200, 'reps' => 5, 'notes' => '']);
        
        // Create a new lift log that's NOT a PR (lighter weight)
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 180, 'reps' => 5, 'notes' => '']);
        
        // Visit the mobile-entry lifts page
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should NOT see PR badge
        $response->assertDontSee('🏆 PR');
        
        // Should see current records component
        $response->assertSee('pr-records-table', false);
        $response->assertSee('200 lbs'); // The record they need to beat
    }

    /** @test */
    public function pr_lifts_show_what_was_beaten()
    {
        // Create a previous lift log with lower weight
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        $oldLog->liftSets()->create(['weight' => 180, 'reps' => 1, 'notes' => '']);
        
        // Create a new PR lift log with higher weight
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 200, 'reps' => 1, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge
        $response->assertSee('🏆 PR');
        
        // Should see what was beaten (no title, just the data)
        $response->assertSee('180');
        $response->assertSee('200');
    }

    /** @test */
    public function non_pr_shows_volume_record()
    {
        // Create a previous lift log with high volume
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        $oldLog->liftSets()->create(['weight' => 100, 'reps' => 10, 'notes' => '']);
        $oldLog->liftSets()->create(['weight' => 100, 'reps' => 10, 'notes' => '']);
        $oldLog->liftSets()->create(['weight' => 100, 'reps' => 10, 'notes' => '']);
        
        // Create a new lift log with lower volume
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 100, 'reps' => 10, 'notes' => '']);
        $newLog->liftSets()->create(['weight' => 100, 'reps' => 10, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertSee('Volume');
        $response->assertSee('3,000 lbs'); // The volume record to beat
    }

    /** @test */
    public function non_pr_shows_rep_specific_records()
    {
        // Create a previous lift log with higher weight for 5 reps
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(10)
        ]);
        $oldLog->liftSets()->create(['weight' => 200, 'reps' => 5, 'notes' => '']);
        
        // Create a new lift log with lower weight for 5 reps
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 180, 'reps' => 5, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertSee('5 Reps');
        $response->assertSee('200 lbs'); // The record to beat
    }

    /** @test */
    public function first_lift_shows_no_records()
    {
        // Create first lift log for this exercise
        $log = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $log->liftSets()->create(['weight' => 135, 'reps' => 5, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge (first lift is always a PR)
        $response->assertSee('🏆 PR');
        
        // Should see first time message
        $response->assertSee('First time!');
        
        // Should NOT see "Current records"
        $response->assertDontSee('Current records');
    }

    /** @test */
    public function pr_lift_shows_beaten_records_not_current_records()
    {
        // Create a previous lift log
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        $oldLog->liftSets()->create(['weight' => 150, 'reps' => 5, 'notes' => '']);
        
        // Create a new PR lift log
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 160, 'reps' => 5, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge
        $response->assertSee('🏆 PR');
        
        // Should see the data (no title needed)
        $response->assertDontSee('Current records');
        
        // Should show the progression
        $response->assertSee('150');
        $response->assertSee('160');
    }

    /** @test */
    public function non_pr_shows_multiple_record_types()
    {
        // Create a previous lift log with multiple records
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        $oldLog->liftSets()->create(['weight' => 150, 'reps' => 5, 'notes' => '']);
        $oldLog->liftSets()->create(['weight' => 150, 'reps' => 5, 'notes' => '']);
        $oldLog->liftSets()->create(['weight' => 150, 'reps' => 5, 'notes' => '']);
        
        // Create a new lift log that doesn't beat any records
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 140, 'reps' => 5, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see current records with multiple types
        $response->assertSee('Est 1RM');
        $response->assertSee('Volume');
        $response->assertSee('5 Rep');
    }

    /** @test */
    public function bodyweight_exercises_show_no_records()
    {
        // Create a bodyweight exercise
        $bodyweightExercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'user_id' => null,
            'exercise_type' => 'bodyweight'
        ]);
        
        // Create previous log
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bodyweightExercise->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        $oldLog->liftSets()->create(['weight' => 0, 'reps' => 20, 'notes' => '']);
        
        // Create new log
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bodyweightExercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 0, 'reps' => 15, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should not see PR badge or records for bodyweight exercises
        $response->assertDontSee('🏆 PR');
        $response->assertDontSee('Current records');
        $response->assertDontSee('PRs beaten');
    }

    /** @test */
    public function true_one_rm_pr_shows_only_one_rep_row_not_estimated_one_rm()
    {
        // Create a previous lift log with a lower 1 rep max
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(7)
        ]);
        $oldLog->liftSets()->create(['weight' => 200, 'reps' => 1, 'notes' => '']);
        
        // Create a new PR lift log with a true 1RM (1 rep)
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 225, 'reps' => 1, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge
        $response->assertSee('🏆 PR');
        
        // Should see "1 Rep" row
        $response->assertSee('1 Rep');
        $response->assertSee('200');
        $response->assertSee('225');
        
        // Should NOT see "Est 1RM" or "1RM" label since it would be the same as "1 Rep"
        $response->assertDontSee('Est 1RM');
        $response->assertDontSee('1RM');
    }

    /** @test */
    public function estimated_one_rm_pr_shows_both_one_rm_and_rep_specific_rows()
    {
        // Create a previous lift log with lower weights
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(7)
        ]);
        $oldLog->liftSets()->create(['weight' => 180, 'reps' => 5, 'notes' => '']);
        
        // Create a new PR lift log with 5 reps (estimated 1RM will be higher than actual weight)
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 200, 'reps' => 5, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge
        $response->assertSee('🏆 PR');
        
        // Should see BOTH "Est 1RM" (estimated) and "5 Reps" rows
        $response->assertSee('Est 1RM');
        $response->assertSee('5 Reps');
        
        // Should see the rep-specific PR
        $response->assertSee('180');
        $response->assertSee('200');
    }

    /** @test */
    public function estimated_one_rm_close_to_true_one_rm_shows_both_rows()
    {
        // Create a previous lift log with a true 1RM
        $oldLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(7)
        ]);
        $oldLog->liftSets()->create(['weight' => 200, 'reps' => 1, 'notes' => '']);
        
        // Create a new lift log with 3 reps that estimates to ~220 lbs 1RM
        // This is close to but not the same as a true 1RM
        $newLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()
        ]);
        $newLog->liftSets()->create(['weight' => 205, 'reps' => 3, 'notes' => '']);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        
        // Should see PR badge
        $response->assertSee('🏆 PR');
        
        // Should see BOTH "Est 1RM" (estimated from 3 reps) and "3 Reps" rows
        // Even though the estimated 1RM might be close to a previous true 1RM,
        // they are different values and should both be shown
        $response->assertSee('Est 1RM');
        $response->assertSee('3 Reps');
    }
}
