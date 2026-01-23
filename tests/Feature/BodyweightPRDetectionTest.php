<?php

namespace Tests\Feature;

use App\Events\LiftLogCompleted;
use App\Listeners\DetectAndRecordPRs;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyweightPRDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $bodyweightExercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight',
        ]);
    }

    /** @test */
    public function first_pure_bodyweight_lift_creates_volume_pr_using_total_reps()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
        ]);
        
        // 3 sets of 10 reps = 30 total reps
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 10]);
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 10]);
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 10]);

        event(new LiftLogCompleted($liftLog));

        // Should create a Volume PR
        $pr = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($pr);
        $this->assertEquals(30, $pr->value); // Total reps
        $this->assertNull($pr->previous_pr_id);
        $this->assertNull($pr->previous_value);
        
        $liftLog->refresh();
        $this->assertTrue($liftLog->is_pr);
        $this->assertEquals(1, $liftLog->pr_count);
    }

    /** @test */
    public function pure_bodyweight_volume_pr_uses_total_reps_not_weight_times_reps()
    {
        // First lift: 20 total reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 0, 'reps' => 20]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 25 total reps (Volume PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 0, 'reps' => 25]);
        event(new LiftLogCompleted($secondLog));

        // Should create a Volume PR
        $pr = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($pr);
        $this->assertEquals(25, $pr->value); // Total reps
        $this->assertEquals(20, $pr->previous_value);
    }

    /** @test */
    public function bodyweight_with_extra_weight_creates_volume_pr_using_weight_times_reps()
    {
        // First lift: 10 lbs × 10 reps = 100 lbs volume
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 10, 'reps' => 10]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 10 lbs × 12 reps = 120 lbs volume (Volume PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 10, 'reps' => 12]);
        event(new LiftLogCompleted($secondLog));

        // Should create a Volume PR using weight × reps
        $pr = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($pr);
        $this->assertEquals(120, $pr->value); // weight × reps
        $this->assertEquals(100, $pr->previous_value);
    }

    /** @test */
    public function bodyweight_with_extra_weight_creates_rep_specific_pr()
    {
        // First lift: 10 lbs for 5 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 10, 'reps' => 5]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 15 lbs for 5 reps (Rep-Specific PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 15, 'reps' => 5]);
        event(new LiftLogCompleted($secondLog));

        // Should create a Rep-Specific PR
        $pr = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 5)
            ->first();
            
        $this->assertNotNull($pr);
        $this->assertEquals(15, $pr->value); // weight
        $this->assertEquals(5, $pr->rep_count);
        $this->assertEquals(10, $pr->previous_value);
    }

    /** @test */
    public function pure_bodyweight_does_not_create_rep_specific_pr()
    {
        // Pure bodyweight (no extra weight) should NOT create rep-specific PRs
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
        ]);
        
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 10]);
        event(new LiftLogCompleted($liftLog));

        // Should only create Volume PR, not Rep-Specific PR
        $volumePR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'volume')
            ->first();
        $repSpecificPR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'rep_specific')
            ->first();
            
        $this->assertNotNull($volumePR);
        $this->assertNull($repSpecificPR);
    }

    /** @test */
    public function bodyweight_does_not_create_one_rm_pr()
    {
        // Bodyweight exercises should NEVER create 1RM PRs
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
        ]);
        
        // Even with extra weight
        $liftLog->liftSets()->create(['weight' => 25, 'reps' => 5]);
        event(new LiftLogCompleted($liftLog));

        // Should NOT create 1RM PR
        $oneRmPR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'one_rm')
            ->first();
            
        $this->assertNull($oneRmPR);
    }

    /** @test */
    public function bodyweight_does_not_create_hypertrophy_pr()
    {
        // Bodyweight exercises should NOT create hypertrophy PRs
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 25, 'reps' => 8]);
        event(new LiftLogCompleted($firstLog));

        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 25, 'reps' => 10]); // More reps at same weight
        event(new LiftLogCompleted($secondLog));

        // Should NOT create hypertrophy PR
        $hypertrophyPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'hypertrophy')
            ->first();
            
        $this->assertNull($hypertrophyPR);
    }

    /** @test */
    public function transition_from_pure_bodyweight_to_weighted_maintains_separate_pr_tracking()
    {
        // Day 1: Pure bodyweight - 30 reps
        $pureLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        $pureLog->liftSets()->create(['weight' => 0, 'reps' => 30]);
        event(new LiftLogCompleted($pureLog));

        // Day 2: Add 10 lbs - 20 reps (less reps but weighted)
        $weightedLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $weightedLog->liftSets()->create(['weight' => 10, 'reps' => 20]);
        event(new LiftLogCompleted($weightedLog));

        // Day 3: 10 lbs - 25 reps (Volume PR for weighted)
        $improvedLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $improvedLog->liftSets()->create(['weight' => 10, 'reps' => 25]);
        event(new LiftLogCompleted($improvedLog));

        // Check that weighted volume PR was created
        $volumePR = PersonalRecord::where('lift_log_id', $improvedLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($volumePR);
        $this->assertEquals(250, $volumePR->value); // 10 × 25
        $this->assertEquals(200, $volumePR->previous_value); // 10 × 20
    }

    /** @test */
    public function mixed_sets_with_and_without_weight_uses_volume_calculation()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
        ]);
        
        // Mixed: some sets with weight, some without
        $liftLog->liftSets()->create(['weight' => 10, 'reps' => 10]); // 100 lbs
        $liftLog->liftSets()->create(['weight' => 0, 'reps' => 15]);  // 0 lbs (bodyweight)
        $liftLog->liftSets()->create(['weight' => 5, 'reps' => 12]);  // 60 lbs
        // Total volume: 160 lbs
        
        event(new LiftLogCompleted($liftLog));

        $volumePR = PersonalRecord::where('lift_log_id', $liftLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($volumePR);
        $this->assertEquals(160, $volumePR->value);
    }

    /** @test */
    public function bodyweight_volume_pr_not_awarded_for_fewer_reps()
    {
        // First lift: 30 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 0, 'reps' => 30]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 25 reps (not a PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 0, 'reps' => 25]);
        event(new LiftLogCompleted($secondLog));

        // Should NOT create a Volume PR
        $pr = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNull($pr);
        
        $secondLog->refresh();
        $this->assertFalse($secondLog->is_pr);
        $this->assertEquals(0, $secondLog->pr_count);
    }

    /** @test */
    public function bodyweight_rep_specific_pr_only_awarded_for_rep_counts_up_to_10()
    {
        // First lift: 15 lbs for 12 reps (above MAX_REP_COUNT_FOR_PR of 10)
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 15, 'reps' => 12]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 20 lbs for 12 reps (heavier but still above 10 reps)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 20, 'reps' => 12]);
        event(new LiftLogCompleted($secondLog));

        // Should NOT create Rep-Specific PR (12 reps > 10)
        $repSpecificPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->first();
            
        $this->assertNull($repSpecificPR);
        
        // But should create Volume PR
        $volumePR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'volume')
            ->first();
            
        $this->assertNotNull($volumePR);
    }

    /** @test */
    public function bodyweight_can_achieve_both_volume_and_rep_specific_pr_simultaneously()
    {
        // First lift: 10 lbs for 5 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 10, 'reps' => 5]);
        event(new LiftLogCompleted($firstLog));

        // Second lift: 15 lbs for 8 reps (both Volume and Rep-Specific PR)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->create(['weight' => 15, 'reps' => 8]);
        event(new LiftLogCompleted($secondLog));

        // Should create both Volume and Rep-Specific PRs
        $volumePR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'volume')
            ->first();
        $repSpecificPR = PersonalRecord::where('lift_log_id', $secondLog->id)
            ->where('pr_type', 'rep_specific')
            ->where('rep_count', 8)
            ->first();
            
        $this->assertNotNull($volumePR);
        $this->assertNotNull($repSpecificPR);
        
        $secondLog->refresh();
        $this->assertTrue($secondLog->is_pr);
        $this->assertEquals(2, $secondLog->pr_count);
    }
}
