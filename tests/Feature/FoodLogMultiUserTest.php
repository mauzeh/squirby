<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Factories\FoodLogFactory;
use Database\Factories\IngredientFactory;
use Database\Factories\MealFactory;

class FoodLogMultiUserTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

        // Create some common data for testing
        $this->ingredient1 = IngredientFactory::new()->create(['user_id' => $this->user1->id, 'name' => 'Ingredient User 1: Oatmeal']);
        $this->ingredient2 = IngredientFactory::new()->create(['user_id' => $this->user2->id, 'name' => 'Ingredient User 2: Brown Rice']);
        $this->meal1 = MealFactory::new()->create(['user_id' => $this->user1->id]);
        $this->meal2 = MealFactory::new()->create(['user_id' => $this->user2->id]);
    }

    /** @test */
    public function authenticated_user_can_view_their_food_logs()
    {
        FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id, 'logged_at' => Carbon::today()]);
        FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id, 'logged_at' => Carbon::today()]);

        $response = $this->actingAs($this->user1)->get(route('food-logs.index'));

        //dd($this->ingredient1->name, $this->ingredient2->name);

        $response->assertStatus(200);
        $response->assertSee($this->ingredient1->name);
        $response->assertDontSee($this->ingredient2->name);
    }

    /** @test */
    public function authenticated_user_cannot_view_other_users_food_logs()
    {
        FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $this->actingAs($this->user1);

        $response = $this->actingAs($this->user1)->get(route('food-logs.index'));

        $response->assertStatus(200);
        $response->assertDontSee($this->ingredient2->name);
    }

    /** @test */
    public function authenticated_user_can_create_food_log()
    {
        $response = $this->actingAs($this->user1)->post(route('food-logs.store'), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
            'logged_at' => '10:00',
            'date' => '2025-01-01',
            'notes' => 'Test note',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user1->id,
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_their_food_log()
    {
        $foodLog = FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->user1)->put(route('food-logs.update', $foodLog->id), [
            'ingredient_id' => $this->ingredient1->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-01-01']));
        $this->assertDatabaseHas('food_logs', [
            'id' => $foodLog->id,
            'user_id' => $this->user1->id,
            'quantity' => 200,
            'notes' => 'Updated note',
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_update_other_users_food_log()
    {
        $foodLog = FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->put(route('food-logs.update', $foodLog->id), [
            'ingredient_id' => $this->ingredient2->id,
            'quantity' => 200,
            'logged_at' => '11:00',
            'date' => '2025-01-01',
            'notes' => 'Updated note',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function authenticated_user_can_delete_their_food_log()
    {
        $foodLog = FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);

        $response = $this->actingAs($this->user1)->delete(route('food-logs.destroy', $foodLog->id));

        $response->assertRedirect(route('food-logs.index', ['date' => $foodLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('food_logs', ['id' => $foodLog->id]);
    }

    /** @test */
    public function authenticated_user_cannot_delete_other_users_food_log()
    {
        $foodLog = FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->delete(route('food-logs.destroy', $foodLog->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('food_logs', ['id' => $foodLog->id]);
    }

    /** @test */
    public function authenticated_user_can_bulk_delete_their_food_logs()
    {
        $foodLog1 = FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $foodLog2 = FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $foodLog3 = FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->post(route('food-logs.destroy-selected'), [
            'food_log_ids' => [$foodLog1->id, $foodLog2->id],
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => $foodLog1->logged_at->format('Y-m-d')]));
        $this->assertDatabaseMissing('food_logs', ['id' => $foodLog1->id]);
        $this->assertDatabaseMissing('food_logs', ['id' => $foodLog2->id]);
        $this->assertDatabaseHas('food_logs', ['id' => $foodLog3->id]);
    }

    /** @test */
    public function authenticated_user_cannot_bulk_delete_other_users_food_logs()
    {
        $foodLog1 = FoodLogFactory::new()->create(['user_id' => $this->user1->id, 'ingredient_id' => $this->ingredient1->id]);
        $foodLog2 = FoodLogFactory::new()->create(['user_id' => $this->user2->id, 'ingredient_id' => $this->ingredient2->id]);

        $response = $this->actingAs($this->user1)->post(route('food-logs.destroy-selected'), [
            'food_log_ids' => [$foodLog1->id, $foodLog2->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('food_logs', ['id' => $foodLog1->id]);
        $this->assertDatabaseHas('food_logs', ['id' => $foodLog2->id]);
    }

    /** @test */
    public function authenticated_user_can_add_meal_to_log()
    {
        $meal = MealFactory::new()->create(['user_id' => $this->user1->id]);
        $meal->ingredients()->attach($this->ingredient1->id, ['quantity' => 50]);

        $response = $this->actingAs($this->user1)->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-01-02']));
        $this->assertDatabaseHas('food_logs', [
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

        $response = $this->actingAs($this->user1)->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1,
            'logged_at_meal' => '12:00',
            'meal_date' => '2025-01-02',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('food_logs', ['user_id' => $this->user1->id]);
    }


}