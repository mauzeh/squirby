<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;

class IngredientManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = Unit::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_create_ingredient()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredientData = [
            'name' => $this->faker->word,
            'protein' => $this->faker->randomFloat(2, 0, 100),
            'carbs' => $this->faker->randomFloat(2, 0, 100),
            'added_sugars' => $this->faker->randomFloat(2, 0, 50),
            'fats' => $this->faker->randomFloat(2, 0, 100),
            'sodium' => $this->faker->randomFloat(2, 0, 1000),
            'iron' => $this->faker->randomFloat(2, 0, 100),
            'potassium' => $this->faker->randomFloat(2, 0, 1000),
            'fiber' => $this->faker->randomFloat(2, 0, 50),
            'calcium' => $this->faker->randomFloat(2, 0, 100),
            'caffeine' => $this->faker->randomFloat(2, 0, 500),
            'base_quantity' => $this->faker->randomFloat(2, 0.01, 100),
            'base_unit_id' => $this->unit->id,
            'cost_per_unit' => $this->faker->randomFloat(2, 0, 10),
        ];

        $response = $this->post(route('ingredients.store'), $ingredientData);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success', 'Ingredient created successfully.');
        $this->assertDatabaseHas('ingredients', array_merge($ingredientData, ['user_id' => $user->id]));
    }

    /** @test */
    public function authenticated_user_cannot_create_ingredient_with_missing_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredientData = [
            'name' => '',
            'protein' => $this->faker->randomFloat(2, 0, 100),
            'carbs' => $this->faker->randomFloat(2, 0, 100),
            'added_sugars' => $this->faker->randomFloat(2, 0, 50),
            'fats' => $this->faker->randomFloat(2, 0, 100),
            'sodium' => $this->faker->randomFloat(2, 0, 1000),
            'iron' => $this->faker->randomFloat(2, 0, 100),
            'potassium' => $this->faker->randomFloat(2, 0, 1000),
            'fiber' => $this->faker->randomFloat(2, 0, 50),
            'calcium' => $this->faker->randomFloat(2, 0, 100),
            'caffeine' => $this->faker->randomFloat(2, 0, 500),
            'base_quantity' => $this->faker->randomFloat(2, 0.01, 100),
            'base_unit_id' => $this->unit->id,
            'cost_per_unit' => $this->faker->randomFloat(2, 0, 10),
        ];

        $response = $this->post(route('ingredients.store'), $ingredientData);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('ingredients', ['user_id' => $user->id, 'name' => '']);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_ingredient()
    {
        $ingredientData = [
            'name' => $this->faker->word,
            'protein' => $this->faker->randomFloat(2, 0, 100),
            'carbs' => $this->faker->randomFloat(2, 0, 100),
            'added_sugars' => $this->faker->randomFloat(2, 0, 50),
            'fats' => $this->faker->randomFloat(2, 0, 100),
            'sodium' => $this->faker->randomFloat(2, 0, 1000),
            'iron' => $this->faker->randomFloat(2, 0, 100),
            'potassium' => $this->faker->randomFloat(2, 0, 1000),
            'fiber' => $this->faker->randomFloat(2, 0, 50),
            'calcium' => $this->faker->randomFloat(2, 0, 100),
            'caffeine' => $this->faker->randomFloat(2, 0, 500),
            'base_quantity' => $this->faker->randomFloat(2, 0.01, 100),
            'base_unit_id' => $this->unit->id,
            'cost_per_unit' => $this->faker->randomFloat(2, 0, 10),
        ];

        $response = $this->post(route('ingredients.store'), $ingredientData);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('ingredients', $ingredientData);
    }

    /** @test */
    public function authenticated_user_only_sees_their_ingredients()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $ingredient1 = Ingredient::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Ingredient', 'base_unit_id' => $this->unit->id]);

        $this->actingAs($user2);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Ingredient', 'base_unit_id' => $this->unit->id]);

        $response = $this->get(route('ingredients.index'));

        $response->assertOk();
        $response->assertSee($ingredient2->name);
        $response->assertDontSee($ingredient1->name);
    }
}
