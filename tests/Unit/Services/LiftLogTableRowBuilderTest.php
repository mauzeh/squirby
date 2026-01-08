<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\LiftLogTableRowBuilder;
use App\Services\ExerciseAliasService;
use App\Services\PRDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class LiftLogTableRowBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected LiftLogTableRowBuilder $builder;
    protected $aliasService;
    protected $prDetectionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->aliasService = Mockery::mock(ExerciseAliasService::class);
        $this->prDetectionService = Mockery::mock(PRDetectionService::class);
        
        // Default mock - return empty array (no PRs) unless specifically overridden
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->andReturn([])
            ->byDefault();
            
        $this->builder = new LiftLogTableRowBuilder($this->aliasService, $this->prDetectionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_builds_rows_with_default_configuration()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test comment'
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        $this->assertCount(1, $rows);
        $this->assertEquals($liftLog->id, $rows[0]['id']);
        $this->assertEquals($exercise->title, $rows[0]['line1']);
        $this->assertNull($rows[0]['line2']); // Comments never in line2
        $this->assertTrue($rows[0]['compact']);
        $this->assertTrue($rows[0]['wrapActions']);
        $this->assertTrue($rows[0]['wrapText']);
        
        // Comments should always be in subitem now
        $this->assertNotEmpty($rows[0]['subItems']);
        $this->assertEquals('neutral', $rows[0]['subItems'][0]['messages'][0]['type']);
        $this->assertEquals('Your notes:', $rows[0]['subItems'][0]['messages'][0]['prefix']);
        $this->assertEquals('Test comment', $rows[0]['subItems'][0]['messages'][0]['text']);
    }

    /**
     * @test
     * @dataProvider dateBadgeProvider
     */
    public function it_displays_correct_date_badge_based_on_logged_date($dateCallback, $expectedText, $expectedColor)
    {
        Carbon::setTestNow(Carbon::create(2024, 5, 26, 12, 0, 0));

        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $dateCallback()
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showDateBadge' => true
        ]);

        $this->assertNotEmpty($rows[0]['badges']);
        $dateBadge = $rows[0]['badges'][0];
        $this->assertEquals($expectedText, $dateBadge['text']);
        $this->assertEquals($expectedColor, $dateBadge['colorClass']);

        Carbon::setTestNow(); // Clear the mocked time
    }

    public static function dateBadgeProvider()
    {
        return [
            'Today' => [fn() => now(), 'Today', 'success'],
            'Yesterday' => [fn() => now()->subDay(), 'Yesterday', 'warning'],
            '2 days ago' => [fn() => now()->subDays(2), '2 days ago', 'neutral'],
            'Almost 2 days (now should be 2 days ago)' => [fn() => now()->subHours(47), '2 days ago', 'neutral'],
            'Within 7 days' => [fn() => now()->subDays(5), '5 days ago', 'neutral'],
            'More than 7 days ago' => [fn() => now()->subDays(10), '5/16/24', 'neutral'],
        ];
    }

    /** @test */
    public function it_excludes_date_badge_when_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showDateBadge' => false
        ]);

        // Should still have badges (reps/sets, weight) but not date badge
        $this->assertNotEmpty($rows[0]['badges']);
        $this->assertNotEquals('Today', $rows[0]['badges'][0]['text']);
    }

    /** @test */
    public function it_includes_view_logs_action_when_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showViewLogsAction' => true
        ]);

        $actions = $rows[0]['actions'];
        $viewLogsAction = collect($actions)->firstWhere('icon', 'fa-chart-line');
        
        $this->assertNotNull($viewLogsAction);
        $this->assertEquals('link', $viewLogsAction['type']);
        $this->assertEquals('btn-info-circle', $viewLogsAction['cssClass']);
    }

    /** @test */
    public function it_includes_delete_action_when_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showDeleteAction' => true,
            'redirectContext' => 'mobile-entry-lifts',
            'selectedDate' => '2024-01-15'
        ]);

        $actions = $rows[0]['actions'];
        $deleteAction = collect($actions)->firstWhere('icon', 'fa-trash');
        
        $this->assertNotNull($deleteAction);
        $this->assertEquals('form', $deleteAction['type']);
        $this->assertEquals('DELETE', $deleteAction['method']);
        $this->assertEquals('btn-transparent', $deleteAction['cssClass']);
        $this->assertTrue($deleteAction['requiresConfirm']);
        $this->assertEquals('mobile-entry-lifts', $deleteAction['params']['redirect_to']);
        $this->assertEquals('2024-01-15', $deleteAction['params']['date']);
    }

    /** @test */
    public function it_excludes_delete_action_when_not_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showDeleteAction' => false
        ]);

        $actions = $rows[0]['actions'];
        $deleteAction = collect($actions)->firstWhere('icon', 'fa-trash');
        
        $this->assertNull($deleteAction);
    }

    /** @test */
    public function it_shows_comments_in_subitem_when_present()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Felt strong today'
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have 1 message: comments
        $this->assertCount(1, $subItem['messages']);
        
        // Message should be comments
        $this->assertEquals('neutral', $subItem['messages'][0]['type']);
        $this->assertEquals('Your notes:', $subItem['messages'][0]['prefix']);
        $this->assertEquals('Felt strong today', $subItem['messages'][0]['text']);
    }

    /** @test */
    public function it_shows_na_in_subitem_when_no_comments()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => null
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        // Should always have subitems now (even when no comments)
        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have 1 message: N/A for comments
        $this->assertCount(1, $subItem['messages']);
        
        // Message should show N/A
        $this->assertEquals('neutral', $subItem['messages'][0]['type']);
        $this->assertEquals('Your notes:', $subItem['messages'][0]['prefix']);
        $this->assertEquals('N/A', $subItem['messages'][0]['text']);
    }

    /** @test */
    public function it_shows_na_for_empty_string_comments()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => ''
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        // Should always have subitems now (even when empty string)
        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have 1 message: N/A for empty comments
        $this->assertCount(1, $subItem['messages']);
        
        // Message should show N/A
        $this->assertEquals('neutral', $subItem['messages'][0]['type']);
        $this->assertEquals('Your notes:', $subItem['messages'][0]['prefix']);
        $this->assertEquals('N/A', $subItem['messages'][0]['text']);
    }

    /** @test */
    public function it_shows_na_for_whitespace_only_comments()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => '   '
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        // Should always have subitems now (even when whitespace only)
        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have 1 message: N/A for whitespace-only comments
        $this->assertCount(1, $subItem['messages']);
        
        // Message should show N/A
        $this->assertEquals('neutral', $subItem['messages'][0]['type']);
        $this->assertEquals('Your notes:', $subItem['messages'][0]['prefix']);
        $this->assertEquals('N/A', $subItem['messages'][0]['text']);
    }

    /** @test */
    public function it_never_shows_comments_in_line2()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test comment'
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]));

        $this->assertNull($rows[0]['line2']);
    }

    /** @test */
    public function it_respects_wrap_actions_configuration()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'wrapActions' => false
        ]);

        $this->assertFalse($rows[0]['wrapActions']);
    }

    /** @test */
    public function it_includes_checkbox_when_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showCheckbox' => true
        ]);

        $this->assertTrue($rows[0]['checkbox']);
    }

    /** @test */
    public function it_styles_badges_with_correct_colors()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 5
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showDateBadge' => false,
        ]);

        $badges = $rows[0]['badges'];

        // Reps/sets badge should be 'info'
        $repsSetsBadge = collect($badges)->firstWhere('text', '1 x 5');
        $this->assertNotNull($repsSetsBadge, "Reps/sets badge with text '1 x 5' not found.");
        $this->assertEquals('info', $repsSetsBadge['colorClass']);

        // Weight badge should be 'success'
        $weightBadge = collect($badges)->firstWhere('text', '135 lbs');
        $this->assertNotNull($weightBadge, "Weight badge with text '135 lbs' not found.");
        $this->assertEquals('success', $weightBadge['colorClass']);
        $this->assertTrue($weightBadge['emphasized']);
    }

    /** @test */
    public function it_handles_multiple_lift_logs()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $exercise2 = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id
        ]);
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id
        ]);
        
        LiftSet::factory()->create(['lift_log_id' => $liftLog1->id]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog2->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->twice()
            ->andReturn($exercise1->title, $exercise2->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog1, $liftLog2]));

        $this->assertCount(2, $rows);
        $this->assertEquals($liftLog1->id, $rows[0]['id']);
        $this->assertEquals($liftLog2->id, $rows[1]['id']);
    }

    /** @test */
    public function it_identifies_pr_logs_correctly()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // Create lift logs with different weights for 1 rep
        $log1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(3)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log1->id,
            'weight' => 250,
            'reps' => 1
        ]);

        $log2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log2->id,
            'weight' => 275, // PR for 1 rep
            'reps' => 1
        ]);

        $log3 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log3->id,
            'weight' => 260,
            'reps' => 1
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->times(3)
            ->andReturn($exercise->title);

        // Mock PR detection to return log1 and log2 as PRs
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([$log1->id, $log2->id]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log1, $log2, $log3]));

        // log1 and log2 should be marked as PRs (chronological PRs)
        // log1 was a PR when it happened (first lift)
        // log2 was a PR when it happened (beat log1)
        // log3 was NOT a PR (didn't beat log2)
        $this->assertEquals('row-pr', $rows[0]['cssClass']); // log1 - PR at the time
        $this->assertEquals('row-pr', $rows[1]['cssClass']); // log2 - PR at the time
        $this->assertNull($rows[2]['cssClass']); // log3 - not PR

        // Both log1 and log2 should have PR badges
        $prBadge1 = collect($rows[0]['badges'])->firstWhere('colorClass', 'pr');
        $this->assertNotNull($prBadge1);
        $this->assertEquals('🏆 PR', $prBadge1['text']);
        
        $prBadge2 = collect($rows[1]['badges'])->firstWhere('colorClass', 'pr');
        $this->assertNotNull($prBadge2);
        $this->assertEquals('🏆 PR', $prBadge2['text']);
    }

    /** @test */
    public function it_identifies_pr_based_on_highest_estimated_1rm()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // 275 lbs × 1 rep = 275 lbs estimated 1RM (PR at the time - first lift)
        $log1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(3)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log1->id,
            'weight' => 275,
            'reps' => 1
        ]);

        // 260 lbs × 2 reps = 277.3 lbs estimated 1RM (PR at the time - beats log1)
        $log2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log2->id,
            'weight' => 260,
            'reps' => 2
        ]);

        // 255 lbs × 3 reps = 280.5 lbs estimated 1RM (PR at the time - beats log2)
        $log3 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log3->id,
            'weight' => 255,
            'reps' => 3
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->times(3)
            ->andReturn($exercise->title);

        // Mock PR detection to return all three as PRs
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([$log1->id, $log2->id, $log3->id]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log1, $log2, $log3]));

        // All three should be marked as PRs (each beat the previous best at the time)
        $this->assertEquals('row-pr', $rows[0]['cssClass']); // log1 - PR (first lift)
        $this->assertEquals('row-pr', $rows[1]['cssClass']); // log2 - PR (beat log1)
        $this->assertEquals('row-pr', $rows[2]['cssClass']); // log3 - PR (beat log2)
    }

    /** @test */
    public function it_does_not_mark_non_regular_exercises_as_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        
        $log = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log->id,
            'weight' => 0,
            'reps' => 10
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        // Mock PR detection to return empty array (no PRs for bodyweight exercises)
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log]));

        // Should not be marked as PR
        $this->assertNull($rows[0]['cssClass']);
        
        // Should not have PR badge
        $prBadge = collect($rows[0]['badges'])->firstWhere('colorClass', 'pr');
        $this->assertNull($prBadge);
    }

    /** @test */
    public function it_considers_all_rep_ranges_for_pr_calculation()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // 300 lbs × 5 reps = 349.95 lbs estimated 1RM (PR at the time - first lift)
        $log1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log1->id,
            'weight' => 300,
            'reps' => 5
        ]);

        // 250 lbs × 1 rep = 250 lbs estimated 1RM
        // This IS a PR because it's the heaviest 1-rep lift (rep-specific PR for 1-5 reps)
        $log2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log2->id,
            'weight' => 250,
            'reps' => 1
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->times(2)
            ->andReturn($exercise->title);

        // Mock PR detection to return both as PRs
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([$log1->id, $log2->id]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log1, $log2]));

        // log1 should be PR (first lift, and highest estimated 1RM)
        $this->assertEquals('row-pr', $rows[0]['cssClass']);
        
        // log2 should also be PR (rep-specific PR - heaviest 1-rep lift)
        $this->assertEquals('row-pr', $rows[1]['cssClass']);
    }

    /** @test */
    public function it_handles_tied_prs_correctly()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // First PR
        $log1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log1->id,
            'weight' => 275,
            'reps' => 1
        ]);

        // Tied - not a PR (doesn't beat previous)
        $log2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1)
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log2->id,
            'weight' => 275, // Same weight
            'reps' => 1
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->times(2)
            ->andReturn($exercise->title);

        // Mock PR detection to return only log1 as PR (ties don't count)
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([$log1->id]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log1, $log2]));

        // Only log1 should be marked as PR (first lift)
        // log2 is not a PR because it only ties, doesn't beat
        $this->assertEquals('row-pr', $rows[0]['cssClass']);
        $this->assertNull($rows[1]['cssClass']);
    }

    /** @test */
    public function it_handles_log_with_multiple_sets_where_one_is_pr()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        $log = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        
        // Multiple sets, one is a PR
        LiftSet::factory()->create([
            'lift_log_id' => $log->id,
            'weight' => 250,
            'reps' => 1
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log->id,
            'weight' => 275, // PR
            'reps' => 1
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log->id,
            'weight' => 260,
            'reps' => 1
        ]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        // Mock PR detection to return this log as PR
        $this->prDetectionService->shouldReceive('calculatePRLogIds')
            ->once()
            ->andReturn([$log->id]);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$log]));

        // Should be marked as PR because one set is a PR
        $this->assertEquals('row-pr', $rows[0]['cssClass']);
    }

    /** @test */
    public function it_includes_from_parameter_in_view_logs_url_when_redirect_context_is_mobile_entry_lifts()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showViewLogsAction' => true,
            'redirectContext' => 'mobile-entry-lifts'
        ]);

        $actions = $rows[0]['actions'];
        $viewLogsAction = collect($actions)->firstWhere('icon', 'fa-chart-line');
        
        $this->assertNotNull($viewLogsAction);
        $this->assertStringContainsString('from=mobile-entry-lifts', $viewLogsAction['url']);
    }

    /** @test */
    public function it_includes_date_parameter_in_view_logs_url_when_selected_date_provided()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showViewLogsAction' => true,
            'redirectContext' => 'mobile-entry-lifts',
            'selectedDate' => '2025-11-26'
        ]);

        $actions = $rows[0]['actions'];
        $viewLogsAction = collect($actions)->firstWhere('icon', 'fa-chart-line');
        
        $this->assertNotNull($viewLogsAction);
        $this->assertStringContainsString('from=mobile-entry-lifts', $viewLogsAction['url']);
        $this->assertStringContainsString('date=2025-11-26', $viewLogsAction['url']);
    }

    /** @test */
    public function it_does_not_include_from_parameter_when_redirect_context_is_not_mobile_entry_lifts()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $this->actingAs($user);
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'showViewLogsAction' => true,
            'redirectContext' => 'exercises-logs'
        ]);

        $actions = $rows[0]['actions'];
        $viewLogsAction = collect($actions)->firstWhere('icon', 'fa-chart-line');
        
        $this->assertNotNull($viewLogsAction);
        $this->assertStringNotContainsString('from=', $viewLogsAction['url']);
        $this->assertStringNotContainsString('date=', $viewLogsAction['url']);
    }
}
