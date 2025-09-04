<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Meal;

class MealManagementTest extends TestCase
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
    public function authenticated_user_only_sees_their_ingredients_in_meal_form()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $ingredient1 = Ingredient::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Ingredient', 'base_unit_id' => $this->unit->id]);

        $this->actingAs($user2);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Ingredient', 'base_unit_id' => $this->unit->id]);

        $response = $this->get(route('meals.create'));

        $response->assertOk();
        $response->assertSee($ingredient2->name);
        $response->assertDontSee($ingredient1->name);
    }
}
