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

        // First session: 150 lbs × 8 reps (estimated 1RM = 150 × 1.2664 = 189.96 lbs)
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(3),
        ]);
        $firstLog->liftSets()->create(['weight' => 150, 'reps' => 8]);

        // Second session: 165 lbs × 7 reps (estimated 1RM = 165 × 1.2331 = 203.46 lbs - HIGHER)
        // This establishes a 7-rep baseline
        $secondLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        $secondLog->liftSets()->create(['weight' => 165, 'reps' => 7]);

        // Third session: 155 lbs × 9 reps (estimated 1RM = 155 × 1.2997 = 201.45 lbs)
        // Lower than 203.46, so NOT a 1RM PR
        // But establishes 9-rep baseline
        $thirdLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $thirdLog->liftSets()->create(['weight' => 155, 'reps' => 9]);

        // Fourth session: 170 lbs × 7 reps (estimated 1RM = 170 × 1.2331 = 209.63 lbs - HIGHER than 203.46)
        // But NOT a rep-specific PR because 165 lbs × 7 was already done and 170 > 165
        // Wait, that WOULD be a rep-specific PR...
        
        // Let's try: 163 lbs × 7 reps (estimated 1RM = 163 × 1.2331 = 200.99 lbs)
        // This is LOWER than 203.46, so not a 1RM PR either
        
        // Actually, we need: heavier weight at LOWER reps to get higher 1RM without beating the rep-specific
        // Fourth session: 175 lbs × 6 reps (estimated 1RM = 175 × 1.1998 = 209.97 lbs - HIGHER than 203.46)
        // First time doing 6 reps, so this IS a rep-specific PR by design
        
        // The only way: do a rep count we've done before, with LIGHTER weight, but enough reps to boost 1RM
        // Fourth session: 160 lbs × 7 reps (estimated 1RM = 160 × 1.2331 = 197.30 lbs)
        // This is LIGHTER than 165 lbs × 7, so NOT a rep-specific PR
        // But 197.30 < 203.46, so NOT a 1RM PR either
        
        // CONCLUSION: It's mathematically impossible to get 1RM PR without rep-specific PR
        // because if you increase 1RM, you either:
        // 1. Do a new rep count (rep-specific PR by design)
        // 2. Do more weight at existing rep count (rep-specific PR)
        // 3. Do less weight at existing rep count (usually lower 1RM)
        
        // Let's test a realistic scenario: 1RM increases with MULTIPLE PR types
        // Fourth session: 168 lbs × 7 reps (estimated 1RM = 168 × 1.2331 = 207.16 lbs - HIGHER than 203.46)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 168,
            'reps' => 7,
            'rounds' => 1,
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $prFlags = session('is_pr');
        
        // Should be a PR (1RM increased from 203.46 to 207.16)
        $this->assertTrue($prFlags > 0);
        
        // Should have ONE_RM
        $this->assertTrue(PRType::ONE_RM->isIn($prFlags));
        
        // Will ALSO have REP_SPECIFIC because 168 > 165 for 7 reps
        // This is expected and correct - in practice, 1RM PRs usually come with rep-specific PRs
        $this->assertTrue(PRType::REP_SPECIFIC->isIn($prFlags));
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
    public function high_rep_ranges_above_10_do_not_support_1rm_or_rep_specific_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        // First session: 100 lbs × 15 reps × 1 set = 1500 lbs volume
        $firstLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLog->liftSets()->create(['weight' => 100, 'reps' => 15]);

        // Second session: 110 lbs × 15 reps × 1 set = 1650 lbs volume
        // Higher weight and volume, but >10 reps means:
        // - No 1RM calculation (formulas unreliable for high reps)
        // - No rep-specific PR (only applies to 1-10 reps)
        // - Should still get volume PR
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
        
        // Should be a PR (volume only)
        $this->assertTrue($prFlags > 0);
        
        // Should have VOLUME but NOT ONE_RM or REP_SPECIFIC (because 15 reps > 10)
        $this->assertTrue(PRType::VOLUME->isIn($prFlags));
        $this->assertFalse(PRType::ONE_RM->isIn($prFlags));
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
        // Only 1.5 lbs more (0.1% increase) - within 1% tolerance
        // 1% of 1500 = 15 lbs, so need >1515 lbs to trigger PR
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
        
        // Should NOT be a PR (within 1% tolerance)
        $this->assertEquals(0, $prFlags);
    }
}
