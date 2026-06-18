<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\LiftSet;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogMultiSetTest extends TestCase
{
    use RefreshDatabase;

    private function createLiftLogWithSets(array $sets, ?User $user = null): LiftLog
    {
        $user = $user ?? User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        foreach ($sets as $set) {
            LiftSet::create([
                'lift_log_id' => $liftLog->id,
                'weight' => $set['weight'],
                'reps' => $set['reps'],
                'unit' => $set['unit'] ?? 'lbs',
            ]);
        }

        $liftLog->load('liftSets');

        return $liftLog;
    }

    // ─── hasUniformSets() ───────────────────────────────────────────────

    /** @test */
    public function uniform_sets_are_detected_when_all_weight_and_reps_match()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
        ]);

        $this->assertTrue($liftLog->hasUniformSets());
    }

    /** @test */
    public function non_uniform_sets_detected_when_weights_differ()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 205, 'reps' => 5],
            ['weight' => 225, 'reps' => 5],
        ]);

        $this->assertFalse($liftLog->hasUniformSets());
    }

    /** @test */
    public function non_uniform_sets_detected_when_reps_differ()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 3],
            ['weight' => 185, 'reps' => 1],
        ]);

        $this->assertFalse($liftLog->hasUniformSets());
    }

    /** @test */
    public function non_uniform_sets_detected_when_both_weight_and_reps_differ()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 205, 'reps' => 3],
            ['weight' => 225, 'reps' => 1],
        ]);

        $this->assertFalse($liftLog->hasUniformSets());
    }

    /** @test */
    public function single_set_is_always_uniform()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 225, 'reps' => 1],
        ]);

        $this->assertTrue($liftLog->hasUniformSets());
    }

    /** @test */
    public function empty_sets_collection_is_uniform()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        $liftLog->load('liftSets');

        $this->assertTrue($liftLog->hasUniformSets());
    }

    /** @test */
    public function uniform_check_uses_numeric_comparison_for_float_weights()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185.0, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
        ]);

        $this->assertTrue($liftLog->hasUniformSets());
    }

    // ─── formatSetsSummary() ────────────────────────────────────────────

    /** @test */
    public function format_summary_for_uniform_sets()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
        ]);

        $this->assertEquals('3×5 @ 185 lbs', $liftLog->formatSetsSummary());
    }

    /** @test */
    public function format_summary_for_non_uniform_sets()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 205, 'reps' => 3],
            ['weight' => 225, 'reps' => 1],
        ]);

        $this->assertEquals('185×5 / 205×3 / 225×1 lbs', $liftLog->formatSetsSummary());
    }

    /** @test */
    public function format_summary_respects_unit_override()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 100, 'reps' => 5, 'unit' => 'kg'],
            ['weight' => 100, 'reps' => 5, 'unit' => 'kg'],
        ]);

        $this->assertEquals('2×5 @ 100 kg', $liftLog->formatSetsSummary('kg'));
    }

    /** @test */
    public function format_summary_uses_first_set_unit_when_no_override()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 80, 'reps' => 8, 'unit' => 'kg'],
            ['weight' => 80, 'reps' => 8, 'unit' => 'kg'],
            ['weight' => 80, 'reps' => 8, 'unit' => 'kg'],
        ]);

        $this->assertEquals('3×8 @ 80 kg', $liftLog->formatSetsSummary());
    }

    /** @test */
    public function format_summary_for_bodyweight_with_zero_weight()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 0, 'reps' => 10],
            ['weight' => 0, 'reps' => 10],
            ['weight' => 0, 'reps' => 10],
        ]);

        $this->assertEquals('3×10', $liftLog->formatSetsSummary());
    }

    /** @test */
    public function format_summary_non_uniform_with_zero_weight_sets()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 0, 'reps' => 10],
            ['weight' => 0, 'reps' => 8],
            ['weight' => 0, 'reps' => 6],
        ]);

        $this->assertEquals('10 reps / 8 reps / 6 reps lbs', $liftLog->formatSetsSummary());
    }

    /** @test */
    public function format_summary_removes_trailing_decimals_from_integer_weights()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 135.0, 'reps' => 10],
            ['weight' => 135.0, 'reps' => 10],
        ]);

        $summary = $liftLog->formatSetsSummary();
        $this->assertStringNotContainsString('.0', $summary);
        $this->assertEquals('2×10 @ 135 lbs', $summary);
    }

    /** @test */
    public function format_summary_preserves_meaningful_decimals()
    {
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 92.5, 'reps' => 5],
            ['weight' => 92.5, 'reps' => 5],
        ]);

        $this->assertEquals('2×5 @ 92.5 lbs', $liftLog->formatSetsSummary());
    }

    // ─── formatMobileSummaryDisplay (grouped badges via strategy) ───────

    /** @test */
    public function grouped_badges_collapses_identical_consecutive_sets()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 45, 'reps' => 8],
            ['weight' => 45, 'reps' => 8],
            ['weight' => 45, 'reps' => 8],
            ['weight' => 45, 'reps' => 8],
            ['weight' => 45, 'reps' => 8],
            ['weight' => 65, 'reps' => 8],
        ], $user);

        $strategy = $liftLog->exercise->getTypeStrategy();
        $display = $strategy->formatMobileSummaryDisplay($liftLog);

        $this->assertArrayHasKey('multiSetBadges', $display);
        $this->assertCount(2, $display['multiSetBadges']);
        $this->assertStringContainsString('5×8', $display['multiSetBadges'][0]);
        $this->assertStringContainsString('45', $display['multiSetBadges'][0]);
        $this->assertStringContainsString('1×8', $display['multiSetBadges'][1]);
        $this->assertStringContainsString('65', $display['multiSetBadges'][1]);
    }

    /** @test */
    public function grouped_badges_for_ascending_weight_pyramid()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 205, 'reps' => 3],
            ['weight' => 225, 'reps' => 1],
        ], $user);

        $strategy = $liftLog->exercise->getTypeStrategy();
        $display = $strategy->formatMobileSummaryDisplay($liftLog);

        $this->assertArrayHasKey('multiSetBadges', $display);
        $this->assertCount(3, $display['multiSetBadges']);
        $this->assertStringContainsString('1×5', $display['multiSetBadges'][0]);
        $this->assertStringContainsString('185', $display['multiSetBadges'][0]);
        $this->assertStringContainsString('1×3', $display['multiSetBadges'][1]);
        $this->assertStringContainsString('1×1', $display['multiSetBadges'][2]);
    }

    /** @test */
    public function grouped_badges_for_bodyweight_zero_weight()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'bodyweight']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        LiftSet::create(['lift_log_id' => $liftLog->id, 'weight' => 0, 'reps' => 10, 'unit' => 'lbs']);
        LiftSet::create(['lift_log_id' => $liftLog->id, 'weight' => 0, 'reps' => 10, 'unit' => 'lbs']);
        LiftSet::create(['lift_log_id' => $liftLog->id, 'weight' => 0, 'reps' => 8, 'unit' => 'lbs']);
        $liftLog->load('liftSets');

        $strategy = $exercise->getTypeStrategy();
        $display = $strategy->formatMobileSummaryDisplay($liftLog);

        $this->assertArrayHasKey('multiSetBadges', $display);
        $this->assertCount(2, $display['multiSetBadges']);
        $this->assertEquals('2×10', $display['multiSetBadges'][0]);
        $this->assertEquals('1×8', $display['multiSetBadges'][1]);
    }

    // ─── Edit guard (controller-level) ──────────────────────────────────

    /** @test */
    public function edit_page_shows_read_only_view_for_non_uniform_sets()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 205, 'reps' => 3],
            ['weight' => 225, 'reps' => 1],
        ], $user);

        $response = $this->actingAs($user)->get(route('lift-logs.edit', $liftLog));

        $response->assertStatus(200);
        $response->assertSee('variable sets');
        $response->assertSee('cannot be edited here');
    }

    /** @test */
    public function edit_page_shows_form_for_uniform_sets()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
        ], $user);

        $response = $this->actingAs($user)->get(route('lift-logs.edit', $liftLog));

        $response->assertStatus(200);
        $response->assertDontSee('variable sets');
    }

    // ─── LiftLogService guard ───────────────────────────────────────────

    /** @test */
    public function generate_form_component_throws_for_non_uniform_sets()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 225, 'reps' => 1],
        ], $user);

        $service = app(\App\Services\MobileEntry\LiftLogService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('non-uniform sets');

        $service->generateFormComponent(
            $liftLog->exercise_id,
            $user->id,
            \Carbon\Carbon::parse($liftLog->logged_at),
            [],
            $liftLog
        );
    }

    /** @test */
    public function generate_form_component_works_for_uniform_sets()
    {
        $user = User::factory()->create();
        $liftLog = $this->createLiftLogWithSets([
            ['weight' => 185, 'reps' => 5],
            ['weight' => 185, 'reps' => 5],
        ], $user);

        $service = app(\App\Services\MobileEntry\LiftLogService::class);

        // Should not throw
        $result = $service->generateFormComponent(
            $liftLog->exercise_id,
            $user->id,
            \Carbon\Carbon::parse($liftLog->logged_at),
            [],
            $liftLog
        );

        $this->assertIsArray($result);
    }
}
