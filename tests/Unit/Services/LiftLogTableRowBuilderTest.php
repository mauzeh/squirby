<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\LiftLogTableRowBuilder;
use App\Services\ExerciseAliasService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class LiftLogTableRowBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected LiftLogTableRowBuilder $builder;
    protected $aliasService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->aliasService = Mockery::mock(ExerciseAliasService::class);
        $this->builder = new LiftLogTableRowBuilder($this->aliasService);
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
        $this->assertEquals('Test comment', $rows[0]['line2']);
        $this->assertTrue($rows[0]['compact']);
        $this->assertTrue($rows[0]['wrapActions']);
        $this->assertTrue($rows[0]['wrapText']);
    }

    /** @test */
    public function it_includes_date_badge_when_configured()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()
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
        $this->assertEquals('Today', $rows[0]['badges'][0]['text']);
        $this->assertEquals('success', $rows[0]['badges'][0]['colorClass']);
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
    public function it_includes_encouraging_messages_when_configured()
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
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'includeEncouragingMessage' => true
        ]);

        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have 2 messages: comments + encouraging
        $this->assertCount(2, $subItem['messages']);
        
        // First message should be comments
        $this->assertEquals('neutral', $subItem['messages'][0]['type']);
        $this->assertEquals('Your notes:', $subItem['messages'][0]['prefix']);
        $this->assertEquals('Felt strong today', $subItem['messages'][0]['text']);
        
        // Second message should be encouraging
        $this->assertEquals('success', $subItem['messages'][1]['type']);
        $this->assertContains($subItem['messages'][1]['prefix'], [
            'Great work!', 'Nice job!', 'Well done!', 'Awesome!', 'Excellent!',
            'Fantastic!', 'Outstanding!', 'Impressive!', 'Strong work!', 'Keep it up!'
        ]);
        $this->assertStringContainsString('You completed', $subItem['messages'][1]['text']);
    }

    /** @test */
    public function it_shows_only_encouraging_message_when_no_comments()
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
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'includeEncouragingMessage' => true
        ]);

        $this->assertNotEmpty($rows[0]['subItems']);
        $subItem = $rows[0]['subItems'][0];
        
        // Should have only 1 message: encouraging (no comments)
        $this->assertCount(1, $subItem['messages']);
        $this->assertEquals('success', $subItem['messages'][0]['type']);
    }

    /** @test */
    public function it_removes_comments_from_line2_when_showing_encouraging_messages()
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
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'includeEncouragingMessage' => true
        ]);

        $this->assertNull($rows[0]['line2']);
    }

    /** @test */
    public function it_keeps_comments_in_line2_when_not_showing_encouraging_messages()
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
        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'includeEncouragingMessage' => false
        ]);

        $this->assertEquals('Test comment', $rows[0]['line2']);
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
    public function it_generates_encouraging_prefix_from_valid_list()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $exercise = Exercise::factory()->create(['exercise_type' => 'weighted']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => null // No comments so encouraging message is first
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn($exercise->title);

        $rows = $this->builder->buildRows(collect([$liftLog]), [
            'includeEncouragingMessage' => true
        ]);

        $prefix = $rows[0]['subItems'][0]['messages'][0]['prefix'];
        
        // Should be one of the valid prefixes
        $validPrefixes = [
            'Great work!', 'Nice job!', 'Well done!', 'Awesome!', 'Excellent!',
            'Fantastic!', 'Outstanding!', 'Impressive!', 'Strong work!', 'Keep it up!'
        ];
        
        $this->assertContains($prefix, $validPrefixes);
    }

    /** @test */
    public function it_includes_weight_badge_for_weighted_exercises()
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
        $rows = $this->builder->buildRows(collect([$liftLog]));

        $badges = $rows[0]['badges'];
        $weightBadge = collect($badges)->firstWhere('colorClass', 'dark');
        
        $this->assertNotNull($weightBadge);
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
}
