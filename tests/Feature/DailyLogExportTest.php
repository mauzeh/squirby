<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Unit;
use Carbon\Carbon;

class DailyLogExportTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $user;
    protected $ingredient;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->create();
        $this->ingredient = Ingredient::factory()->create(['user_id' => $this->user->id, 'base_unit_id' => $this->unit->id]);
    }

    /** @test */
    public function authenticated_user_can_export_daily_logs_by_date_range()
    {
        $this->actingAs($this->user);

        // Create some daily logs within and outside the date range
        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:00:00')]);
        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:00:00')]);
        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-03 12:00:00')]);
        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-04 13:00:00')]); // Outside range

        $response = $this->post(route('export'), [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-03',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        //$response->assertHeader('Content-Disposition', '/^attachment; filename=daily_log_2025-01-01_to_2025-01-03_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/');

        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        $this->assertCount(4, $lines); // Header + 3 logs
        $this->assertStringContainsString('01/01/2025', $lines[1]);
        $this->assertStringContainsString('01/02/2025', $lines[2]);
        $this->assertStringContainsString('01/03/2025', $lines[3]);
        $this->assertStringNotContainsString('01/04/2025', $content);
    }

    /** @test */
    public function authenticated_user_can_export_daily_logs_for_single_day_range()
    {
        $this->actingAs($this->user);

        $logDate = Carbon::parse('2025-09-04 10:00:00');
        DailyLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'logged_at' => $logDate
        ]);

        $response = $this->post(route('export'), [
            'start_date' => '2025-09-04',
            'end_date' => '2025-09-04',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        $this->assertCount(2, $lines); // Header + 1 log
        $this->assertStringContainsString('09/04/2025', $lines[1]);
    }

    /** @test */
    public function authenticated_user_can_export_all_daily_logs()
    {
        $this->actingAs($this->user);

        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:00:00')]);
        DailyLog::factory()->create(['user_id' => $this->user->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:00:00')]);

        $response = $this->post(route('export-all'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        //$response->assertHeader('Content-Disposition', '/^attachment; filename=daily_log_all_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/');

        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        $this->assertCount(3, $lines); // Header + 2 logs
        $this->assertStringContainsString('01/01/2025', $lines[1]);
        $this->assertStringContainsString('01/02/2025', $lines[2]);
    }

    /** @test */
    public function unauthenticated_user_cannot_export_daily_logs()
    {
        $response = $this->post(route('export'), [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-03',
        ]);

        $response->assertRedirect(route('login'));

        $response = $this->post(route('export-all'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_cannot_export_other_users_daily_logs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);

        // Create logs for user1
        DailyLog::factory()->create(['user_id' => $user1->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:00:00')]);
        DailyLog::factory()->create(['user_id' => $user1->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:00:00')]);

        // Create logs for user2
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user2->id, 'base_unit_id' => $this->unit->id]);
        DailyLog::factory()->create(['user_id' => $user2->id, 'ingredient_id' => $ingredient2->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:30:00')]);
        DailyLog::factory()->create(['user_id' => $user2->id, 'ingredient_id' => $ingredient2->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:30:00')]);

        $response = $this->post(route('export-all'));

        $response->assertOk();
        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        // Assert that user1's logs are present
        $this->assertStringContainsString('01/01/2025', $lines[1]);
        $this->assertStringContainsString('01/02/2025', $lines[2]);
        $this->assertCount(3, $lines); // Header + 2 logs from user1

        // Assert that user2's logs are NOT present
        $this->assertStringNotContainsString($ingredient2->name, $content);
    }

    /** @test */
    public function authenticated_user_cannot_export_other_users_daily_logs_for_date_range()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);

        // Create logs for user1 within the date range
        DailyLog::factory()->create(['user_id' => $user1->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:00:00')]);
        DailyLog::factory()->create(['user_id' => $user1->id, 'ingredient_id' => $this->ingredient->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:00:00')]);

        // Create logs for user2 within the date range
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user2->id, 'base_unit_id' => $this->unit->id]);
        DailyLog::factory()->create(['user_id' => $user2->id, 'ingredient_id' => $ingredient2->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-01 10:30:00')]);
        DailyLog::factory()->create(['user_id' => $user2->id, 'ingredient_id' => $ingredient2->id, 'unit_id' => $this->unit->id, 'logged_at' => Carbon::parse('2025-01-02 11:30:00')]);

        $response = $this->post(route('export'), [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-02',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        // Assert that user1's logs are present
        $this->assertStringContainsString('01/01/2025', $lines[1]);
        $this->assertStringContainsString('01/02/2025', $lines[2]);
        $this->assertCount(3, $lines); // Header + 2 logs from user1

        // Assert that user2's logs are NOT present
        $this->assertStringNotContainsString($ingredient2->name, $content);
    }

}