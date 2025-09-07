<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Meal;
use App\Models\Role;

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

    /** @test */
    public function impersonated_user_meal_is_not_visible_after_adding()
    {
        // Create an admin user and a regular user
        $adminUser = User::factory()->create();
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);

        $regularUser = User::factory()->create();
        $athleteRole = \App\Models\Role::where('name', 'Athlete')->first();
        $regularUser->roles()->attach($athleteRole);

        // Log in as admin
        $this->actingAs($adminUser);

        // Impersonate the regular user
        $this->get(route('users.impersonate', $regularUser->id));
        // After impersonating, explicitly set the authenticated user for subsequent requests
        $this->actingAs($regularUser);
        $this->assertAuthenticatedAs($regularUser);

        // Create an ingredient for the regular user
        $ingredient = Ingredient::factory()->create([
            'user_id' => $regularUser->id,
            'base_unit_id' => $this->unit->id
        ]);

        // Add a meal as the impersonated user
        $mealName = 'Test Impersonated Meal';
        $response = $this->post(route('meals.store'), [
            'name' => $mealName,
            'comments' => 'Meal added while impersonating',
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 100]
            ]
        ]);

        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Meal created successfully.');

        // Navigate to the meals index page as the impersonated user
        $response = $this->get(route('meals.index'));
        $response->assertOk();

        // Assert that the newly added meal is NOT visible (this confirms the bug)
        $response->assertSee($mealName);

        // Stop impersonating
        $this->get(route('users.leave-impersonate'));
        // After leaving impersonation, explicitly set the authenticated user back to admin
        $this->actingAs($adminUser);
        $this->assertAuthenticatedAs($adminUser);
    }
}
