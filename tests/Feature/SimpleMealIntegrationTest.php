<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Meal;
use App\Models\FoodLog;
use Carbon\Carbon;

class SimpleMealIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $unit;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = Unit::factory()->create([
            'name' => 'grams',
            'abbreviation' => 'g'
        ]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function end_to_end_meal_creation_process_with_multiple_ingredients()
    {
        // Create a variety of ingredients
        $ingredients = [
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Ground Turkey',
                'protein' => 20,
                'carbs' => 0,
                'fats' => 8,
                'cost_per_unit' => 0.12,
                'base_quantity' => 100
            ]),
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Sweet Potato',
                'protein' => 2,
                'carbs' => 20,
                'fats' => 0,
                'cost_per_unit' => 0.04,
                'base_quantity' => 100
            ]),
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Spinach',
                'protein' => 3,
                'carbs' => 4,
                'fats' => 0,
                'cost_per_unit' => 0.01,
                'base_quantity' => 100
            ]),
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Olive Oil',
                'protein' => 0,
                'carbs' => 0,
                'fats' => 100,
                'cost_per_unit' => 0.50,
                'base_quantity' => 100
            ])
        ];

        // Start meal creation process
        $response = $this->get(route('meals.create'));
        $response->assertOk();

        // Create meal with name
        $response = $this->post(route('meals.store'), [
            'name' => 'Complete Balanced Meal',
            'comments' => 'A nutritious meal'
        ]);

        $meal = Meal::where('user_id', $this->user->id)->where('name', 'Complete Balanced Meal')->first();
        $this->assertNotNull($meal);
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Add first ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredients[0]->id
        ]));
        $response->assertOk();

        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredients[0]->id,
            'quantity' => 150
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));

        // Add remaining ingredients one by one
        $quantities = [100, 200, 15]; // Sweet potato, spinach, olive oil
        
        for ($i = 1; $i < count($ingredients); $i++) {
            // Navigate to add ingredient
            $response = $this->get(route('meals.add-ingredient', [
                'meal' => $meal->id,
                'ingredient' => $ingredients[$i]->id
            ]));
            $response->assertOk();

            // Add ingredient with quantity
            $response = $this->post(route('meals.store-ingredient', $meal->id), [
                'ingredient_id' => $ingredients[$i]->id,
                'quantity' => $quantities[$i - 1]
            ]);
            $response->assertRedirect(route('meals.edit', $meal->id));
            $response->assertSessionHas('success', 'Ingredient added!');
        }

        // Verify all ingredients were added correctly
        $meal->refresh();
        $meal->load('ingredients');
        $this->assertCount(4, $meal->ingredients);

        // Verify specific quantities
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[0]->id,
            'quantity' => 150
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[1]->id,
            'quantity' => 100
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[2]->id,
            'quantity' => 200
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[3]->id,
            'quantity' => 15
        ]);

        // Verify meal appears correctly in index
        $response = $this->get(route('meals.index'));
        $response->assertOk();
        
        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $this->assertNotNull($tableComponent);
        
        $tableData = json_encode($tableComponent);
        $this->assertStringContainsString('Complete Balanced Meal', $tableData);
        
        // Calculate expected calories:
        // Turkey: 150g * (20*4 + 0*4 + 8*9) / 100 = 150 * 152 / 100 = 228 cal
        // Sweet Potato: 100g * (2*4 + 20*4 + 0*9) / 100 = 100 * 88 / 100 = 88 cal
        // Spinach: 200g * (3*4 + 4*4 + 0*9) / 100 = 200 * 28 / 100 = 56 cal
        // Olive Oil: 15g * (0*4 + 0*4 + 100*9) / 100 = 15 * 900 / 100 = 135 cal
        // Total: 507 cal
        $this->assertStringContainsString('507 cal', $tableData);
    }

    /** @test */
    public function meal_editing_with_multiple_ingredients_and_modifications()
    {
        // Create ingredients
        $protein = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Chicken Thigh',
            'protein' => 18,
            'carbs' => 0,
            'fats' => 9,
            'cost_per_unit' => 0.10,
            'base_quantity' => 100
        ]);

        $carb = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Jasmine Rice',
            'protein' => 4,
            'carbs' => 79,
            'fats' => 0,
            'cost_per_unit' => 0.03,
            'base_quantity' => 100
        ]);

        $vegetable = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Green Beans',
            'protein' => 2,
            'carbs' => 7,
            'fats' => 0,
            'cost_per_unit' => 0.02,
            'base_quantity' => 100
        ]);

        $fat = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Butter',
            'protein' => 1,
            'carbs' => 0,
            'fats' => 81,
            'cost_per_unit' => 0.25,
            'base_quantity' => 100
        ]);

        // Create meal with initial ingredients
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Dinner Plate'
        ]);
        $meal->ingredients()->attach($protein->id, ['quantity' => 120]);
        $meal->ingredients()->attach($carb->id, ['quantity' => 80]);
        $meal->ingredients()->attach($vegetable->id, ['quantity' => 150]);

        // Test editing workflow
        $response = $this->get(route('meals.edit', $meal->id));
        $response->assertOk();

        // Verify nutritional information is displayed
        $data = $response->viewData('data');
        $nutritionComponent = collect($data['components'])->firstWhere(function ($component) {
            return $component['type'] === 'messages' && 
                   isset($component['data']['messages'][0]['text']) &&
                   str_contains($component['data']['messages'][0]['text'], 'Nutritional Information:');
        });
        $this->assertNotNull($nutritionComponent);

        // Add new ingredient (butter)
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $fat->id,
            'quantity' => 10
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Modify existing ingredient quantities
        // Increase chicken
        $response = $this->post(route('meals.edit-quantity', [$meal->id, $protein->id]), [
            'quantity' => 180
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Quantity updated!');

        // Decrease rice
        $response = $this->post(route('meals.edit-quantity', [$meal->id, $carb->id]), [
            'quantity' => 60
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Remove green beans
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $vegetable->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient removed from meal.');

        // Verify final state
        $meal->refresh();
        $meal->load('ingredients');
        $this->assertCount(3, $meal->ingredients);

        // Verify quantities
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $protein->id,
            'quantity' => 180
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $carb->id,
            'quantity' => 60
        ]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $fat->id,
            'quantity' => 10
        ]);
        $this->assertDatabaseMissing('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $vegetable->id
        ]);

        // Verify updated nutritional information in index
        $response = $this->get(route('meals.index'));
        $response->assertOk();
        
        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $tableData = json_encode($tableComponent);
        
        // Calculate new calories:
        // Chicken: 180g * (18*4 + 0*4 + 9*9) / 100 = 180 * 153 / 100 = 275.4 cal
        // Rice: 60g * (4*4 + 79*4 + 0*9) / 100 = 60 * 332 / 100 = 199.2 cal
        // Butter: 10g * (1*4 + 0*4 + 81*9) / 100 = 10 * 733 / 100 = 73.3 cal
        // Total: ~548 cal
        $this->assertStringContainsString('548 cal', $tableData);
    }

    /** @test */
    public function compatibility_with_existing_meal_logging_system()
    {
        // Create complex meal with multiple ingredients
        $ingredients = [
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Lean Beef',
                'protein' => 26,
                'carbs' => 0,
                'fats' => 15,
                'cost_per_unit' => 0.20,
                'base_quantity' => 100
            ]),
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Basmati Rice',
                'protein' => 4,
                'carbs' => 78,
                'fats' => 1,
                'cost_per_unit' => 0.04,
                'base_quantity' => 100
            ]),
            Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $this->unit->id,
                'name' => 'Mixed Vegetables',
                'protein' => 3,
                'carbs' => 8,
                'fats' => 0,
                'cost_per_unit' => 0.03,
                'base_quantity' => 100
            ])
        ];

        // Create meal using new system
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Beef and Rice Bowl',
            'comments' => 'Hearty lunch meal'
        ]);

        $quantities = [125, 90, 100];
        foreach ($ingredients as $index => $ingredient) {
            $meal->ingredients()->attach($ingredient->id, ['quantity' => $quantities[$index]]);
        }

        // Test logging full portion
        $logDate = Carbon::today();
        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => '12:30',
            'meal_date' => $logDate->format('Y-m-d'),
            'notes' => 'Lunch at office'
        ]);

        // Verify all ingredients were logged with correct quantities
        foreach ($ingredients as $index => $ingredient) {
            $this->assertDatabaseHas('food_logs', [
                'ingredient_id' => $ingredient->id,
                'unit_id' => $this->unit->id,
                'quantity' => $quantities[$index],
                'user_id' => $this->user->id,
            ]);

            // Verify notes format
            $foodLog = FoodLog::where('ingredient_id', $ingredient->id)
                ->where('user_id', $this->user->id)
                ->latest()
                ->first();
            
            $this->assertStringContainsString('Beef and Rice Bowl', $foodLog->notes);
            $this->assertStringContainsString('Portion: 1', $foodLog->notes);
            $this->assertStringContainsString('Hearty lunch meal', $foodLog->notes);
            $this->assertStringContainsString('Lunch at office', $foodLog->notes);
        }

        // Test logging partial portion
        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 0.75,
            'logged_at_meal' => '18:00',
            'meal_date' => $logDate->format('Y-m-d'),
        ]);

        // Verify partial quantities: 125*0.75=93.75, 90*0.75=67.5, 100*0.75=75
        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredients[0]->id,
            'quantity' => 93.75,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredients[1]->id,
            'quantity' => 67.5,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredients[2]->id,
            'quantity' => 75,
            'user_id' => $this->user->id,
        ]);

        // Test that meal data structure is compatible
        $meal->refresh();
        $meal->load('ingredients');
        
        // Verify meal structure matches what addMealToLog expects
        $this->assertTrue($meal->ingredients->isNotEmpty());
        $this->assertNotNull($meal->name);
        $this->assertNotNull($meal->user_id);
        
        foreach ($meal->ingredients as $ingredient) {
            $this->assertNotNull($ingredient->pivot->quantity);
            $this->assertNotNull($ingredient->base_unit_id);
        }
    }

    /** @test */
    public function meal_system_integrates_with_component_architecture()
    {
        // Create ingredients
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Test Ingredient 1'
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Test Ingredient 2'
        ]);

        // Test that all pages use flexible component system
        $routes = [
            ['method' => 'get', 'route' => 'meals.index'],
            ['method' => 'get', 'route' => 'meals.create'],
        ];

        foreach ($routes as $routeInfo) {
            $response = $this->get(route($routeInfo['route']));
            $response->assertOk();
            $response->assertViewIs('mobile-entry.flexible');
            
            $data = $response->viewData('data');
            $this->assertArrayHasKey('components', $data);
            $this->assertIsArray($data['components']);
            $this->assertNotEmpty($data['components']);
        }

        // Create meal to test edit page
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Component Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 100]);

        $response = $this->get(route('meals.edit', $meal->id));
        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        
        // Verify specific component types are present
        $componentTypes = collect($data['components'])->pluck('type')->toArray();
        $this->assertContains('title', $componentTypes);
        $this->assertContains('table', $componentTypes);
        $this->assertContains('button', $componentTypes);
        $this->assertContains('item-list', $componentTypes);

        // Test quantity form uses component system
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient2->id
        ]));
        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
        $this->assertArrayHasKey('data', $formComponent);
    }

    /** @test */
    public function meal_system_handles_edge_cases_and_error_conditions()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Edge Case Ingredient'
        ]);

        // Test accessing non-existent meal
        $response = $this->get(route('meals.edit', 99999));
        $response->assertStatus(404);

        // Create meal for testing
        $testMeal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        // Test accessing non-existent ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $testMeal->id,
            'ingredient' => 99999
        ]));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Ingredient not found.');

        // Test missing ingredient parameter
        $response = $this->get(route('meals.add-ingredient', ['meal' => $testMeal->id]));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No ingredient specified.');

        // Create meal for further testing
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Edge Case Meal'
        ]);

        // Test updating quantity for non-existent ingredient in meal
        $response = $this->get(route('meals.edit-quantity', [$meal->id, $ingredient->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('error', 'Ingredient not found in meal.');

        // Test removing non-existent ingredient from meal
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('error', 'Ingredient not found in meal.');

        // Test very small valid quantity (boundary condition)
        $response = $this->post(route('meals.store-ingredient', $testMeal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 0.01 // Minimum valid quantity
        ]);
        $response->assertRedirect(route('meals.edit', $testMeal->id));
        $response->assertSessionMissing('errors');

        // Verify ingredient was added with tiny quantity
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $testMeal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 0.01
        ]);
    }

    /** @test */
    public function meal_system_maintains_data_consistency_across_operations()
    {
        // Create ingredients
        $ingredients = Ingredient::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
        ]);

        // Create meal and perform multiple operations
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Consistency Test Meal'
        ]);

        // Add ingredients
        foreach ($ingredients as $index => $ingredient) {
            $meal->ingredients()->attach($ingredient->id, ['quantity' => ($index + 1) * 50]);
        }

        // Verify initial state
        $this->assertCount(3, $meal->ingredients);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[0]->id,
            'quantity' => 50
        ]);

        // Update quantities
        $response = $this->post(route('meals.edit-quantity', [$meal->id, $ingredients[0]->id]), [
            'quantity' => 75
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Verify update
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[0]->id,
            'quantity' => 75
        ]);

        // Remove middle ingredient
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredients[1]->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Verify removal
        $this->assertDatabaseMissing('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredients[1]->id
        ]);

        // Verify other ingredients remain
        $meal->refresh();
        $meal->load('ingredients');
        $this->assertCount(2, $meal->ingredients);
        $this->assertTrue($meal->ingredients->contains('id', $ingredients[0]->id));
        $this->assertTrue($meal->ingredients->contains('id', $ingredients[2]->id));
        $this->assertFalse($meal->ingredients->contains('id', $ingredients[1]->id));

        // Verify meal still exists
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);

        // Remove remaining ingredients one by one
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredients[0]->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Meal should still exist with one ingredient
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);

        // Remove last ingredient - should delete meal
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredients[2]->id]));
        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Ingredient removed and meal deleted (no ingredients remaining).');

        // Verify meal was soft deleted and all pivot records cleaned up
        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
        $this->assertDatabaseMissing('meal_ingredients', ['meal_id' => $meal->id]);
    }
}