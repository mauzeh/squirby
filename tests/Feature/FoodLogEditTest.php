<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodLogEditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_update_a_food_log_and_redirect_correctly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        // Test redirect to mobile entry
        $response = $this->put(route('food-logs.update', $foodLog->id), [
            'quantity' => 100,
            'notes' => 'Updated notes',
            'redirect_to' => 'mobile-entry.foods',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseHas('food_logs', [
            'id' => $foodLog->id,
            'quantity' => 100,
            'notes' => 'Updated notes',
        ]);
    }

    /** @test */
    public function it_should_show_the_mobile_friendly_edit_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);
        $foodLog->load('ingredient');

        $response = $this->get(route('food-logs.edit', $foodLog->id));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');

        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        $this->assertCount(2, $data['components']);
        $this->assertEquals('title', $data['components'][0]['type']);
        $this->assertEquals('form', $data['components'][1]['type']);
        $this->assertEquals($foodLog->ingredient->name, $data['components'][1]['data']['title']);
    }

    /** @test */
    public function mobile_entry_foods_shows_edit_and_delete_buttons_for_logged_items()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $response = $this->get(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));

        $response->assertOk();
        $data = $response->viewData('data');
        
        // Find the logged items table
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $this->assertNotNull($tableComponent, 'Table component should exist');
        
        // Check that the table has rows
        $this->assertNotEmpty($tableComponent['data']['rows']);
        
        $row = $tableComponent['data']['rows'][0];
        
        // Check for actions
        $this->assertArrayHasKey('actions', $row);
        $this->assertCount(2, $row['actions']); // edit and delete
        
        // Check edit action
        $editAction = collect($row['actions'])->firstWhere('type', 'link');
        $this->assertNotNull($editAction);
        $this->assertEquals('fa-pencil', $editAction['icon']);
        $this->assertStringContainsString('food-logs/' . $foodLog->id . '/edit', $editAction['url']);
        $this->assertEquals('btn-transparent', $editAction['cssClass']);
        
        // Check delete action
        $deleteAction = collect($row['actions'])->firstWhere('type', 'form');
        $this->assertNotNull($deleteAction);
        $this->assertEquals('fa-trash', $deleteAction['icon']);
        $this->assertEquals(route('food-logs.destroy', $foodLog->id), $deleteAction['url']);
        $this->assertEquals('DELETE', $deleteAction['method']);
        $this->assertEquals('btn-transparent', $deleteAction['cssClass']);
        $this->assertTrue($deleteAction['requiresConfirm']);
    }

    /** @test */
    public function mobile_entry_foods_actions_are_compact()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $response = $this->get(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertTrue($row['compact']);
    }

    /** @test */
    public function user_cannot_edit_another_users_food_log()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create(['user_id' => $user1->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user1->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $this->actingAs($user2);
        $response = $this->get(route('food-logs.edit', $foodLog->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_delete_another_users_food_log()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create(['user_id' => $user1->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user1->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $this->actingAs($user2);
        $response = $this->delete(route('food-logs.destroy', $foodLog->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_log_a_meal_and_redirect_correctly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $today = \Carbon\Carbon::today();

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => '12:00',
            'meal_date' => $today->toDateString(),
            'notes' => 'Lunch',
            'redirect_to' => 'mobile-entry.foods'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods'));
        
        // Verify food log was created
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);
    }

    /** @test */
    public function logging_meal_creates_entries_for_all_ingredients()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient1 = Ingredient::factory()->create(['user_id' => $user->id, 'name' => 'Chicken']);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user->id, 'name' => 'Rice']);
        
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Chicken and Rice'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 150]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 200]);

        $today = \Carbon\Carbon::today();

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => '12:00',
            'meal_date' => $today->toDateString(),
            'redirect_to' => 'mobile-entry.foods'
        ]);

        $response->assertSessionHasNoErrors();
        
        // Verify both ingredients were logged
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 150,
        ]);
        
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 200,
        ]);
    }

    /** @test */
    public function logging_meal_with_portion_multiplies_quantities()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $today = \Carbon\Carbon::today();

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.5,
            'logged_at_meal' => '12:00',
            'meal_date' => $today->toDateString(),
            'redirect_to' => 'mobile-entry.foods'
        ]);

        $response->assertSessionHasNoErrors();
        
        // Verify quantity was multiplied by portion
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 150, // 100 * 1.5
        ]);
    }

    /** @test */
    public function user_cannot_log_another_users_meal()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create(['user_id' => $user1->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user1->id,
            'name' => 'User 1 Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $this->actingAs($user2);
        
        $today = \Carbon\Carbon::today();

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => '12:00',
            'meal_date' => $today->toDateString(),
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_log_ingredient_with_date_field()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $today = \Carbon\Carbon::today();

        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 150,
            'logged_at' => '14:30',
            'date' => $today->toDateString(),
            'notes' => 'Afternoon snack',
            'redirect_to' => 'mobile-entry-foods'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods', ['date' => $today->toDateString()]));
        
        // Verify food log was created with correct date and time
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 150,
            'notes' => 'Afternoon snack',
        ]);

        $foodLog = FoodLog::where('user_id', $user->id)->first();
        $this->assertEquals($today->format('Y-m-d'), $foodLog->logged_at->format('Y-m-d'));
        $this->assertEquals('14:30', $foodLog->logged_at->format('H:i'));
    }

    /** @test */
    public function user_can_log_ingredient_without_date_field_defaults_to_today()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);

        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'logged_at' => '12:00',
            'notes' => 'Lunch',
            'redirect_to' => 'mobile-entry-foods'
            // No 'date' field - should default to today
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods'));
        
        // Verify food log was created with today's date
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'notes' => 'Lunch',
        ]);

        $foodLog = FoodLog::where('user_id', $user->id)->first();
        $this->assertEquals(\Carbon\Carbon::today()->format('Y-m-d'), $foodLog->logged_at->format('Y-m-d'));
        $this->assertEquals('12:00', $foodLog->logged_at->format('H:i'));
    }

    /** @test */
    public function user_can_log_ingredient_with_null_date_field_defaults_to_today()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);

        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 75,
            'logged_at' => '09:15',
            'date' => null, // Explicitly null
            'redirect_to' => 'mobile-entry-foods'
        ]);

        $response->assertSessionHasNoErrors();
        
        // Verify food log was created with today's date
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 75,
        ]);

        $foodLog = FoodLog::where('user_id', $user->id)->first();
        $this->assertEquals(\Carbon\Carbon::today()->format('Y-m-d'), $foodLog->logged_at->format('Y-m-d'));
        $this->assertEquals('09:15', $foodLog->logged_at->format('H:i'));
    }

    /** @test */
    public function user_can_log_meal_without_meal_date_field_defaults_to_today()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 1.0,
            'logged_at_meal' => '18:00',
            'notes' => 'Dinner',
            'redirect_to' => 'mobile-entry-foods'
            // No 'meal_date' field - should default to today
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods'));
        
        // Verify food log was created with today's date
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $foodLog = FoodLog::where('user_id', $user->id)->first();
        $this->assertEquals(\Carbon\Carbon::today()->format('Y-m-d'), $foodLog->logged_at->format('Y-m-d'));
        $this->assertEquals('18:00', $foodLog->logged_at->format('H:i'));
        $this->assertStringContainsString('Test Meal', $foodLog->notes);
        $this->assertStringContainsString('Dinner', $foodLog->notes);
    }

    /** @test */
    public function user_can_log_meal_with_null_meal_date_field_defaults_to_today()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Breakfast Bowl'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 80]);

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 0.5,
            'logged_at_meal' => '08:30',
            'meal_date' => null, // Explicitly null
            'redirect_to' => 'mobile-entry-foods'
        ]);

        $response->assertSessionHasNoErrors();
        
        // Verify food log was created with today's date and correct portion
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 40, // 80 * 0.5
        ]);

        $foodLog = FoodLog::where('user_id', $user->id)->first();
        $this->assertEquals(\Carbon\Carbon::today()->format('Y-m-d'), $foodLog->logged_at->format('Y-m-d'));
        $this->assertEquals('08:30', $foodLog->logged_at->format('H:i'));
    }

    /** @test */
    public function ingredient_logging_without_redirect_to_parameter_works()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);

        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'logged_at' => '16:45',
            // No date, no redirect_to
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods'));
        
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
        ]);
    }

    /** @test */
    public function meal_logging_without_redirect_to_parameter_works()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $meal = \App\Models\Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Simple Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 60]);

        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $meal->id,
            'portion' => 2.0,
            'logged_at_meal' => '13:15',
            // No meal_date, no redirect_to
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods'));
        
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 120, // 60 * 2.0
        ]);
    }
}
