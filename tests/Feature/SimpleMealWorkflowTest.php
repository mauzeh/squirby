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

class SimpleMealWorkflowTest extends TestCase
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
    public function complete_meal_creation_workflow_from_start_to_finish()
    {
        // Create some ingredients for the user
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Chicken Breast',
            'protein' => 25,
            'carbs' => 0,
            'fats' => 3,
            'cost_per_unit' => 0.15,
            'base_quantity' => 100
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Brown Rice',
            'protein' => 8,
            'carbs' => 77,
            'fats' => 2,
            'cost_per_unit' => 0.05,
            'base_quantity' => 100
        ]);

        // Step 1: Visit meal creation page
        $response = $this->get(route('meals.create'));
        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');

        // Step 2: Create meal with name
        $response = $this->post(route('meals.store'), [
            'name' => 'Protein Bowl',
            'comments' => 'Healthy meal'
        ]);

        // Verify meal was created
        $meal = Meal::where('user_id', $this->user->id)->where('name', 'Protein Bowl')->first();
        $this->assertNotNull($meal);
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Meal created successfully! Now add some ingredients.');

        // Step 3: Visit meal edit page
        $response = $this->get(route('meals.edit', $meal->id));
        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');

        // Step 4: Add first ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient1->id
        ]));
        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');

        // Step 5: Store first ingredient with quantity
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient1->id,
            'quantity' => 200
        ]);

        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 200
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient added!');

        // Step 6: Add second ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient2->id
        ]));
        $response->assertOk();

        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient2->id,
            'quantity' => 150
        ]);

        // Verify second ingredient was added
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 150
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient added!');

        // Step 7: Verify meal appears in index with correct nutritional info
        $response = $this->get(route('meals.index'));
        $response->assertOk();
        
        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $this->assertNotNull($tableComponent);
        
        // Calculate expected nutritional values
        // Chicken: 200g * (25*4 + 0*4 + 3*9) / 100 = 200 * (100 + 0 + 27) / 100 = 254 cal
        // Rice: 150g * (8*4 + 77*4 + 2*9) / 100 = 150 * (32 + 308 + 18) / 100 = 537 cal
        // Total: 791 cal
        $tableData = json_encode($tableComponent);
        $this->assertStringContainsString('791 cal', $tableData);
        $this->assertStringContainsString('Protein Bowl', $tableData);
    }

    /** @test */
    public function complete_meal_editing_and_ingredient_management_workflow()
    {
        // Create ingredients
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Salmon',
            'protein' => 20,
            'carbs' => 0,
            'fats' => 13,
            'cost_per_unit' => 0.30,
            'base_quantity' => 100
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Quinoa',
            'protein' => 14,
            'carbs' => 64,
            'fats' => 6,
            'cost_per_unit' => 0.08,
            'base_quantity' => 100
        ]);

        $ingredient3 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Broccoli',
            'protein' => 3,
            'carbs' => 7,
            'fats' => 0,
            'cost_per_unit' => 0.02,
            'base_quantity' => 100
        ]);

        // Create meal with initial ingredients
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Healthy Dinner'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 150]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 100]);

        // Step 1: Edit existing meal
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

        // Step 2: Add third ingredient
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient3->id
        ]));
        $response->assertOk();

        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient3->id,
            'quantity' => 200
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));

        // Step 3: Update quantity of existing ingredient
        $response = $this->get(route('meals.edit-quantity', [$meal->id, $ingredient1->id]));
        $response->assertOk();

        $response = $this->post(route('meals.edit-quantity', [$meal->id, $ingredient1->id]), [
            'quantity' => 200
        ]);
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Quantity updated!');

        // Verify quantity was updated
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 200
        ]);

        // Step 4: Remove one ingredient
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient2->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('success', 'Ingredient removed from meal.');

        // Verify ingredient was removed but meal still exists
        $this->assertDatabaseMissing('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient2->id
        ]);
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);

        // Step 5: Verify final meal state
        $meal->refresh();
        $meal->load('ingredients');
        $this->assertCount(2, $meal->ingredients);
        $this->assertTrue($meal->ingredients->contains('id', $ingredient1->id));
        $this->assertTrue($meal->ingredients->contains('id', $ingredient3->id));
        $this->assertFalse($meal->ingredients->contains('id', $ingredient2->id));
    }

    /** @test */
    public function complete_meal_deletion_and_cleanup_workflow()
    {
        // Create ingredients
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Pasta'
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Tomato Sauce'
        ]);

        // Create meal with ingredients
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Simple Pasta'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 50]);

        // Test 1: Direct meal deletion
        $meal2 = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Meal to Delete'
        ]);
        $meal2->ingredients()->attach($ingredient1->id, ['quantity' => 75]);

        $response = $this->delete(route('meals.destroy', $meal2->id));
        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Meal deleted successfully.');
        $this->assertSoftDeleted('meals', ['id' => $meal2->id]);

        // Test 2: Meal deletion by removing last ingredient
        // Remove first ingredient
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient1->id]));
        $response->assertRedirect(route('meals.edit', $meal->id));
        
        // Meal should still exist with one ingredient
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient2->id
        ]);

        // Remove last ingredient - should delete meal
        $response = $this->delete(route('meals.remove-ingredient', [$meal->id, $ingredient2->id]));
        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Ingredient removed and meal deleted (no ingredients remaining).');
        
        // Verify meal was soft deleted and pivot records cleaned up
        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
        $this->assertDatabaseMissing('meal_ingredients', ['meal_id' => $meal->id]);
    }

    /** @test */
    public function verify_compatibility_with_existing_add_meal_to_log_functionality()
    {
        // Create ingredients with nutritional data
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Oats',
            'protein' => 17,
            'carbs' => 66,
            'fats' => 7,
            'cost_per_unit' => 0.03,
            'base_quantity' => 100
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Banana',
            'protein' => 1,
            'carbs' => 23,
            'fats' => 0,
            'cost_per_unit' => 0.50,
            'base_quantity' => 100
        ]);

        // Create meal using the new system
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Breakfast Bowl',
            'comments' => 'Healthy morning meal'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 50]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 120]);

        // Test that the meal can be added to food log using existing functionality
        $logDate = Carbon::today();
        $logTime = '08:30';
        
        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => $logTime,
            'meal_date' => $logDate->format('Y-m-d'),
            'notes' => 'Morning breakfast'
        ]);

        // Verify food log entries were created for each ingredient
        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredient1->id,
            'unit_id' => $this->unit->id,
            'quantity' => 50, // 50g oats
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredient2->id,
            'unit_id' => $this->unit->id,
            'quantity' => 120, // 120g banana
            'user_id' => $this->user->id,
        ]);

        // Verify notes include meal name and comments
        $oatsLog = FoodLog::where('ingredient_id', $ingredient1->id)->first();
        $this->assertStringContainsString('Breakfast Bowl', $oatsLog->notes);
        $this->assertStringContainsString('Healthy morning meal', $oatsLog->notes);
        $this->assertStringContainsString('Morning breakfast', $oatsLog->notes);
        $this->assertStringContainsString('Portion: 1', $oatsLog->notes);

        // Test with different portion size
        $meal2 = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Snack Portion'
        ]);
        $meal2->ingredients()->attach($ingredient1->id, ['quantity' => 30]);

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal2->id,
            'portion' => 0.5,
            'logged_at_meal' => '15:00',
            'meal_date' => $logDate->format('Y-m-d'),
        ]);

        // Verify portion calculation: 30g * 0.5 = 15g
        $this->assertDatabaseHas('food_logs', [
            'ingredient_id' => $ingredient1->id,
            'quantity' => 15,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function meal_creation_prevents_duplicate_ingredients()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Test Ingredient'
        ]);

        // Create meal with ingredient
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        // Try to add the same ingredient again
        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $meal->id,
            'ingredient' => $ingredient->id
        ]));

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('warning', 'Ingredient already in meal.');

        // Try to store the same ingredient again
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 200
        ]);

        $response->assertRedirect(route('meals.edit', $meal->id));
        $response->assertSessionHas('warning', 'Ingredient already in meal.');

        // Verify original quantity wasn't changed
        $this->assertDatabaseHas('meal_ingredients', [
            'meal_id' => $meal->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);
    }

    /** @test */
    public function meal_system_handles_authorization_correctly()
    {
        $otherUser = User::factory()->create();
        
        // Create ingredient for other user
        $otherIngredient = Ingredient::factory()->create([
            'user_id' => $otherUser->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Other User Ingredient'
        ]);

        // Create meal for other user
        $otherMeal = Meal::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Meal'
        ]);

        // Test that current user cannot access other user's meal
        $response = $this->get(route('meals.edit', $otherMeal->id));
        $response->assertStatus(403);

        $response = $this->delete(route('meals.destroy', $otherMeal->id));
        $response->assertStatus(403);

        // Test that current user cannot add other user's ingredients
        $myMeal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'My Meal'
        ]);

        $response = $this->get(route('meals.add-ingredient', [
            'meal' => $myMeal->id,
            'ingredient' => $otherIngredient->id
        ]));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Ingredient not found.');

        $response = $this->post(route('meals.store-ingredient', $myMeal->id), [
            'ingredient_id' => $otherIngredient->id,
            'quantity' => 100
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Ingredient not found.');
    }

    /** @test */
    public function meal_system_validates_input_correctly()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'name' => 'Valid Ingredient'
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);

        // Test invalid quantities
        $invalidQuantities = [0, -5, 'invalid', null, ''];
        
        foreach ($invalidQuantities as $invalidQuantity) {
            $response = $this->post(route('meals.store-ingredient', $meal->id), [
                'ingredient_id' => $ingredient->id,
                'quantity' => $invalidQuantity
            ]);
            $response->assertSessionHasErrors('quantity');
        }

        // Test missing ingredient_id
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'quantity' => 100
        ]);
        $response->assertSessionHasErrors('ingredient_id');

        // Test non-existent ingredient_id
        $response = $this->post(route('meals.store-ingredient', $meal->id), [
            'ingredient_id' => 99999,
            'quantity' => 100
        ]);
        $response->assertSessionHasErrors('ingredient_id');

        // Test meal creation validation
        $response = $this->post(route('meals.store'), [
            'name' => '', // Empty name should fail
            'comments' => 'Test comments'
        ]);
        $response->assertSessionHasErrors('name');

        // Test valid meal creation
        $response = $this->post(route('meals.store'), [
            'name' => 'Valid Meal Name',
            'comments' => 'Test comments'
        ]);
        $response->assertSessionMissing('errors');
    }
}