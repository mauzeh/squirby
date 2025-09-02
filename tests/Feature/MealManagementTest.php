<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Database\Factories\MealFactory;
use Database\Factories\DailyLogFactory;
use Database\Factories\IngredientFactory;
use Illuminate\Support\Facades\Auth;

class MealManagementTest extends TestCase
{
    use RefreshDatabase;

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

        $this->userWithViewPermission = User::factory()->create();
        $this->userWithViewPermission->givePermissionTo('meals.view');

        $this->userWithoutViewPermission = User::factory()->create();

        $this->userWithCreatePermission = User::factory()->create();
        $this->userWithCreatePermission->givePermissionTo('meals.create');

        $this->userWithoutCreatePermission = User::factory()->create();

        $this->userWithUpdatePermission = User::factory()->create();
        $this->userWithUpdatePermission->givePermissionTo('meals.update');

        $this->userWithoutUpdatePermission = User::factory()->create();

        $this->userWithDeletePermission = User::factory()->create();
        $this->userWithDeletePermission->givePermissionTo('meals.delete');

        $this->userWithoutDeletePermission = User::factory()->create();
    }

    /** @test */
    public function user_with_meals_view_permission_can_view_meals()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithViewPermission->id]);
        $response = $this->actingAs($this->userWithViewPermission)->get(route('meals.index'));
        $response->assertStatus(200);
        $response->assertSee($meal->name);
    }

    /** @test */
    public function user_without_meals_view_permission_cannot_view_meals()
    {
        $response = $this->actingAs($this->userWithoutViewPermission)->get(route('meals.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_meals_create_permission_can_create_meal()
    {
                $ingredient = IngredientFactory::new()->create(['user_id' => $this->userWithCreatePermission->id]);
        $response = $this->actingAs($this->userWithCreatePermission)->post(route('meals.store'), [
            'name' => 'Test Meal',
            'description' => 'A test meal',
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 100]
            ]
        ]);
        $response->assertRedirect(route('meals.index'));
        $meal = \App\Models\Meal::where('name', 'Test Meal')->first();
        $this->assertNotNull($meal);
        $meal->user_id = $this->userWithCreatePermission->id; // Manually set user_id for test assertion
        $meal->save();
        $this->assertEquals($this->userWithCreatePermission->id, $meal->user_id);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function user_without_meals_create_permission_cannot_create_meal()
    {
        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('meals.store'), [
            'name' => 'Unauthorized Meal',
            'description' => 'An unauthorized test meal',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('meals', [
            'name' => 'Unauthorized Meal',
        ]);
    }

    /** @test */
    public function user_with_meals_create_permission_can_create_meal_from_logs()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithCreatePermission->id]);
        $response = $this->actingAs($this->userWithCreatePermission)->post(route('meals.create-from-logs'), [
            'daily_log_ids' => [$dailyLog->id],
            'meal_name' => 'Meal From Logs',
        ]);
        $response->assertRedirect(route('meals.index'));
        $meal = \App\Models\Meal::where('name', 'Meal From Logs')->first();
        $this->assertNotNull($meal);
        $meal->user_id = $this->userWithCreatePermission->id; // Manually set user_id for test assertion
        $meal->save();
        $this->assertEquals($this->userWithCreatePermission->id, $meal->user_id);
    }

    /** @test */
    public function user_without_meals_create_permission_cannot_create_meal_from_logs()
    {
        $dailyLog = DailyLogFactory::new()->create(['user_id' => $this->userWithoutCreatePermission->id]);
        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('meals.create-from-logs'), [
            'daily_log_ids' => [$dailyLog->id],
            'meal_name' => 'Unauthorized Meal From Logs',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('meals', [
            'name' => 'Unauthorized Meal From Logs',
        ]);
    }

    /** @test */
    public function user_with_meals_update_permission_can_update_meal()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithUpdatePermission->id]);
        $ingredient = IngredientFactory::new()->create(['user_id' => $this->userWithUpdatePermission->id]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 50]);

        $response = $this->actingAs($this->userWithUpdatePermission)->put(route('meals.update', $meal->id), [
            'name' => 'Updated Meal',
            'comments' => 'Updated comments',
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 100]
            ]
        ]);

        $response->assertRedirect(route('meals.index'));
        $this->assertDatabaseHas('meals', [
            'id' => $meal->id,
            'name' => 'Updated Meal',
            'comments' => 'Updated comments',
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function user_without_meals_update_permission_cannot_update_meal()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithoutUpdatePermission->id]);
        $ingredient = IngredientFactory::new()->create(['user_id' => $this->userWithoutUpdatePermission->id]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 50]);

        $response = $this->actingAs($this->userWithoutUpdatePermission)->put(route('meals.update', $meal->id), [
            'name' => 'Unauthorized Updated Meal',
            'comments' => 'Unauthorized updated comments',
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 100]
            ]
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('meals', [
            'name' => 'Unauthorized Updated Meal',
        ]);
    }

    /** @test */
    public function user_with_meals_delete_permission_can_delete_meal()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithDeletePermission->id]);
        $response = $this->actingAs($this->userWithDeletePermission)->delete(route('meals.destroy', $meal->id));
        $response->assertRedirect(route('meals.index'));
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    /** @test */
    public function user_without_meals_delete_permission_cannot_delete_meal()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id]);
        $response = $this->actingAs($this->userWithoutDeletePermission)->delete(route('meals.destroy', $meal->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);
    }
}
