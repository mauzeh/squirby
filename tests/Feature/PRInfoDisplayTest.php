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
        $response->assertSee('Current records');
        $response->assertSee('200.0 lbs'); // The record they need to beat
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
        
        // Should see what was beaten
        $response->assertSee('Records beaten');
        $response->assertSee('180.0');
        $response->assertSee('200.0');
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
        $response->assertSee('Current records:');
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
        $response->assertSee('Current records:');
        $response->assertSee('5 Reps');
        $response->assertSee('200.0 lbs'); // The record to beat
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
        
        // Should see "Records beaten" not "Current records"
        $response->assertSee('Records beaten');
        $response->assertDontSee('Current records');
        
        // Should show the progression
        $response->assertSee('150.0');
        $response->assertSee('160.0');
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
        $response->assertSee('Current records');
        $response->assertSee('1RM');
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
}
