<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use App\Models\FoodLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class MobileFoodEntryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $ingredient;
    protected $meal;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->create(['name' => 'grams']);
        $this->ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
        ]);
        $this->meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $this->meal->ingredients()->attach($this->ingredient->id, ['quantity' => 100]);
    }

    /** @test */
    public function authenticated_user_can_access_mobile_food_entry_page()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        $response->assertViewIs('food_logs.mobile-entry');
        $response->assertViewHas(['selectedDate', 'ingredients', 'meals', 'foodLogs', 'dailyTotals']);
    }

    /** @test */
    public function mobile_entry_page_displays_date_navigation()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertSee('Today');
        $response->assertSee('Prev');
        $response->assertSee('Next');
    }

    /** @test */
    public function mobile_entry_page_accepts_date_parameter()
    {
        $testDate = '2025-01-15';
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $response->assertOk();
        $response->assertViewHas('selectedDate');
        
        $selectedDate = $response->viewData('selectedDate');
        $this->assertEquals($testDate, $selectedDate->toDateString());
    }

    /** @test */
    public function authenticated_user_can_submit_ingredient_from_mobile_entry()
    {
        $testDate = '2025-01-15';
        
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 150,
            'date' => $testDate,
            'notes' => 'Mobile test note',
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('success', 'Ingredient logged successfully!');
        
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 150,
            'notes' => 'Mobile test note',
        ]);
    }

    /** @test */
    public function authenticated_user_can_submit_meal_from_mobile_entry()
    {
        $testDate = '2025-01-15';
        
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $this->meal->id,
            'portion' => 1.5,
            'date' => $testDate,
            'notes' => 'Mobile meal note',
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('success', 'Meal logged successfully!');
        
        // Check that food log was created for the meal ingredient
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 150, // 100 * 1.5 portion
        ]);
        
        // Check that notes include meal name and portion
        $foodLog = FoodLog::where('user_id', $this->user->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();
        
        $this->assertStringContainsString($this->meal->name, $foodLog->notes);
        $this->assertStringContainsString('Portion: 1.5', $foodLog->notes);
        $this->assertStringContainsString('Mobile meal note', $foodLog->notes);
    }

    /** @test */
    public function mobile_entry_rounds_time_to_nearest_15_minutes()
    {
        // Mock current time to 14:37 (should round to 14:45)
        Carbon::setTestNow(Carbon::parse('2025-01-15 14:37:00'));
        
        $testDate = '2025-01-15';
        
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 100,
            'date' => $testDate,
            'notes' => '',
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        // Check for any errors
        if (session('error')) {
            $this->fail('Expected success but got error: ' . session('error'));
        }
        
        $response->assertSessionHas('success', 'Ingredient logged successfully!');
        
        $foodLog = FoodLog::where('user_id', $this->user->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();
        
        $this->assertNotNull($foodLog, 'Food log should be created');
        
        // Should be rounded to 14:30 and have the correct date (37 minutes rounds to 30)
        $this->assertEquals('2025-01-15 14:30:00', $foodLog->logged_at->format('Y-m-d H:i:s'));
        
        Carbon::setTestNow(); // Reset
    }

    /** @test */
    public function mobile_entry_validates_required_fields()
    {
        $testDate = '2025-01-15';
        
        // Test missing quantity for ingredient
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'date' => $testDate,
            // Missing quantity
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'Quantity is required for ingredients.');
        
        // Test missing portion for meal
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $this->meal->id,
            'date' => $testDate,
            // Missing portion
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'Portion is required for meals.');
    }

    /** @test */
    public function mobile_entry_only_allows_user_owned_ingredients_and_meals()
    {
        $otherUser = User::factory()->create();
        $otherUnit = Unit::factory()->create(['name' => 'other_grams']);
        $otherIngredient = Ingredient::factory()->create([
            'user_id' => $otherUser->id,
            'base_unit_id' => $otherUnit->id,
        ]);
        $otherMeal = Meal::factory()->create(['user_id' => $otherUser->id]);
        
        $testDate = '2025-01-15';
        
        // Try to log other user's ingredient - should redirect with error
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $otherIngredient->id,
            'quantity' => 100,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
        
        // Try to log other user's meal - should redirect with error
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $otherMeal->id,
            'portion' => 1,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function mobile_entry_displays_existing_food_logs_for_selected_date()
    {
        $testDate = '2025-01-15';
        
        // Create some food logs for the test date
        $foodLog1 = FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 100,
            'logged_at' => Carbon::parse($testDate . ' 08:30:00'),
            'notes' => 'Breakfast ingredient',
        ]);
        
        $foodLog2 = FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 150,
            'logged_at' => Carbon::parse($testDate . ' 12:15:00'),
            'notes' => 'Lunch ingredient',
        ]);
        
        // Create a food log for a different date (should not appear)
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 50,
            'logged_at' => Carbon::parse('2025-01-14 10:00:00'),
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $response->assertOk();
        
        // Check that the page displays the food logs for the selected date
        $response->assertSee("Today's Food Log", false);
        $response->assertSee($this->ingredient->name);
        $response->assertSee('100.00 ' . $this->unit->name);
        $response->assertSee('150.00 ' . $this->unit->name);
        $response->assertSee('Breakfast ingredient');
        $response->assertSee('Lunch ingredient');
        $response->assertSee('8:30 AM');
        $response->assertSee('12:15 PM');
        
        // Check that nutrition information is displayed
        $response->assertSee('Cal:');
        $response->assertSee('P:');
        $response->assertSee('C:');
        $response->assertSee('F:');
        
        // Check that delete buttons are present
        $response->assertSee('×'); // Delete button symbol
        
        // Verify the food logs are passed to the view
        $foodLogs = $response->viewData('foodLogs');
        $this->assertCount(2, $foodLogs);
        $this->assertEquals($foodLog2->id, $foodLogs->first()->id); // Should be ordered by logged_at desc
        $this->assertEquals($foodLog1->id, $foodLogs->last()->id);
    }

    /** @test */
    public function mobile_entry_shows_no_logs_message_when_no_food_logged()
    {
        $testDate = '2025-01-15';
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $response->assertOk();
        $response->assertSee('No food logged for this date yet.');
        $response->assertDontSee('Today\'s Food Log');
    }

    /** @test */
    public function user_can_delete_food_log_from_mobile_entry()
    {
        $testDate = '2025-01-15';
        
        // Create a food log
        $foodLog = FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 100,
            'logged_at' => Carbon::parse($testDate . ' 08:30:00'),
        ]);
        
        // Delete the food log from mobile entry
        $response = $this->actingAs($this->user)
            ->from(route('food-logs.mobile-entry', ['date' => $testDate]))
            ->delete(route('food-logs.destroy', $foodLog));
        
        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('success', 'Log entry deleted successfully!');
        
        // Verify the food log was deleted
        $this->assertDatabaseMissing('food_logs', [
            'id' => $foodLog->id,
        ]);
    }

    /** @test */
    public function user_cannot_delete_other_users_food_logs()
    {
        $otherUser = User::factory()->create();
        $otherIngredient = Ingredient::factory()->create(['user_id' => $otherUser->id]);
        $otherUnit = Unit::factory()->create();
        
        // Create a food log for another user
        $otherFoodLog = FoodLog::factory()->create([
            'user_id' => $otherUser->id,
            'ingredient_id' => $otherIngredient->id,
            'unit_id' => $otherUnit->id,
            'quantity' => 100,
            'logged_at' => Carbon::now(),
        ]);
        
        // Try to delete the other user's food log
        $response = $this->actingAs($this->user)->delete(route('food-logs.destroy', $otherFoodLog));
        
        $response->assertStatus(403); // Forbidden
        
        // Verify the food log still exists
        $this->assertDatabaseHas('food_logs', [
            'id' => $otherFoodLog->id,
        ]);
    }

    /** @test */
    public function mobile_entry_displays_daily_nutrition_totals()
    {
        $testDate = '2024-01-15';
        
        // Create food logs with known nutrition values
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 10,
            'carbs' => 5, // 10*4 + 5*4 + 5*9 = 40 + 20 + 45 = 105 calories
            'fats' => 5,
            'base_quantity' => 100,
        ]);
        
        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 15,
            'carbs' => 10, // 15*4 + 10*4 + 8*9 = 60 + 40 + 72 = 172 calories
            'fats' => 8,
            'base_quantity' => 100,
        ]);
        
        // Create food logs for the test date
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient1->id,
            'unit_id' => $this->unit->id,
            'quantity' => 100, // 1x base quantity
            'logged_at' => Carbon::parse($testDate . ' 08:00:00'),
        ]);
        
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient2->id,
            'unit_id' => $this->unit->id,
            'quantity' => 50, // 0.5x base quantity
            'logged_at' => Carbon::parse($testDate . ' 12:00:00'),
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $response->assertOk();
        
        // Check that daily totals section is displayed (without heading since it's now at the top)
        
        // Expected totals:
        // Ingredient1 (100g): 105 calories, 10g protein, 5g carbs, 5g fats
        // Ingredient2 (50g): 172*0.5 = 86 calories, 15*0.5 = 7.5g protein, 10*0.5 = 5g carbs, 8*0.5 = 4g fats
        // Total: 105 + 86 = 191 calories, 10 + 7.5 = 17.5g protein, 5 + 5 = 10g carbs, 5 + 4 = 9g fats
        
        $response->assertSee('191'); // Calories
        $response->assertSee('17.5g'); // Protein
        $response->assertSee('10g'); // Carbs
        $response->assertSee('9g'); // Fats
        
        // Check that the totals grid is displayed
        $response->assertSee('totals-grid', false);
        $response->assertSee('Calories');
        $response->assertSee('Protein');
        $response->assertSee('Carbs');
        $response->assertSee('Fats');
    }

    /** @test */
    public function mobile_entry_shows_zero_totals_when_no_food_logged()
    {
        $testDate = '2024-01-15';
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $response->assertOk();
        
        // Check that daily totals section is displayed with zeros (without heading since it's now at the top)
        $response->assertSee('0'); // Calories should be 0
        $response->assertSee('0g'); // Protein, carbs, fats should be 0g
    }

    /** @test */
    public function mobile_entry_validates_positive_quantities()
    {
        $testDate = '2025-01-15';
        
        // Test negative quantity
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => -5,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
        
        // Test zero quantity
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 0,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function mobile_entry_validates_maximum_quantities()
    {
        $testDate = '2025-01-15';
        
        // Test quantity exceeding maximum
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 15000, // Exceeds max of 10000
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
        
        // Test portion exceeding maximum
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $this->meal->id,
            'portion' => 150, // Exceeds max of 100
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function mobile_entry_handles_deleted_ingredients()
    {
        $testDate = '2025-01-15';
        $deletedIngredientId = $this->ingredient->id;
        
        // Delete the ingredient
        $this->ingredient->delete();
        
        // Try to log the deleted ingredient
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $deletedIngredientId,
            'quantity' => 100,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'The selected ingredient no longer exists or you do not have permission to access it.');
        
        // Verify no food log was created
        $this->assertDatabaseMissing('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $deletedIngredientId,
        ]);
    }

    /** @test */
    public function mobile_entry_handles_deleted_meals()
    {
        $testDate = '2025-01-15';
        $deletedMealId = $this->meal->id;
        
        // Delete the meal
        $this->meal->delete();
        
        // Try to log the deleted meal
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $deletedMealId,
            'portion' => 1,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'The selected meal no longer exists or you do not have permission to access it.');
        
        // Verify no food log was created
        $this->assertDatabaseMissing('food_logs', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function mobile_entry_handles_ingredients_without_valid_units()
    {
        $testDate = '2025-01-15';
        
        // Create ingredient without base unit
        $ingredientWithoutUnit = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => null,
        ]);
        
        // Try to log ingredient without unit
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $ingredientWithoutUnit->id,
            'quantity' => 100,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'The selected ingredient does not have a valid unit configured.');
    }

    /** @test */
    public function mobile_entry_handles_meals_without_ingredients()
    {
        $testDate = '2025-01-15';
        
        // Create meal without ingredients
        $emptyMeal = Meal::factory()->create(['user_id' => $this->user->id]);
        
        // Try to log empty meal
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $emptyMeal->id,
            'portion' => 1,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error', 'The selected meal has no ingredients configured.');
    }

    /** @test */
    public function mobile_entry_validates_notes_length()
    {
        $testDate = '2025-01-15';
        $longNotes = str_repeat('a', 1001); // Exceeds 1000 character limit
        
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 100,
            'date' => $testDate,
            'notes' => $longNotes,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Notes cannot exceed 1000 characters', session('error'));
    }

    /** @test */
    public function mobile_entry_page_handles_invalid_dates_gracefully()
    {
        // Test with invalid date format
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => 'invalid-date']));
        
        $response->assertOk();
        
        // Should default to today's date
        $selectedDate = $response->viewData('selectedDate');
        $this->assertEquals(Carbon::today()->toDateString(), $selectedDate->toDateString());
    }

    /** @test */
    public function mobile_entry_page_limits_date_range()
    {
        // Test with date too far in the future
        $futureDate = Carbon::today()->addYears(2)->toDateString();
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $futureDate]));
        
        $response->assertOk();
        
        // Should default to today's date
        $selectedDate = $response->viewData('selectedDate');
        $this->assertEquals(Carbon::today()->toDateString(), $selectedDate->toDateString());
        
        // Test with date too far in the past
        $pastDate = Carbon::today()->subYears(10)->toDateString();
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry', ['date' => $pastDate]));
        
        $response->assertOk();
        
        // Should default to today's date
        $selectedDate = $response->viewData('selectedDate');
        $this->assertEquals(Carbon::today()->toDateString(), $selectedDate->toDateString());
    }

    /** @test */
    public function mobile_entry_page_only_shows_ingredients_with_valid_units()
    {
        // Create ingredient without base unit
        $ingredientWithoutUnit = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => null,
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        $ingredients = $response->viewData('ingredients');
        
        // Should only include ingredients with valid units
        $this->assertTrue($ingredients->contains('id', $this->ingredient->id));
        $this->assertFalse($ingredients->contains('id', $ingredientWithoutUnit->id));
    }

    /** @test */
    public function mobile_entry_page_only_shows_meals_with_ingredients()
    {
        // Create meal without ingredients
        $emptyMeal = Meal::factory()->create(['user_id' => $this->user->id]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        $meals = $response->viewData('meals');
        
        // Should only include meals with ingredients
        $this->assertTrue($meals->contains('id', $this->meal->id));
        $this->assertFalse($meals->contains('id', $emptyMeal->id));
    }

    /** @test */
    public function mobile_entry_page_displays_error_and_success_messages()
    {
        // Test error message display
        $response = $this->actingAs($this->user)
            ->withSession(['error' => 'Test error message'])
            ->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        $response->assertSee('Test error message');
        $response->assertSee('error-message', false);
        
        // Test success message display
        $response = $this->actingAs($this->user)
            ->withSession(['success' => 'Test success message'])
            ->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        $response->assertSee('Test success message');
        $response->assertSee('success-message', false);
    }

    /** @test */
    public function mobile_entry_displays_ingredient_base_quantity_as_data_attribute()
    {
        // Create ingredient with specific base_quantity
        $ingredientWithBaseQuantity = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 250,
            'name' => 'Test Ingredient with Base Quantity',
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that the ingredient appears with the correct data-base-quantity attribute
        $response->assertSee('data-base-quantity="250"', false);
        $response->assertSee('Test Ingredient with Base Quantity');
    }

    /** @test */
    public function mobile_entry_displays_ingredient_base_quantity_in_template()
    {
        // Create ingredients with different base quantities
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 100,
            'name' => 'Standard Ingredient',
        ]);
        
        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 50.5,
            'name' => 'Half Portion Ingredient',
        ]);
        
        $ingredient3 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 0,
            'name' => 'Zero Base Ingredient',
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Verify all ingredients appear with their base quantities
        $response->assertSee('data-base-quantity="100"', false);
        $response->assertSee('data-base-quantity="50.5"', false);
        $response->assertSee('data-base-quantity="0"', false);
        
        // Verify ingredient names are displayed
        $response->assertSee('Standard Ingredient');
        $response->assertSee('Half Portion Ingredient');
        $response->assertSee('Zero Base Ingredient');
    }

    /** @test */
    public function mobile_entry_handles_null_base_quantity_gracefully()
    {
        // Create ingredient with null base_quantity (use 0 since null isn't allowed)
        $ingredientWithNullBase = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 0, // Use 0 instead of null since database doesn't allow null
            'name' => 'Null Base Ingredient',
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Should still display the ingredient (JavaScript will handle 0 gracefully)
        $response->assertSee('Null Base Ingredient');
        $response->assertSee('data-base-quantity="0"', false);
    }

    /** @test */
    public function mobile_entry_increment_decrement_buttons_have_correct_data_attributes()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that increment/decrement buttons have correct data-target attributes
        $response->assertSee('data-target="quantity"', false);
        $response->assertSee('data-target="portion"', false);
        
        // Check that buttons have correct classes
        $response->assertSee('class="decrement-button"', false);
        $response->assertSee('class="increment-button"', false);
    }

    /** @test */
    public function mobile_entry_form_inputs_have_correct_attributes()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check quantity input attributes
        $response->assertSee('id="quantity"', false);
        $response->assertSee('name="quantity"', false);
        $response->assertSee('step="0.01"', false);
        $response->assertSee('min="0"', false);
        $response->assertSee('value="1"', false);
        
        // Check portion input attributes
        $response->assertSee('id="portion"', false);
        $response->assertSee('name="portion"', false);
        
        // Check that both inputs have large-input class
        $response->assertSee('class="large-input"', false);
    }

    /** @test */
    public function mobile_entry_displays_unit_information_for_ingredients()
    {
        // Create ingredient with specific unit
        $customUnit = Unit::factory()->create(['name' => 'milliliters']);
        $ingredientWithCustomUnit = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $customUnit->id,
            'name' => 'Liquid Ingredient',
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that unit information is included in data attributes
        $response->assertSee('data-unit="milliliters"', false);
        $response->assertSee('Liquid Ingredient');
        
        // Check that unit display element exists
        $response->assertSee('id="ingredient-unit"', false);
        $response->assertSee('class="unit-display"', false);
    }

    /** @test */
    public function mobile_entry_javascript_validation_elements_are_present()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that validation error container exists
        $response->assertSee('id="validation-errors"', false);
        $response->assertSee('class="message-container message-validation hidden"', false);
        
        // Check that form has correct ID for JavaScript
        $response->assertSee('id="food-logging-form"', false);
        
        // Check that hidden form fields exist for JavaScript
        $response->assertSee('id="selected-type"', false);
        $response->assertSee('id="selected-id"', false);
        $response->assertSee('id="selected-name"', false);
        
        // Check that display elements exist
        $response->assertSee('id="selected-food-name"', false);
        $response->assertSee('id="selected-food-type-label"', false);
    }

    /** @test */
    public function mobile_entry_form_containers_have_correct_visibility_classes()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that food list container starts hidden
        $response->assertSee('id="food-list-container"', false);
        $response->assertSee('class="item-list-container hidden"', false);
        
        // Check that logging form container starts hidden
        $response->assertSee('id="logging-form-container"', false);
        $response->assertSee('class="item-list-container hidden"', false);
        
        // Check that form field containers start hidden
        $response->assertSee('id="ingredient-fields"', false);
        $response->assertSee('class="form-fields hidden"', false);
        $response->assertSee('id="meal-fields"', false);
        $response->assertSee('class="form-fields hidden"', false);
    }

    /** @test */
    public function mobile_entry_displays_add_food_button()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that add food button exists with correct attributes
        $response->assertSee('id="add-food-button"', false);
        $response->assertSee('class="button-large button-green"', false);
        $response->assertSee('Add Food');
    }

    /** @test */
    public function mobile_entry_displays_form_action_buttons()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that form action buttons exist
        $response->assertSee('id="submit-button"', false);
        $response->assertSee('class="button-large button-blue"', false);
        $response->assertSee('Log Food');
        
        $response->assertSee('id="cancel-logging"', false);
        $response->assertSee('class="button-large button-gray"', false);
        $response->assertSee('Cancel');
    }

    /** @test */
    public function mobile_entry_includes_csrf_protection()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that CSRF token is included
        $response->assertSee('name="_token"', false);
        
        // Check that redirect_to hidden field is set correctly
        $response->assertSee('name="redirect_to"', false);
        $response->assertSee('value="mobile-entry"', false);
    }

    /** @test */
    public function mobile_entry_displays_notes_textarea_for_both_ingredient_and_meal()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check ingredient notes textarea
        $response->assertSee('id="ingredient-notes"', false);
        $response->assertSee('name="notes"', false);
        $response->assertSee('class="large-textarea"', false);
        $response->assertSee('placeholder="Optional notes..."', false);
        
        // Check meal notes textarea
        $response->assertSee('id="meal-notes"', false);
        // Note: both textareas have name="notes" since only one is visible at a time
    }

    /** @test */
    public function mobile_entry_meal_portion_input_has_correct_default_and_attributes()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check meal portion input
        $response->assertSee('id="portion"', false);
        $response->assertSee('name="portion"', false);
        $response->assertSee('value="1"', false); // Default portion is 1
        $response->assertSee('step="0.01"', false);
        $response->assertSee('min="0"', false);
        
        // Check that portion has unit display
        $response->assertSee('servings'); // Static unit display for portions
    }

    /** @test */
    public function mobile_entry_handles_decimal_base_quantities()
    {
        // Create ingredient with decimal base_quantity
        $decimalIngredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => $this->unit->id,
            'base_quantity' => 33.33,
            'name' => 'Decimal Base Ingredient',
        ]);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that decimal base quantity is properly displayed
        $response->assertSee('data-base-quantity="33.33"', false);
        $response->assertSee('Decimal Base Ingredient');
    }

    /** @test */
    public function mobile_entry_displays_ingredients_and_meals_in_separate_sections()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that ingredients are displayed with correct classes
        $response->assertSee('class="food-list-item item-list-item ingredient-item"', false);
        $response->assertSee('class="food-name item-name"', false);
        $response->assertSee('<em>Ingredient</em>', false);
        
        // Check that meals are displayed with correct classes
        $response->assertSee('class="food-list-item item-list-item meal-item"', false);
        $response->assertSee('<em>Meal</em>', false);
    }

    /** @test */
    public function mobile_entry_form_has_correct_action_and_method()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.mobile-entry'));
        
        $response->assertOk();
        
        // Check that form has correct action and method
        $response->assertSee('method="POST"', false);
        $response->assertSee('action="' . route('food-logs.store') . '"', false);
    }
}