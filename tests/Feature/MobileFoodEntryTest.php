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
        // Mock current time to 14:37 (should round to 14:30)
        Carbon::setTestNow(Carbon::parse('2025-01-15 14:37:00'));
        
        $testDate = '2025-01-15';
        
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $this->ingredient->id,
            'quantity' => 100,
            'date' => $testDate,
        ]);

        $response->assertRedirect(route('food-logs.mobile-entry', ['date' => $testDate]));
        
        $foodLog = FoodLog::where('user_id', $this->user->id)->first();
        
        // Should be rounded to 14:30
        $this->assertEquals('14:30:00', $foodLog->logged_at->format('H:i:s'));
        
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
        $otherIngredient = Ingredient::factory()->create(['user_id' => $otherUser->id]);
        $otherMeal = Meal::factory()->create(['user_id' => $otherUser->id]);
        
        $testDate = '2025-01-15';
        
        // Try to log other user's ingredient
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'ingredient',
            'selected_id' => $otherIngredient->id,
            'quantity' => 100,
            'date' => $testDate,
        ]);

        $response->assertStatus(404); // Should not find the ingredient
        
        // Try to log other user's meal
        $response = $this->actingAs($this->user)->post(route('food-logs.store'), [
            'redirect_to' => 'mobile-entry',
            'selected_type' => 'meal',
            'selected_id' => $otherMeal->id,
            'portion' => 1,
            'date' => $testDate,
        ]);

        $response->assertStatus(404); // Should not find the meal
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
        $response->assertSee('Today&#039;s Food Log', false);
        $response->assertSee($this->ingredient->name);
        $response->assertSee('100 ' . $this->unit->name);
        $response->assertSee('150 ' . $this->unit->name);
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
}