<?php

namespace Tests\Feature;

use App\Enums\PRType;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests to ensure different PR types don't interfere with each other
 * and that each type is detected accurately and independently
 */
class PRTypeInterferenceTest extends TestCase
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
    public function first_time_rep_count_is_a_pr_by_design()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 5 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 100, 'reps' => 5]);

        // Second session: 80 lbs × 4 reps (lighter weight, but first time doing 4 reps)
        // This IS a PR because it's the first 4-rep attempt - system prioritizes accuracy
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 80,
            'reps' => 4,
            'rounds' => 1,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (rep-specific for first 4-rep attempt)
        $this->assertTrue($prFlags > 0);
        
        // Should have REP_SPECIFIC flag
        $this->assertTrue(PRType::REP_SPECIFIC->isIn($prFlags));
        
        $successMessage = session('success');
        $this->assertStringContainsString('PR!', $successMessage);
    }

    /** @test */
    public function rep_specific_pr_without_1rm_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 200 lbs × 3 reps (estimated 1RM ~218 lbs)
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 200, 'reps' => 3]);

        // Second session: 185 lbs × 5 reps (estimated 1RM ~208 lbs - LOWER than previous)
        // But this IS a rep-specific PR for 5 reps
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 185,
            'reps' => 5,
            'rounds' => 1,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (rep-specific)
        $this->assertTrue($prFlags > 0);
        
        // Should have REP_SPECIFIC but NOT ONE_RM
        $this->assertTrue(PRType::REP_SPECIFIC->isIn($prFlags));
        $this->assertFalse(PRType::ONE_RM->isIn($prFlags));
    }

    /** @test */
    public function volume_pr_without_1rm_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 5 reps × 1 set = 500 lbs volume
        // Also establish a 4-rep baseline so rep-specific doesn't trigger
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        $firstLog->liftSets()->create(['weight' => 100, 'reps' => 5]);
        
        // Establish 4-rep baseline
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $secondLog->liftSets()->create(['weight' => 85, 'reps' => 4]);

        // Third session: 80 lbs × 4 reps × 2 sets = 640 lbs volume
        // Lower weight than 4-rep baseline, but higher total volume
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 80,
            'reps' => 4,
            'rounds' => 2,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (volume only)
        $this->assertTrue($prFlags > 0);
        
        // Should have VOLUME but NOT ONE_RM or REP_SPECIFIC
        $this->assertTrue(PRType::VOLUME->isIn($prFlags));
        $this->assertFalse(PRType::ONE_RM->isIn($prFlags));
        $this->assertFalse(PRType::REP_SPECIFIC->isIn($prFlags));
    }

    /** @test */
    public function one_rm_pr_without_rep_specific_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 180 lbs × 5 reps (estimated 1RM ~202 lbs)
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 180, 'reps' => 5]);

        // Second session: 175 lbs × 6 reps (estimated 1RM ~209 lbs - HIGHER)
        // But NOT a rep-specific PR for 6 reps if we had a heavier 6-rep before
        // Let's add a previous 6-rep lift
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        $secondLog->liftSets()->create(['weight' => 185, 'reps' => 6]);

        // Now log 175 lbs × 6 reps
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 175,
            'reps' => 6,
            'rounds' => 1,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (1RM only)
        $this->assertTrue($prFlags > 0);
        
        // Should have ONE_RM but NOT REP_SPECIFIC (because 185×6 was heavier)
        $this->assertTrue(PRType::ONE_RM->isIn($prFlags));
        $this->assertFalse(PRType::REP_SPECIFIC->isIn($prFlags));
    }

    /** @test */
    public function all_three_pr_types_simultaneously()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 3 reps × 1 set = 300 lbs volume
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 100, 'reps' => 3]);

        // Second session: 120 lbs × 5 reps × 3 sets = 1800 lbs volume
        // - Higher estimated 1RM (120×5 ~135 vs 100×3 ~109)
        // - First time doing 5 reps at any weight (rep-specific PR)
        // - Much higher volume (1800 vs 300)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 120,
            'reps' => 5,
            'rounds' => 3,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR
        $this->assertTrue($prFlags > 0);
        
        // Should have ALL THREE PR types
        $this->assertTrue(PRType::ONE_RM->isIn($prFlags));
        $this->assertTrue(PRType::REP_SPECIFIC->isIn($prFlags));
        $this->assertTrue(PRType::VOLUME->isIn($prFlags));
    }

    /** @test */
    public function rep_specific_pr_only_applies_to_1_to_10_reps()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 15 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 100, 'reps' => 15]);

        // Second session: 110 lbs × 15 reps (heavier for same rep count)
        // But rep-specific PRs only apply to 1-10 reps
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 110,
            'reps' => 15,
            'rounds' => 1,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (1RM and possibly volume)
        $this->assertTrue($prFlags > 0);
        
        // Should have ONE_RM but NOT REP_SPECIFIC (because 15 reps > 10)
        $this->assertTrue(PRType::ONE_RM->isIn($prFlags));
        $this->assertFalse(PRType::REP_SPECIFIC->isIn($prFlags));
    }

    /** @test */
    public function volume_pr_with_varying_set_weights()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 5 reps × 3 sets = 1500 lbs volume
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5],
            ['weight' => 100, 'reps' => 5],
            ['weight' => 100, 'reps' => 5],
        ]);

        // Second session: Pyramid sets with varying weights
        // 110×5 + 105×5 + 100×5 = 1575 lbs volume (slightly more)
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);
        $secondLog->liftSets()->createMany([
            ['weight' => 110, 'reps' => 5],
            ['weight' => 105, 'reps' => 5],
            ['weight' => 100, 'reps' => 5],
        ]);

        // Use the service directly to check
        $prService = app(\App\Services\PRDetectionService::class);
        $prFlags = $prService->isLiftLogPR($secondLog, $exercise, $this->user);
        
        // Should be a PR (volume and possibly others)
        $this->assertTrue($prFlags > 0);
        
        // Should have VOLUME PR
        $this->assertTrue(PRType::VOLUME->isIn($prFlags));
    }

    /** @test */
    public function no_pr_when_all_metrics_are_worse_or_equal()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 150 lbs × 5 reps × 3 sets = 2250 lbs volume
        // Also establish baselines for 4 reps
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        $firstLog->liftSets()->createMany([
            ['weight' => 150, 'reps' => 5],
            ['weight' => 150, 'reps' => 5],
            ['weight' => 150, 'reps' => 5],
        ]);
        
        // Establish 4-rep baseline that's heavier
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $secondLog->liftSets()->createMany([
            ['weight' => 145, 'reps' => 4],
            ['weight' => 145, 'reps' => 4],
        ]);

        // Third session: 140 lbs × 4 reps × 2 sets = 1120 lbs volume
        // Lower weight than 4-rep baseline, fewer reps, less volume, lower 1RM
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 140,
            'reps' => 4,
            'rounds' => 2,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should NOT be a PR
        $this->assertEquals(0, $prFlags);
        $this->assertFalse(PRType::ONE_RM->isIn($prFlags));
        $this->assertFalse(PRType::REP_SPECIFIC->isIn($prFlags));
        $this->assertFalse(PRType::VOLUME->isIn($prFlags));
    }

    /** @test */
    public function tolerance_prevents_false_positives_for_volume()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 5 reps × 3 sets = 1500 lbs volume
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5],
            ['weight' => 100, 'reps' => 5],
            ['weight' => 100, 'reps' => 5],
        ]);

        // Second session: 100.05 lbs × 5 reps × 3 sets = 1501.5 lbs volume
        // Only 1.5 lbs more - within tolerance (0.1 lbs per comparison)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100.05,
            'reps' => 5,
            'rounds' => 3,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should NOT be a PR (within tolerance)
        $this->assertEquals(0, $prFlags);
    }
}
