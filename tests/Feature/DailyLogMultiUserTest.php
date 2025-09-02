<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Factories\DailyLogFactory;
use Database\Factories\IngredientFactory;
use Database\Factories\MealFactory;

class DailyLogMultiUserTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;
    protected $userWithViewPermission;
    protected $userWithoutViewPermission;
    protected $userWithCreatePermission;
    protected $userWithoutCreatePermission;
    protected $userWithUpdatePermission;
    protected $userWithoutUpdatePermission;
    protected $userWithDeletePermission;
    protected $userWithoutDeletePermission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        // Users for multi-user tests
        $this->user1 = User::factory()->create();
        $this->user1->givePermissionTo([
            'daily-logs.view',
            'daily-logs.create',
            'daily-logs.update',
            'daily-logs.delete',
            'meals.view',
            'meals.create',
            'meals.update',
            'meals.delete',
            'ingredients.view',
            'ingredients.create',
            'ingredients.update',
            'ingredients.delete',
        ]);
        $this->user2 = User::factory()->create();

        // Users for granular permission tests
        $this->userWithViewPermission = User::factory()->create();
        $this->userWithViewPermission->givePermissionTo('daily-logs.view');

        $this->userWithoutViewPermission = User::factory()->create();

        $this->userWithCreatePermission = User::factory()->create();
        $this->userWithCreatePermission->givePermissionTo('daily-logs.create');

        $this->userWithoutCreatePermission = User::factory()->create();

        $this->userWithUpdatePermission = User::factory()->create();
        $this->userWithUpdatePermission->givePermissionTo('daily-logs.update');

        $this->userWithoutUpdatePermission = User::factory()->create();

        $this->userWithDeletePermission = User::factory()->create();
        $this->userWithDeletePermission->givePermissionTo('daily-logs.delete');

        $this->userWithoutDeletePermission = User::factory()->create();

        // Create some common data for testing
        $this->ingredient1 = IngredientFactory::new()->create(['user_id' => $this->user1->id, 'name' => 'Ingredient A for User 1']);
        $this->ingredient2 = IngredientFactory::new()->create(['user_id' => $this->user2->id, 'name' => 'Ingredient B for User 2']);
        $this->meal1 = MealFactory::new()->create(['user_id' => $this->user1->id]);
        $this->meal2 = MealFactory::new()->create(['user_id' => $this->user2->id]);
    }

    /** @test */
    public function authenticated_user_can_view_their_daily_logs()
    {
        $log1 = DailyLogFactory::new()->create([
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
        ]);
        $log2 = DailyLogFactory::new()->create([
            'user_id' => $this->user2->id,
            'ingredient_id' => $this->ingredient2->id,
        ]);

        $response = $this->actingAs($this->user1)->get(route('daily-logs.index', ['date' => $log1->logged_at->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee($log1->ingredient->name);
        $response->assertDontSee($log2->ingredient->name);
    }

    /** @test */
    public function authenticated_user_cannot_view_other_users_daily_logs()
    {
        $log1 = DailyLogFactory::new()->create([
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
        ]);
        $log2 = DailyLogFactory::new()->create([
            'user_id' => $this->user2->id,
            'ingredient_id' => $this->ingredient2->id,
        ]);

        $response = $this->actingAs($this->user1)->get(route('daily-logs.index', ['date' => $log2->logged_at->toDateString()]));

        $response->assertStatus(200);
        $response->assertDontSee($log2->ingredient->name);
    }

    /** @test */
    public function authenticated_user_can_create_daily_log()
    {
        $response = $this->actingAs($this->user1)->post(route('daily-logs.store'), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
            'logged_at' => '10:00',
            'date' => '2025-01-01',
            'notes' => 'Test note',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_their_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->user1)->put(route('daily-logs.update', $dailyLog->id), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('daily_logs', [
            'id' => $dailyLog->id,
            'user_id' => $this->user1->id,
            'quantity' => 200,
            'notes' => 'Updated note',
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_update_other_users_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->put(route('daily-logs.update', $dailyLog->id), [
            'ingredient_id' => $this->ingredient2->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function authenticated_user_can_delete_their_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->user1)->delete(route('daily-logs.destroy', $dailyLog->id));

        $response->assertRedirect(route('daily-logs.index', ['date' => $dailyLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog->id]);
    }

    /** @test */
    public function authenticated_user_cannot_delete_other_users_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->delete(route('daily-logs.destroy', $dailyLog->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog->id]);
    }

    /** @test */
    public function authenticated_user_can_bulk_delete_their_daily_logs()
    {
        $dailyLog1 = DailyLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $dailyLog2 = DailyLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $dailyLog3 = DailyLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->post(route('daily-logs.destroy-selected'), [
            'daily_log_ids' => [$dailyLog1->id, $dailyLog2->id],
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => $dailyLog1->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog1->id]);
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog2->id]);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog3->id]);
    }

    /** @test */
    public function authenticated_user_cannot_bulk_delete_other_users_daily_logs()
    {
        $dailyLog1 = DailyLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $dailyLog2 = DailyLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->post(route('daily-logs.destroy-selected'), [
            'daily_log_ids' => [$dailyLog1->id, $dailyLog2->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog1->id]);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog2->id]);
    }

    /** @test */
    public function authenticated_user_can_add_meal_to_log()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->user1->id]);
        $meal->ingredients()->attach($this->ingredient1->id, ['quantity' => 50]);

        $response = $this->actingAs($this->user1)->post(route('daily-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-02']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 50,
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_add_other_users_meal_to_log()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->user2->id]);
        $meal->ingredients()->attach($this->ingredient2->id, ['quantity' => 50]);

        $response = $this->actingAs($this->user1)->post(route('daily-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('daily_logs', ['user_id' => $this->user1->id]);
    }

    /** @test */
    public function authenticated_user_can_import_daily_logs()
    {
        $tsvData = "2025-01-03\t10:00\t" . $this->ingredient1->name . "\tNote\t100";

        $response = $this->actingAs($this->user1)->post(route('daily-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-01-03',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-03']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function user_with_daily_logs_view_permission_can_view_daily_logs()
    {
        $ingredient = IngredientFactory::new()->create(['user_id' => $this->userWithViewPermission->id]);
        $log = DailyLogFactory::new()->create(['user_id' => $this->userWithViewPermission->id, 'ingredient_id' => $ingredient->id]);
        $response = $this->actingAs($this->userWithViewPermission)->get(route('daily-logs.index', ['date' => $log->logged_at->toDateString()]));
        $response->assertStatus(200);
        $response->assertSee($log->ingredient->name);
    }

    /** @test */
    public function user_without_daily_logs_view_permission_cannot_view_daily_logs()
    {
        $log = DailyLogFactory::new()->create(['user_id' => $this->userWithoutViewPermission->id, 'ingredient_id' => $this->ingredient1->id]);
        $response = $this->actingAs($this->userWithoutViewPermission)->get(route('daily-logs.index', ['date' => $log->logged_at->toDateString()]));
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_daily_logs_create_permission_can_create_daily_log()
    {
        $response = $this->actingAs($this->userWithCreatePermission)->post(route('daily-logs.store'), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
            'logged_at' => '10:00',
            'date' => '2025-01-01',
            'notes' => 'Test note',
        ]);
        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->userWithCreatePermission->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function user_without_daily_logs_create_permission_cannot_create_daily_log()
    {
        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('daily-logs.store'), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
            'logged_at' => '10:00',
            'date' => '2025-01-01',
            'notes' => 'Test note',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('daily_logs', [
            'user_id' => $this->userWithoutCreatePermission->id,
        ]);
    }

    /** @test */
    public function user_with_daily_logs_create_permission_can_add_meal_to_log()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithCreatePermission->id]);
        $meal->ingredients()->attach($this->ingredient1->id, ['quantity' => 50]);

        $response = $this->actingAs($this->userWithCreatePermission)->post(route('daily-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-02']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->userWithCreatePermission->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 50,
        ]);
    }

    /** @test */
    public function user_without_daily_logs_create_permission_cannot_add_meal_to_log()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithoutCreatePermission->id]);
        $meal->ingredients()->attach($this->ingredient1->id, ['quantity' => 50]);

        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('daily-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('daily_logs', ['user_id' => $this->userWithoutCreatePermission->id]);
    }

    /** @test */
    public function user_with_daily_logs_create_permission_can_import_daily_logs()
    {
        $tsvData = "2025-01-03\t10:00\t" . $this->ingredient1->name . "\tNote\t100";

        $response = $this->actingAs($this->userWithCreatePermission)->post(route('daily-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-01-03',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-03']));
        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->userWithCreatePermission->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function user_without_daily_logs_create_permission_cannot_import_daily_logs()
    {
        $tsvData = "2025-01-03\t10:00\t" . $this->ingredient1->name . "\tNote\t100";

        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('daily-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-01-03',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('daily_logs', ['user_id' => $this->userWithoutCreatePermission->id]);
    }

    /** @test */
    public function user_with_daily_logs_update_permission_can_update_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithUpdatePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithUpdatePermission)->put(route('daily-logs.update', $dailyLog->id), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('daily_logs', [
            'id' => $dailyLog->id,
            'user_id' => $this->userWithUpdatePermission->id,
            'quantity' => 200,
            'notes' => 'Updated note',
        ]);
    }

    /** @test */
    public function user_without_daily_logs_update_permission_cannot_update_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithoutUpdatePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithoutUpdatePermission)->put(route('daily-logs.update', $dailyLog->id), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('daily_logs', [
            'id' => $dailyLog->id,
            'quantity' => 200,
        ]);
    }

    /** @test */
    public function user_with_daily_logs_delete_permission_can_delete_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithDeletePermission)->delete(route('daily-logs.destroy', $dailyLog->id));

        $response->assertRedirect(route('daily-logs.index', ['date' => $dailyLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog->id]);
    }

    /** @test */
    public function user_without_daily_logs_delete_permission_cannot_delete_daily_log()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithoutDeletePermission)->delete(route('daily-logs.destroy', $dailyLog->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog->id]);
    }

    /** @test */
    public function user_with_daily_logs_delete_permission_can_bulk_delete_daily_logs()
    {
        $dailyLog1 = DailyLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);
        $dailyLog2 = DailyLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithDeletePermission)->post(route('daily-logs.destroy-selected'), [
            'daily_log_ids' => [$dailyLog1->id, $dailyLog2->id],
        ]);

        $response->assertRedirect(route('daily-logs.index', ['date' => $dailyLog1->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog1->id]);
        $this->assertDatabaseMissing('daily_logs', ['id' => $dailyLog2->id]);
    }

    /** @test */
    public function user_without_daily_logs_delete_permission_cannot_bulk_delete_daily_logs()
    {
        $dailyLog1 = DailyLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);
        $dailyLog2 = DailyLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->userWithoutDeletePermission)->post(route('daily-logs.destroy-selected'), [
            'daily_log_ids' => [$dailyLog1->id, $dailyLog2->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog1->id]);
        $this->assertDatabaseHas('daily_logs', ['id' => $dailyLog2->id]);
    }
}
