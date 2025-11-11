<?php

namespace Tests\Unit\Services\MobileEntry;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MobileFoodForm;
use App\Models\Unit;
use App\Models\User;
use App\Services\MobileEntry\FoodLogService;
use App\Services\NutritionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for FoodLogService core logic
 * 
 * Tests the business logic without route dependencies
 */
class FoodLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private FoodLogService $service;
    private Carbon $testDate;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the NutritionService
        $mockNutritionService = $this->createMock(NutritionService::class);
        $mockNutritionService->method('calculateTotalMacro')->willReturn(100);
        $mockNutritionService->method('calculateFoodLogTotals')->willReturn([
            'calories' => 1200,
            'protein' => 80,
            'carbs' => 150,
            'fats' => 40,
            'fiber' => 25,
            'added_sugars' => 10,
            'sodium' => 2000,
            'calcium' => 800,
            'iron' => 15,
            'potassium' => 3000,
            'caffeine' => 50,
            'cost' => 12.50
        ]);
        
        $this->service = new FoodLogService($mockNutritionService);
        $this->testDate = Carbon::parse('2024-01-15');
    }

    #[Test]
    public function it_generates_summary_with_nutrition_data()
    {
        $user = User::factory()->create();
        
        // Create some food logs
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();
        
        FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate,
            'quantity' => 100
        ]);
        
        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('values', $summary);
        $this->assertArrayHasKey('labels', $summary);
        $this->assertArrayHasKey('ariaLabels', $summary);
        
        // Check values are properly formatted (from mocked NutritionService)
        $this->assertEquals(1200, $summary['values']['total']); // calories
        $this->assertEquals(1, $summary['values']['completed']); // entries count
        $this->assertEquals(80.0, $summary['values']['today']); // protein
        
        // Check labels
        $this->assertEquals('Calories', $summary['labels']['total']);
        $this->assertEquals('Entries', $summary['labels']['completed']);
        $this->assertEquals('7-Day Avg', $summary['labels']['average']);
        $this->assertEquals('Protein (g)', $summary['labels']['today']);
        
        // Check aria labels
        $this->assertEquals('Daily nutrition summary', $summary['ariaLabels']['section']);
    }

    #[Test]
    public function it_returns_null_summary_when_no_entries_exist()
    {
        $user = User::factory()->create();
        
        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
        $this->assertNull($summary);
    }

    #[Test]
    public function it_generates_logged_items_with_nutrition_info()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create(['name' => 'Chicken Breast']);
        $unit = Unit::factory()->create(['name' => 'g']);
        
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate->copy()->setTime(12, 30),
            'quantity' => 150,
            'notes' => 'Grilled with herbs'
        ]);
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertCount(1, $loggedItems['items']);
        $this->assertArrayHasKey('emptyMessage', $loggedItems);
        $this->assertEquals('', $loggedItems['emptyMessage']); // Should be empty string when there are items
        
        $item = $loggedItems['items'][0];
        $this->assertEquals($foodLog->id, $item['id']);
        $this->assertEquals('Chicken Breast', $item['title']);
        $this->assertStringContainsString('food-logs/' . $foodLog->id . '/edit', $item['editAction']);
        $this->assertStringContainsString('food-logs/' . $foodLog->id, $item['deleteAction']);
        
        // Check message format
        $this->assertEquals('success', $item['message']['type']);
        $this->assertEquals('Completed!', $item['message']['prefix']);
        $this->assertStringContainsString('150 g • 100 cal, 100g protein', $item['message']['text']);
        
        // Check notes
        $this->assertEquals('Grilled with herbs', $item['freeformText']);
        
        // Check delete parameters
        $this->assertArrayHasKey('deleteParams', $item);
        $this->assertEquals('mobile-entry.foods', $item['deleteParams']['redirect_to']);
        $this->assertEquals($this->testDate->toDateString(), $item['deleteParams']['date']);
    }

    #[Test]
    public function it_skips_logged_items_with_missing_ingredient_or_unit()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create food log with valid ingredient and unit
        FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate
        ]);
        
        // Create food log with missing ingredient (simulate deleted ingredient)
        // We'll create the log first, then delete the ingredient to simulate the scenario
        $tempIngredient = Ingredient::factory()->create();
        $invalidLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $tempIngredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate
        ]);
        // Delete the ingredient to simulate missing relationship
        $tempIngredient->delete();
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        // Should only include the valid log
        $this->assertCount(1, $loggedItems['items']);
    }

    #[Test]
    public function it_includes_empty_message_when_no_logged_items()
    {
        $user = User::factory()->create();
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertEmpty($loggedItems['items']);
        $this->assertArrayHasKey('emptyMessage', $loggedItems);
        $this->assertEquals('No food logged yet today! Add ingredients or meals above to get started.', $loggedItems['emptyMessage']);
        
        // Check confirmation messages and aria labels are still present
        $this->assertArrayHasKey('confirmMessages', $loggedItems);
        $this->assertArrayHasKey('ariaLabels', $loggedItems);
    }

    #[Test]
    public function it_orders_logged_items_by_logged_at_desc()
    {
        $user = User::factory()->create();
        $ingredient1 = Ingredient::factory()->create(['name' => 'First Food']);
        $ingredient2 = Ingredient::factory()->create(['name' => 'Second Food']);
        $unit = Unit::factory()->create();
        
        // Create logs in different order
        $log1 = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient1->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate->copy()->setTime(10, 0) // Earlier
        ]);
        
        $log2 = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient2->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate->copy()->setTime(14, 0) // Later
        ]);
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertCount(2, $loggedItems['items']);
        // Should be ordered by logged_at desc (most recent first)
        $this->assertEquals('Second Food', $loggedItems['items'][0]['title']); // 14:00
        $this->assertEquals('First Food', $loggedItems['items'][1]['title']);  // 10:00
    }

    #[Test]
    public function it_generates_item_selection_list_with_ingredients_and_meals()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create ingredients
        $ingredient1 = Ingredient::factory()->create([
            'name' => 'Chicken Breast',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        $ingredient2 = Ingredient::factory()->create([
            'name' => 'Brown Rice',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Create meal
        $meal = Meal::factory()->create([
            'name' => 'Chicken and Rice Bowl',
            'user_id' => $user->id
        ]);
        
        // Attach ingredients to meal
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 150]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 100]);
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $this->assertArrayHasKey('items', $itemSelectionList);
        $this->assertArrayHasKey('createForm', $itemSelectionList);
        $this->assertCount(3, $itemSelectionList['items']); // 2 ingredients + 1 meal
        
        // Check ingredient items
        $chickenItem = collect($itemSelectionList['items'])->firstWhere('name', 'Chicken Breast');
        $this->assertNotNull($chickenItem);
        $this->assertEquals('ingredient-' . $ingredient1->id, $chickenItem['id']);
        $this->assertEquals('custom', $chickenItem['type']['cssClass']);
        $this->assertEquals('Ingredient', $chickenItem['type']['label']);
        $this->assertEquals(2, $chickenItem['type']['priority']);
        $this->assertStringContainsString('mobile-entry/add-food-form/ingredient/' . $ingredient1->id, $chickenItem['href']);
        $this->assertStringContainsString('date=' . $this->testDate->toDateString(), $chickenItem['href']);
        
        // Check meal item
        $mealItem = collect($itemSelectionList['items'])->firstWhere('name', 'Chicken and Rice Bowl (Meal)');
        $this->assertNotNull($mealItem);
        $this->assertEquals('meal-' . $meal->id, $mealItem['id']);
        $this->assertEquals('highlighted', $mealItem['type']['cssClass']);
        $this->assertEquals('Meal', $mealItem['type']['label']);
        $this->assertEquals(1, $mealItem['type']['priority']);
        $this->assertStringContainsString('mobile-entry/add-food-form/meal/' . $meal->id, $mealItem['href']);
        
        // Create form should be present
        $this->assertArrayHasKey('createForm', $itemSelectionList);
    }

    #[Test]
    public function it_includes_create_form_in_item_selection_list()
    {
        $user = User::factory()->create();
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        // Verify createForm is included
        $this->assertArrayHasKey('createForm', $itemSelectionList);
        
        // Verify other expected keys are still present
        $this->assertArrayHasKey('items', $itemSelectionList);
        $this->assertArrayHasKey('noResultsMessage', $itemSelectionList);
        $this->assertArrayHasKey('ariaLabels', $itemSelectionList);
        $this->assertArrayHasKey('filterPlaceholder', $itemSelectionList);
        
        // Verify no results message mentions creating items
        $this->assertEquals('No food items found. Type a name and hit "+" to create a new ingredient.', $itemSelectionList['noResultsMessage']);
    }

    #[Test]
    public function it_excludes_ingredients_without_valid_units()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create ingredient with valid unit
        $validIngredient = Ingredient::factory()->create([
            'name' => 'Valid Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Create ingredient without unit
        $invalidIngredient = Ingredient::factory()->create([
            'name' => 'Invalid Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => null
        ]);
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $itemNames = collect($itemSelectionList['items'])->pluck('name')->toArray();
        $this->assertContains('Valid Ingredient', $itemNames);
        $this->assertNotContains('Invalid Ingredient', $itemNames);
    }

    #[Test]
    public function it_excludes_meals_without_ingredients()
    {
        $user = User::factory()->create();
        
        // Create meal with ingredients
        $validMeal = Meal::factory()->create([
            'name' => 'Valid Meal',
            'user_id' => $user->id
        ]);
        
        $ingredient = Ingredient::factory()->create();
        $validMeal->ingredients()->attach($ingredient->id, ['quantity' => 100]);
        
        // Create meal without ingredients
        $emptyMeal = Meal::factory()->create([
            'name' => 'Empty Meal',
            'user_id' => $user->id
        ]);
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $itemNames = collect($itemSelectionList['items'])->pluck('name')->toArray();
        $this->assertContains('Valid Meal (Meal)', $itemNames);
        $this->assertNotContains('Empty Meal (Meal)', $itemNames);
    }

    #[Test]
    public function it_shows_all_ingredients_as_ingredient_type()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        
        $ingredient1 = Ingredient::factory()->create([
            'name' => 'User Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        $ingredient2 = Ingredient::factory()->create([
            'name' => 'Another Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $item1 = collect($itemSelectionList['items'])->firstWhere('name', 'User Ingredient');
        $item2 = collect($itemSelectionList['items'])->firstWhere('name', 'Another Ingredient');
        
        $this->assertEquals('custom', $item1['type']['cssClass']);
        $this->assertEquals('Ingredient', $item1['type']['label']);
        $this->assertEquals(2, $item1['type']['priority']);
        
        $this->assertEquals('custom', $item2['type']['cssClass']);
        $this->assertEquals('Ingredient', $item2['type']['label']);
        $this->assertEquals(2, $item2['type']['priority']);
    }

    #[Test]
    public function it_sorts_meals_first_then_ingredients_alphabetically()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create ingredients
        $zIngredient = Ingredient::factory()->create([
            'name' => 'Z Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        $aIngredient = Ingredient::factory()->create([
            'name' => 'A Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Create a meal
        $meal = Meal::factory()->create([
            'name' => 'Test Meal',
            'user_id' => $user->id
        ]);
        $meal->ingredients()->attach($aIngredient->id, ['quantity' => 100]);
        
        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        // Meal should come first due to priority 1
        $this->assertEquals('Test Meal (Meal)', $itemSelectionList['items'][0]['name']);
        $this->assertEquals(1, $itemSelectionList['items'][0]['type']['priority']);
        
        // Then ingredients alphabetically
        $this->assertEquals('A Ingredient', $itemSelectionList['items'][1]['name']);
        $this->assertEquals(2, $itemSelectionList['items'][1]['type']['priority']);
        
        $this->assertEquals('Z Ingredient', $itemSelectionList['items'][2]['name']);
        $this->assertEquals(2, $itemSelectionList['items'][2]['type']['priority']);
    }

    #[Test]
    public function it_generates_forms_from_database_entries()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create(['name' => 'g']);
        $ingredient = Ingredient::factory()->create([
            'name' => 'Olive Oil',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 100,
            'base_quantity' => 100
        ]);
        
        // Create mobile food form entry
        MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate->toDateString(),
            'type' => 'ingredient',
            'item_id' => $ingredient->id,
            'item_name' => $ingredient->name
        ]);
        
        $request = new \Illuminate\Http\Request();
        $forms = $this->service->generateForms($user->id, $this->testDate, $request);
        
        $this->assertCount(1, $forms);
        
        $form = $forms[0];
        $this->assertEquals('ingredient-' . $ingredient->id, $form['id']);
        $this->assertEquals('success', $form['type']);
        $this->assertEquals('Olive Oil', $form['title']);
        $this->assertEquals('Olive Oil', $form['itemName']);
        $this->assertStringContainsString('food-logs', $form['formAction']);
        $this->assertStringContainsString('mobile-entry/remove-food-form/ingredient-' . $ingredient->id, $form['deleteAction']);
        
        // Check numeric fields
        $this->assertCount(1, $form['numericFields']);
        $quantityField = $form['numericFields'][0];
        $this->assertEquals('quantity', $quantityField['name']);
        $this->assertEquals('Quantity (g):', $quantityField['label']);
        $this->assertEquals(1.0, $quantityField['defaultValue']);
        $this->assertEquals('any', $quantityField['step']);
        $this->assertEquals(0.01, $quantityField['min']);
        $this->assertEquals(1000, $quantityField['max']);
        
        // Check comment field
        $commentField = $form['commentField'];
        $this->assertEquals('notes', $commentField['name']);
        $this->assertEquals('Notes:', $commentField['label']);
        $this->assertEquals('Any notes about this food?', $commentField['placeholder']);
        $this->assertEquals('', $commentField['defaultValue']);
        
        // Check hidden fields
        $hiddenFields = $form['hiddenFields'];
        $this->assertEquals($ingredient->id, $hiddenFields['ingredient_id']);
        $this->assertArrayHasKey('logged_at', $hiddenFields);
        $this->assertEquals($this->testDate->toDateString(), $hiddenFields['date']);
        $this->assertEquals('mobile-entry-foods', $hiddenFields['redirect_to']);
        
        // Check messages include nutrition info
        $nutritionMessage = collect($form['messages'])->firstWhere(function ($message) {
            return str_contains($message['text'] ?? '', 'cal');
        });
        $this->assertNotNull($nutritionMessage);
        $this->assertEquals('tip', $nutritionMessage['type']);
        $this->assertStringContainsString('Nutrition per serving:', $nutritionMessage['prefix']);
    }

    #[Test]
    public function it_generates_meal_forms_with_portion_field()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        
        $ingredient = Ingredient::factory()->create([
            'base_unit_id' => $unit->id,
            'protein' => 25,
            'carbs' => 0,
            'fats' => 5
        ]);
        
        $meal = Meal::factory()->create([
            'name' => 'Protein Bowl',
            'user_id' => $user->id,
            'comments' => 'High protein meal'
        ]);
        
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 150]);
        
        // Create mobile food form entry for meal
        MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate->toDateString(),
            'type' => 'meal',
            'item_id' => $meal->id,
            'item_name' => $meal->name
        ]);
        
        $request = new \Illuminate\Http\Request();
        $forms = $this->service->generateForms($user->id, $this->testDate, $request);
        
        $this->assertCount(1, $forms);
        
        $form = $forms[0];
        $this->assertEquals('meal-' . $meal->id, $form['id']);
        $this->assertEquals('Protein Bowl (Meal)', $form['title']);
        
        // Check portion field
        $this->assertCount(1, $form['numericFields']);
        $portionField = $form['numericFields'][0];
        $this->assertEquals('portion', $portionField['name']);
        $this->assertEquals('Portion:', $portionField['label']);
        $this->assertEquals(1.0, $portionField['defaultValue']);
        $this->assertEquals(0.25, $portionField['increment']);
        $this->assertEquals(0.1, $portionField['min']);
        $this->assertEquals(10, $portionField['max']);
        
        // Check hidden fields for meal
        $hiddenFields = $form['hiddenFields'];
        $this->assertEquals($meal->id, $hiddenFields['meal_id']);
        $this->assertArrayHasKey('logged_at_meal', $hiddenFields);
        $this->assertEquals($this->testDate->toDateString(), $hiddenFields['meal_date']);
        
        // Check messages include meal info
        $mealInfoMessage = collect($form['messages'])->firstWhere(function ($message) {
            return str_contains($message['text'] ?? '', 'ingredients');
        });
        $this->assertNotNull($mealInfoMessage);
        $this->assertEquals('info', $mealInfoMessage['type']);
        $this->assertEquals('This meal contains:', $mealInfoMessage['prefix']);
        $this->assertEquals('1 ingredients', $mealInfoMessage['text']);
        
        // Check meal comments appear in messages
        $commentsMessage = collect($form['messages'])->firstWhere('prefix', 'Meal notes:');
        $this->assertNotNull($commentsMessage);
        $this->assertEquals('High protein meal', $commentsMessage['text']);
    }

    #[Test]
    public function it_returns_empty_forms_when_no_database_forms()
    {
        $user = User::factory()->create();
        $request = new \Illuminate\Http\Request();
        
        $forms = $this->service->generateForms($user->id, $this->testDate, $request);
        
        // Should return empty array when no database forms exist
        $this->assertCount(0, $forms);
    }

    #[Test]
    public function it_adds_ingredient_form_to_database()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'name' => 'Test Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        $result = $this->service->addFoodForm($user->id, 'ingredient', $ingredient->id, $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('', $result['message']);
        
        // Verify database entry was created
        $this->assertDatabaseHas('mobile_food_forms', [
            'user_id' => $user->id,
            'type' => 'ingredient',
            'item_id' => $ingredient->id,
            'item_name' => 'Test Ingredient'
        ]);
    }

    #[Test]
    public function it_adds_meal_form_to_database()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();
        $meal = Meal::factory()->create([
            'name' => 'Test Meal',
            'user_id' => $user->id
        ]);
        
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);
        
        $result = $this->service->addFoodForm($user->id, 'meal', $meal->id, $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('', $result['message']);
        
        // Verify database entry was created
        $this->assertDatabaseHas('mobile_food_forms', [
            'user_id' => $user->id,
            'type' => 'meal',
            'item_id' => $meal->id,
            'item_name' => 'Test Meal'
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_form_entries()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'name' => 'Duplicate Test',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Add form first time
        $result1 = $this->service->addFoodForm($user->id, 'ingredient', $ingredient->id, $this->testDate);
        $this->assertTrue($result1['success']);
        
        // Try to add same form again
        $result2 = $this->service->addFoodForm($user->id, 'ingredient', $ingredient->id, $this->testDate);
        $this->assertFalse($result2['success']);
        $this->assertEquals('Duplicate Test is already added to your forms.', $result2['message']);
        
        // Verify only one database entry exists
        $count = MobileFoodForm::where('user_id', $user->id)
            ->where('type', 'ingredient')
            ->where('item_id', $ingredient->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_fails_to_add_form_for_nonexistent_ingredient()
    {
        $user = User::factory()->create();
        
        $result = $this->service->addFoodForm($user->id, 'ingredient', 99999, $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Food item not found. Try searching for a different name or create a new ingredient using the "+" button.', $result['message']);
        
        // Verify no database entry was created
        $this->assertDatabaseMissing('mobile_food_forms', [
            'user_id' => $user->id,
            'type' => 'ingredient',
            'item_id' => 99999
        ]);
    }

    #[Test]
    public function it_fails_to_add_form_for_ingredient_without_unit()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'name' => 'No Unit Ingredient',
            'user_id' => $user->id,
            'base_unit_id' => null
        ]);
        
        $result = $this->service->addFoodForm($user->id, 'ingredient', $ingredient->id, $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Ingredient does not have a valid unit configured. Please update the ingredient settings.', $result['message']);
    }

    #[Test]
    public function it_fails_to_add_form_for_meal_without_ingredients()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create([
            'name' => 'Empty Meal',
            'user_id' => $user->id
        ]);
        
        $result = $this->service->addFoodForm($user->id, 'meal', $meal->id, $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Meal has no ingredients configured. Please add ingredients to the meal first.', $result['message']);
    }

    #[Test]
    public function it_removes_food_form_from_database()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'name' => 'Remove Test',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Create form entry
        MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate,
            'type' => 'ingredient',
            'item_id' => $ingredient->id,
            'item_name' => $ingredient->name
        ]);
        
        $formId = 'ingredient-' . $ingredient->id;
        $result = $this->service->removeFoodForm($user->id, $formId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Removed Remove Test. You can add it back anytime using \'Add Food\' below.', $result['message']);
        
        // Verify database entry was deleted
        $this->assertDatabaseMissing('mobile_food_forms', [
            'user_id' => $user->id,
            'type' => 'ingredient',
            'item_id' => $ingredient->id
        ]);
    }

    #[Test]
    public function it_fails_to_remove_nonexistent_form()
    {
        $user = User::factory()->create();
        
        $result = $this->service->removeFoodForm($user->id, 'ingredient-99999');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Food form not found. It may have already been removed.', $result['message']);
    }

    #[Test]
    public function it_fails_to_remove_form_with_invalid_format()
    {
        $user = User::factory()->create();
        
        $result = $this->service->removeFoodForm($user->id, 'invalid-format');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Food form not found. It may have already been removed.', $result['message']);
    }

    #[Test]
    public function it_removes_form_after_successful_logging()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'name' => 'Logged Food',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id
        ]);
        
        // Create form entry
        MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate->toDateString(),
            'type' => 'ingredient',
            'item_id' => $ingredient->id,
            'item_name' => $ingredient->name
        ]);
        
        $result = $this->service->removeFormAfterLogging($user->id, 'ingredient', $ingredient->id, $this->testDate);
        
        $this->assertTrue($result, 'removeFormAfterLogging should return true when form is found and deleted');
        
        // Verify database entry was deleted
        $this->assertDatabaseMissing('mobile_food_forms', [
            'user_id' => $user->id,
            'type' => 'ingredient',
            'item_id' => $ingredient->id,
            'date' => $this->testDate->toDateString()
        ]);
    }

    #[Test]
    public function it_returns_false_when_removing_nonexistent_form_after_logging()
    {
        $user = User::factory()->create();
        
        $result = $this->service->removeFormAfterLogging($user->id, 'ingredient', 99999, $this->testDate);
        
        $this->assertFalse($result);
    }

    #[Test]
    public function it_creates_new_ingredient_successfully()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create(['name' => 'g']);
        
        $result = $this->service->createIngredient($user->id, 'New Custom Food', $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Created \'New Custom Food\'! Now scroll down to log your first entry - update the nutrition info and quantity as needed.', $result['message']);
        
        // Verify ingredient was created
        $this->assertDatabaseHas('ingredients', [
            'name' => 'New Custom Food',
            'user_id' => $user->id,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0,
            'base_quantity' => 100,
            'base_unit_id' => $unit->id,
            'cost_per_unit' => 0
        ]);
    }

    #[Test]
    public function it_fails_to_create_ingredient_with_existing_name()
    {
        $user = User::factory()->create();
        
        // Create existing ingredient
        Ingredient::factory()->create([
            'name' => 'Existing Food',
            'user_id' => $user->id
        ]);
        
        $result = $this->service->createIngredient($user->id, 'Existing Food', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('\'Existing Food\' already exists in your ingredient library. Use the search above to find and add it instead.', $result['message']);
        
        // Verify no duplicate was created
        $count = Ingredient::where('name', 'Existing Food')->count();
        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_fails_to_create_ingredient_when_default_unit_missing()
    {
        $user = User::factory()->create();
        
        // Ensure no 'g' unit exists
        Unit::where('name', 'g')->delete();
        
        $result = $this->service->createIngredient($user->id, 'Test Food', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Default unit not found. Please contact administrator.', $result['message']);
    }

    #[Test]
    public function it_cleans_up_old_forms()
    {
        $user = User::factory()->create();
        
        // Create forms for different dates
        $oldForm = MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate->copy()->subDays(5), // Old form
            'type' => 'ingredient',
            'item_id' => 1,
            'item_name' => 'Old Food'
        ]);
        
        $recentForm = MobileFoodForm::factory()->create([
            'user_id' => $user->id,
            'date' => $this->testDate->copy()->subDays(1), // Recent form
            'type' => 'ingredient',
            'item_id' => 2,
            'item_name' => 'Recent Food'
        ]);
        
        $this->service->cleanupOldForms($user->id, $this->testDate);
        
        // Old form should be deleted (older than 3 days)
        $this->assertDatabaseMissing('mobile_food_forms', ['id' => $oldForm->id]);
        
        // Recent form should remain
        $this->assertDatabaseHas('mobile_food_forms', ['id' => $recentForm->id]);
    }

    #[Test]
    public function it_generates_interface_messages_with_session_data()
    {
        $sessionMessages = [
            'success' => 'Food logged successfully!',
            'error' => 'Validation failed',
            'warning' => 'Please check your input',
            'info' => 'Additional information'
        ];
        
        $messages = $this->service->generateInterfaceMessages($sessionMessages);
        
        $this->assertTrue($messages['hasMessages']);
        $this->assertEquals(4, $messages['messageCount']);
        
        // Check success message
        $successMessage = collect($messages['messages'])->firstWhere('type', 'success');
        $this->assertNotNull($successMessage);
        $this->assertEquals('Food logged successfully!', $successMessage['text']);
        
        // Check error message
        $errorMessage = collect($messages['messages'])->firstWhere('type', 'error');
        $this->assertNotNull($errorMessage);
        $this->assertEquals('Validation failed', $errorMessage['text']);
    }

    #[Test]
    public function it_generates_empty_interface_messages_with_no_session_data()
    {
        $messages = $this->service->generateInterfaceMessages();
        
        $this->assertFalse($messages['hasMessages']);
        $this->assertEquals(0, $messages['messageCount']);
        $this->assertEmpty($messages['messages']);
    }

    #[Test]
    public function it_gets_appropriate_quantity_increments_for_different_units()
    {
        $testCases = [
            // Very small default values
            ['tsp', 0.1, 0.1],
            ['tablespoon', 0.1, 0.1],
            
            // Small units with normal values
            ['tsp', 1.0, 0.5],
            ['tablespoon', 1.0, 0.5],
            ['oz', 1.0, 0.5],
            
            // Medium units
            ['cup', 1.0, 0.5],
            ['ml', 1.0, 0.5],
            
            // Large units
            ['lb', 100, 1],
            ['kg', 100, 1],
            
            // Grams with different quantities
            ['g', 50, 5],   // Small quantity
            ['g', 150, 10], // Large quantity
            
            // Default case
            ['unknown', 1.0, 0.5]
        ];
        
        foreach ($testCases as [$unitName, $defaultValue, $expectedIncrement]) {
            $increment = $this->service->getQuantityIncrement($unitName, $defaultValue);
            $this->assertEquals($expectedIncrement, $increment, "Failed for unit: {$unitName} with value: {$defaultValue}");
        }
    }

    #[Test]
    public function it_respects_user_ownership_when_adding_forms()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create ingredient owned by user2
        $ingredient = Ingredient::factory()->create([
            'name' => 'Private Ingredient',
            'user_id' => $user2->id,
            'base_unit_id' => $unit->id
        ]);
        
        // User1 should not be able to add user2's ingredient
        $result = $this->service->addFoodForm($user1->id, 'ingredient', $ingredient->id, $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Food item not found. Try searching for a different name or create a new ingredient using the "+" button.', $result['message']);
    }

    #[Test]
    public function it_includes_last_session_data_in_ingredient_form_messages()
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->create(['name' => 'tbsp']);
        $ingredient = Ingredient::factory()->create([
            'name' => 'Olive Oil',
            'user_id' => $user->id,
            'base_unit_id' => $unit->id,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 100,
            'base_quantity' => 100
        ]);
        
        // Create previous food log
        FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate->copy()->subDays(2),
            'quantity' => 1.5,
            'notes' => 'For cooking pasta'
        ]);
        
        $lastSession = $this->service->getLastIngredientSession($ingredient->id, $this->testDate, $user->id);
        
        $this->assertIsArray($lastSession);
        $this->assertEquals(1.5, $lastSession['quantity']);
        $this->assertEquals('tbsp', $lastSession['unit']);
        $this->assertEquals('Jan 13', $lastSession['date']);
        $this->assertEquals('For cooking pasta', $lastSession['notes']);
        
        // Test message generation
        $messages = $this->service->generateIngredientFormMessages($ingredient, $lastSession);
        
        $this->assertGreaterThanOrEqual(3, count($messages)); // Last session + notes + nutrition
        
        $lastSessionMessage = collect($messages)->firstWhere('prefix', 'Last logged (Jan 13):');
        $this->assertNotNull($lastSessionMessage);
        $this->assertEquals('1.5 tbsp', $lastSessionMessage['text']);
        
        $notesMessage = collect($messages)->firstWhere('prefix', 'Your last notes:');
        $this->assertNotNull($notesMessage);
        $this->assertEquals('For cooking pasta', $notesMessage['text']);
        
        $nutritionMessage = collect($messages)->firstWhere(function ($message) {
            return str_contains($message['prefix'] ?? '', 'Nutrition per serving');
        });
        $this->assertNotNull($nutritionMessage);
    }

    #[Test]
    public function it_returns_null_when_no_last_ingredient_session_exists()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();
        
        $lastSession = $this->service->getLastIngredientSession($ingredient->id, $this->testDate, $user->id);
        
        $this->assertNull($lastSession);
    }

    #[Test]
    public function it_ignores_future_sessions_when_getting_last_ingredient_session()
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();
        
        // Create future food log
        FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => $this->testDate->copy()->addDays(1),
            'quantity' => 999
        ]);
        
        $lastSession = $this->service->getLastIngredientSession($ingredient->id, $this->testDate, $user->id);
        
        $this->assertNull($lastSession);
    }
}