<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodLogCreateMealFromListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_create_a_meal_from_food_log_entries()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient1 = Ingredient::factory()->create(['user_id' => $user->id]);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user->id]);

        $foodLog1 = FoodLog::factory()->create(['user_id' => $user->id, 'ingredient_id' => $ingredient1->id, 'quantity' => 100]);
        $foodLog2 = FoodLog::factory()->create(['user_id' => $user->id, 'ingredient_id' => $ingredient2->id, 'quantity' => 200]);

        $response = $this->post(route('meals.create-from-logs'), [
            'meal_name' => 'My New Meal',
            'food_log_ids' => [$foodLog1->id, $foodLog2->id],
        ]);

        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Meal created successfully from log entries.');

        $this->assertDatabaseHas('meals', [
            'name' => 'My New Meal',
            'user_id' => $user->id,
        ]);

        $meal = Meal::where('name', 'My New Meal')->first();

        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 100,
        ]);

        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 200,
        ]);
    }
}
