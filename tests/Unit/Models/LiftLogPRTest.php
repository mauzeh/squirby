<?php

namespace Tests\Unit\Models;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiftLogPRTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function lift_log_has_personal_records_relationship()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);

        $pr1 = PersonalRecord::factory()->create([
            'lift_log_id' => $liftLog->id,
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);

        $pr2 = PersonalRecord::factory()->create([
            'lift_log_id' => $liftLog->id,
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);

        $this->assertCount(2, $liftLog->personalRecords);
        $this->assertTrue($liftLog->personalRecords->contains($pr1));
        $this->assertTrue($liftLog->personalRecords->contains($pr2));
    }

    /** @test */
    public function is_pr_returns_true_when_flag_is_set()
    {
        $liftLog = LiftLog::factory()->create([
            'is_pr' => true,
        ]);

        $this->assertTrue($liftLog->isPR());
    }

    /** @test */
    public function is_pr_returns_false_when_flag_is_not_set()
    {
        $liftLog = LiftLog::factory()->create([
            'is_pr' => false,
        ]);

        $this->assertFalse($liftLog->isPR());
    }

    /** @test */
    public function get_pr_count_returns_correct_count()
    {
        $liftLog = LiftLog::factory()->create([
            'pr_count' => 3,
        ]);

        $this->assertEquals(3, $liftLog->getPRCount());
    }

    /** @test */
    public function get_pr_count_returns_zero_by_default()
    {
        $liftLog = LiftLog::factory()->create([
            'pr_count' => 0,
        ]);

        $this->assertEquals(0, $liftLog->getPRCount());
    }

    /** @test */
    public function is_pr_and_pr_count_are_fillable()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);

        $liftLog->update([
            'is_pr' => true,
            'pr_count' => 2,
        ]);

        $this->assertTrue($liftLog->is_pr);
        $this->assertEquals(2, $liftLog->pr_count);
    }

    /** @test */
    public function lift_log_can_be_created_with_pr_flags()
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'is_pr' => true,
            'pr_count' => 2,
        ]);

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'is_pr' => true,
            'pr_count' => 2,
        ]);
    }
}
