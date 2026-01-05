<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Meal;

class SimpleMealControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $unit;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = Unit::factory()->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function index_displays_meals_using_flexible_components()
    {
        $response = $this->get(route('meals.index'));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        $response->assertViewHas('data');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        $this->assertIsArray($data['components']);
    }

    /** @test */
    public function index_displays_nutritional_information_for_meals()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 15,
            'carbs' => 30,
            'fats' => 8,
            'cost_per_unit' => 3.00,
            'base_quantity' => 100
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Nutritious Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.index'));

        $response->assertOk();
        
        $data = $response->viewData('data');
        
        // Check for table component with nutritional badges
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $this->assertNotNull($tableComponent);
        
        // The table should contain nutritional information as badges
        // Calories = (15*4) + (30*4) + (8*9) = 60 + 120 + 72 = 252
        $tableData = json_encode($tableComponent);
        $this->assertStringContainsString('252 cal', $tableData);
        $this->assertStringContainsString('15g P', $tableData);
        $this->assertStringContainsString('30g C', $tableData);
        $this->assertStringContainsString('8g F', $tableData);
        $this->assertStringContainsString('$3.00', $tableData);
    }

    /** @test */
    public function create_displays_simple_meal_creation_form()
    {
        $response = $this->get(route('meals.create'));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        
        // Check for title component
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        $this->assertNotNull($titleComponent);
        $this->assertEquals('Create Meal', $titleComponent['data']['main']);
        
        // Check for info message
        $messagesComponent = collect($data['components'])->firstWhere('type', 'messages');
        $this->assertNotNull($messagesComponent);
        
        // Check for form component
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
    }

    /** @test */
    public function edit_displays_meal_builder_interface()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.edit', $meal->id));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        
        // Check for title component
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        $this->assertNotNull($titleComponent);
        $this->assertStringContainsString('Edit Meal: Test Meal', $titleComponent['data']['main']);
    }

    /** @test */
    public function edit_displays_nutritional_information_when_meal_has_ingredients()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 10,
            'carbs' => 20,
            'fats' => 5,
            'cost_per_unit' => 2.50,
            'base_quantity' => 100
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.edit', $meal->id));

        $response->assertOk();
        
        $data = $response->viewData('data');
        
        // Check for nutritional information message component
        $nutritionComponent = collect($data['components'])->firstWhere('type', 'messages');
        $this->assertNotNull($nutritionComponent);
        
        $nutritionMessage = $nutritionComponent['data']['messages'][0]['text'];
        $this->assertStringContainsString('Nutritional Information:', $nutritionMessage);
        // Calories = (10*4) + (20*4) + (5*9) = 40 + 80 + 45 = 165
        $this->assertStringContainsString('165 cal', $nutritionMessage);
        $this->assertStringContainsString('10g protein', $nutritionMessage);
        $this->assertStringContainsString('20g carbs', $nutritionMessage);
        $this->assertStringContainsString('5g fat', $nutritionMessage);
        $this->assertStringContainsString('$2.50 cost', $nutritionMessage);
    }

    /** @test */
    public function edit_does_not_display_nutritional_information_when_meal_has_no_ingredients()
    {
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empty Meal'
        ]);

        $response = $this->get(route('meals.edit', $meal->id));

        $response->assertOk();
        
        $data = $response->viewData('data');
        
        // Check that there's no nutritional information message
        $components = collect($data['components']);
        $nutritionComponents = $components->filter(function ($component) {
            return $component['type'] === 'messages' && 
                   isset($component['data']['messages'][0]['text']) &&
                   str_contains($component['data']['messages'][0]['text'], 'Nutritional Information:');
        });
        
        $this->assertTrue($nutritionComponents->isEmpty());
    }

    /** @test */
    public function edit_prevents_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $meal = Meal::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Meal'
        ]);

        $response = $this->get(route('meals.edit', $meal->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function destroy_deletes_meal_and_redirects()
    {
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        $response = $this->delete(route('meals.destroy', $meal->id));

        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Meal deleted successfully.');
        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
    }

    /** @test */
    public function destroy_prevents_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $meal = Meal::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Meal'
        ]);

        $response = $this->delete(route('meals.destroy', $meal->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('meals', ['id' => $meal->id, 'deleted_at' => null]);
    }

    /** @test */
    public function add_ingredient_shows_quantity_form()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Test Ingredient'
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient->id
        ]));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
    }

    /** @test */
    public function add_ingredient_prevents_duplicates()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient->id
        ]));

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('warning', 'Ingredient already in meal.');
    }

    /** @test */
    public function store_creates_new_meal_with_name_and_comments()
    {
        $response = $this->post(route('meals.store'), [
            'name' => 'New Test Meal',
            'comments' => 'Test comments'
        ]);

        $this->assertDatabaseHas('meals', [
            'user_id' => $this->user->id,
            'name' => 'New Test Meal',
            'comments' => 'Test comments'
        ]);

        $meal = Meal::where('user_id', $this->user->id)->where('name', 'New Test Meal')->first();
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Meal created successfully! Now add some ingredients.');
    }

    /** @test */
    public function store_ingredient_adds_to_existing_meal()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing Meal'
        ]);

        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 200
        ]);

        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 200
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient added!');
    }

    /** @test */
    public function store_ingredient_prevents_duplicates()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 200
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('warning', 'Ingredient already in meal.');
        
        // Verify quantity wasn't changed
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);
    }

    /** @test */
    public function update_quantity_shows_form_on_get_request()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.edit-quantity', [$meal->id, $ingredient->id]));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
    }

    /** @test */
    public function update_quantity_updates_on_post_request()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->post(route('meals.edit-quantity', [$meal->id, $ingredient->id]), [
            'quantity' => 250
        ]);

        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 250
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Quantity updated!');
    }

    /** @test */
    public function remove_ingredient_removes_from_meal()
    {
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);
        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 150]);

        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient1->id]));

        $this->assertDatabaseMissing('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient1->id
        ]);

        // Meal should still exist with other ingredient
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient2->id
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient removed from meal.');
    }

    /** @test */
    public function remove_last_ingredient_deletes_meal()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient->id]));

        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
        $this->assertDatabaseMissing('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id
        ]);

        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Ingredient removed and meal deleted (no ingredients remaining).');
    }

    /** @test */
    public function ingredient_management_validates_quantities()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        // Test invalid quantity (too small)
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 0
        ]);

        $response->assertSessionHasErrors('quantity');

        // Test invalid quantity (negative)
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => -5
        ]);

        $response->assertSessionHasErrors('quantity');

        // Test invalid quantity (non-numeric)
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 'invalid'
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    /** @test */
    public function ingredient_management_prevents_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'user_id' => $otherUser->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        // Try to add other user's ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient->id
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Ingredient not found.');
    }
}