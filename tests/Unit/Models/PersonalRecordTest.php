<?php

namespace Tests\Unit\Models;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalRecordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;
    protected LiftLog $liftLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);
    }

    /** @test */
    public function it_can_create_a_personal_record()
    {
        $pr = PersonalRecord::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'lift_log_id' => $this->liftLog->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
            'achieved_at' => now(),
        ]);

        $this->assertDatabaseHas('personal_records', [
            'id' => $pr->id,
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'lift_log_id' => $this->liftLog->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
        ]);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $pr->user);
        $this->assertEquals($this->user->id, $pr->user->id);
    }

    /** @test */
    public function it_belongs_to_an_exercise()
    {
        $pr = PersonalRecord::factory()->create([
            'exercise_id' => $this->exercise->id,
        ]);

        $this->assertInstanceOf(Exercise::class, $pr->exercise);
        $this->assertEquals($this->exercise->id, $pr->exercise->id);
    }

    /** @test */
    public function it_belongs_to_a_lift_log()
    {
        $pr = PersonalRecord::factory()->create([
            'lift_log_id' => $this->liftLog->id,
        ]);

        $this->assertInstanceOf(LiftLog::class, $pr->liftLog);
        $this->assertEquals($this->liftLog->id, $pr->liftLog->id);
    }

    /** @test */
    public function it_can_have_a_previous_pr()
    {
        $previousPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 300.00,
        ]);

        $currentPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
            'previous_pr_id' => $previousPR->id,
            'previous_value' => 300.00,
        ]);

        $this->assertInstanceOf(PersonalRecord::class, $currentPR->previousPR);
        $this->assertEquals($previousPR->id, $currentPR->previousPR->id);
        $this->assertEquals(300.00, $currentPR->previous_value);
    }

    /** @test */
    public function it_can_be_superseded_by_another_pr()
    {
        $previousPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 300.00,
        ]);

        $currentPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
            'previous_pr_id' => $previousPR->id,
        ]);

        $this->assertInstanceOf(PersonalRecord::class, $previousPR->supersededBy);
        $this->assertEquals($currentPR->id, $previousPR->supersededBy->id);
    }

    /** @test */
    public function current_scope_returns_only_unbeaten_prs()
    {
        $oldPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 300.00,
        ]);

        $currentPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
            'previous_pr_id' => $oldPR->id,
        ]);

        $currentPRs = PersonalRecord::current()->get();

        $this->assertCount(1, $currentPRs);
        $this->assertEquals($currentPR->id, $currentPRs->first()->id);
        $this->assertFalse($currentPRs->contains($oldPR));
    }

    /** @test */
    public function for_exercise_scope_filters_by_exercise()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id]);

        $pr1 = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
        ]);

        $pr2 = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
        ]);

        $results = PersonalRecord::forExercise($exercise1->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($pr1->id, $results->first()->id);
    }

    /** @test */
    public function of_type_scope_filters_by_pr_type()
    {
        $oneRmPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'one_rm',
        ]);

        $volumePR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'volume',
        ]);

        $results = PersonalRecord::ofType('one_rm')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($oneRmPR->id, $results->first()->id);
    }

    /** @test */
    public function it_can_store_rep_specific_pr_data()
    {
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'rep_specific',
            'rep_count' => 5,
            'value' => 275.00,
        ]);

        $this->assertEquals(5, $pr->rep_count);
        $this->assertEquals('rep_specific', $pr->pr_type);
        $this->assertEquals(275.00, $pr->value);
    }

    /** @test */
    public function it_can_store_hypertrophy_pr_data()
    {
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'pr_type' => 'hypertrophy',
            'weight' => 200.00,
            'value' => 12.00, // 12 reps at 200 lbs
        ]);

        $this->assertEquals(200.00, $pr->weight);
        $this->assertEquals('hypertrophy', $pr->pr_type);
        $this->assertEquals(12.00, $pr->value);
    }

    /** @test */
    public function it_casts_decimal_fields_correctly()
    {
        $pr = PersonalRecord::factory()->create([
            'value' => 315.50,
            'previous_value' => 300.25,
            'weight' => 200.75,
        ]);

        $this->assertIsString($pr->value);
        $this->assertEquals('315.50', $pr->value);
        $this->assertEquals('300.25', $pr->previous_value);
        $this->assertEquals('200.75', $pr->weight);
    }

    /** @test */
    public function it_casts_achieved_at_to_datetime()
    {
        $pr = PersonalRecord::factory()->create([
            'achieved_at' => '2026-01-22 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $pr->achieved_at);
    }

    /** @test */
    public function it_soft_deletes()
    {
        $pr = PersonalRecord::factory()->create();

        $pr->delete();

        $this->assertSoftDeleted('personal_records', [
            'id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_cascades_delete_when_user_is_force_deleted()
    {
        $user = User::factory()->create();
        $pr = PersonalRecord::factory()->create([
            'user_id' => $user->id,
        ]);

        $user->forceDelete();

        $this->assertDatabaseMissing('personal_records', [
            'id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_cascades_delete_when_exercise_is_force_deleted()
    {
        $exercise = Exercise::factory()->create();
        $pr = PersonalRecord::factory()->create([
            'exercise_id' => $exercise->id,
        ]);

        $exercise->forceDelete();

        $this->assertDatabaseMissing('personal_records', [
            'id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_cascades_delete_when_lift_log_is_force_deleted()
    {
        $liftLog = LiftLog::factory()->create();
        $pr = PersonalRecord::factory()->create([
            'lift_log_id' => $liftLog->id,
        ]);

        $liftLog->forceDelete();

        $this->assertDatabaseMissing('personal_records', [
            'id' => $pr->id,
        ]);
    }

    /** @test */
    public function scopes_can_be_chained()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        $oldPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'pr_type' => 'one_rm',
            'value' => 300.00,
        ]);

        $currentPR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'pr_type' => 'one_rm',
            'value' => 315.00,
            'previous_pr_id' => $oldPR->id,
        ]);

        $volumePR = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'pr_type' => 'volume',
            'value' => 5000.00,
        ]);

        // Get current one_rm PRs for exercise1
        $results = PersonalRecord::current()
            ->forExercise($exercise1->id)
            ->ofType('one_rm')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($currentPR->id, $results->first()->id);
    }
}
