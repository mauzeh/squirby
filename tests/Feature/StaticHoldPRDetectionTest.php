<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticHoldPRDetectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function first_static_hold_creates_time_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'L-sit',
        ]);

        // First hold: 30 seconds bodyweight
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 30, // 30 seconds
        ]);

        // Trigger PR detection
        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should create TIME PR
        $timePR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'time')
            ->first();

        $this->assertNotNull($timePR);
        $this->assertEquals(30, $timePR->value);
        $this->assertNull($timePR->previous_pr_id);
        $this->assertNull($timePR->previous_value);
    }

    /** @test */
    public function longer_hold_creates_time_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Plank',
        ]);

        // First hold: 45 seconds
        $firstLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 0,
            'reps' => 45,
        ]);

        event(new \App\Events\LiftLogCompleted($firstLog));

        // Second hold: 60 seconds (PR!)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 0,
            'reps' => 60,
        ]);

        event(new \App\Events\LiftLogCompleted($secondLog));

        // Should create TIME PR
        $timePR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'time')
            ->first();

        $this->assertNotNull($timePR);
        $this->assertEquals(60, $timePR->value);
        $this->assertEquals(45, $timePR->previous_value);
    }

    /** @test */
    public function shorter_hold_does_not_create_time_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Front Lever',
        ]);

        // First hold: 20 seconds
        $firstLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 0,
            'reps' => 20,
        ]);

        event(new \App\Events\LiftLogCompleted($firstLog));

        // Second hold: 15 seconds (not a PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 0,
            'reps' => 15,
        ]);

        event(new \App\Events\LiftLogCompleted($secondLog));

        // Should NOT create TIME PR
        $timePR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'time')
            ->first();

        $this->assertNull($timePR);
    }

    /** @test */
    public function weighted_static_hold_creates_rep_specific_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Weighted Plank',
        ]);

        // First weighted hold: 30 seconds with 25 lbs
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25,
            'reps' => 30,
        ]);

        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should create REP_SPECIFIC PR (weight stored in rep_count)
        $repSpecificPR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 25)
            ->first();

        $this->assertNotNull($repSpecificPR);
        $this->assertEquals(30, $repSpecificPR->value); // duration
        $this->assertEquals(25, $repSpecificPR->rep_count); // weight
    }

    /** @test */
    public function longer_weighted_hold_creates_rep_specific_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Weighted L-sit',
        ]);

        // First hold: 20 seconds with 10 lbs
        $firstLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 10,
            'reps' => 20,
        ]);

        event(new \App\Events\LiftLogCompleted($firstLog));

        // Second hold: 30 seconds with 10 lbs (PR!)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 10,
            'reps' => 30,
        ]);

        event(new \App\Events\LiftLogCompleted($secondLog));

        // Should create REP_SPECIFIC PR
        $repSpecificPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 10)
            ->first();

        $this->assertNotNull($repSpecificPR);
        $this->assertEquals(30, $repSpecificPR->value);
        $this->assertEquals(20, $repSpecificPR->previous_value);
    }

    /** @test */
    public function static_hold_can_achieve_both_time_and_rep_specific_pr_simultaneously()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Handstand',
        ]);

        // First hold: 30 seconds bodyweight
        $firstLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 0,
            'reps' => 30,
        ]);

        event(new \App\Events\LiftLogCompleted($firstLog));

        // Second hold: 45 seconds with 5 lbs (both TIME and REP_SPECIFIC PR!)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 5,
            'reps' => 45,
        ]);

        event(new \App\Events\LiftLogCompleted($secondLog));

        // Should create both TIME and REP_SPECIFIC PRs
        $timePR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'time')
            ->first();

        $repSpecificPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 5)
            ->first();

        $this->assertNotNull($timePR);
        $this->assertNotNull($repSpecificPR);
        $this->assertEquals(45, $timePR->value);
        $this->assertEquals(45, $repSpecificPR->value);
    }

    /** @test */
    public function multiple_sets_tracks_best_hold_for_time_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Hollow Body Hold',
        ]);

        // Multiple sets with varying durations
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 40, // First set: 40s
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 50, // Second set: 50s (best)
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 35, // Third set: 35s
        ]);

        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should use best hold (50s) for TIME PR
        $timePR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'time')
            ->first();

        $this->assertNotNull($timePR);
        $this->assertEquals(50, $timePR->value);
    }

    /** @test */
    public function different_weights_create_separate_rep_specific_prs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Weighted Planche',
        ]);

        // First session: bodyweight and 10 lbs
        $firstLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 0,
            'reps' => 20,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $firstLog->id,
            'weight' => 10,
            'reps' => 15,
        ]);

        event(new \App\Events\LiftLogCompleted($firstLog));

        // Second session: improved both weights
        $secondLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 0,
            'reps' => 25, // PR at bodyweight
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $secondLog->id,
            'weight' => 10,
            'reps' => 20, // PR at 10 lbs
        ]);

        event(new \App\Events\LiftLogCompleted($secondLog));

        // Should create separate REP_SPECIFIC PRs for each weight
        $bodyweightPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 0)
            ->first();

        $weightedPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 10)
            ->first();

        $this->assertNotNull($bodyweightPR);
        $this->assertNotNull($weightedPR);
        $this->assertEquals(25, $bodyweightPR->value);
        $this->assertEquals(20, $weightedPR->value);
    }

    /** @test */
    public function static_hold_does_not_create_one_rm_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'L-sit',
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 30,
        ]);

        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should NOT create ONE_RM PR
        $oneRMPR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'one_rm')
            ->first();

        $this->assertNull($oneRMPR);
    }

    /** @test */
    public function static_hold_does_not_create_volume_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Plank',
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 60,
        ]);

        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should NOT create VOLUME PR
        $volumePR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'volume')
            ->first();

        $this->assertNull($volumePR);
    }

    /** @test */
    public function static_hold_does_not_create_hypertrophy_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'static_hold',
            'title' => 'Front Lever',
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 10,
            'reps' => 20,
        ]);

        event(new \App\Events\LiftLogCompleted($liftLog));

        // Should NOT create HYPERTROPHY PR
        $hypertrophyPR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'hypertrophy')
            ->first();

        $this->assertNull($hypertrophyPR);
    }
}
