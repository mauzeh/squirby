<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodLoggingDirectNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Ingredient $ingredient;
    protected Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Ingredient'
        ]);
        
        $this->meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Meal'
        ]);
        $this->meal->ingredients()->attach($this->ingredient->id, ['quantity' => 100]);
    }

    /** @test */
    public function ingredient_form_page_loads_successfully()
    {
        $response = $this->get(route('food-logs.create-ingredient', $this->ingredient));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        
        // Check for title component
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        $this->assertNotNull($titleComponent);
        $this->assertStringContainsString('Log Ingredient', $titleComponent['data']['main']);
        $this->assertStringContainsString($this->ingredient->name, $titleComponent['data']['main']);
        
        // Check for form component
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
        $this->assertEquals(route('food-logs.store'), $formComponent['data']['formAction']);
    }

    /** @test */
    public function meal_form_page_loads_successfully()
    {
        $response = $this->get(route('food-logs.create-meal', $this->meal));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        
        // Check for title component
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        $this->assertNotNull($titleComponent);
        $this->assertStringContainsString('Log Meal', $titleComponent['data']['main']);
        $this->assertStringContainsString($this->meal->name, $titleComponent['data']['main']);
        
        // Check for form component
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertNotNull($formComponent);
        $this->assertEquals(route('food-logs.add-meal'), $formComponent['data']['formAction']);
    }

    /** @test */
    public function ingredient_form_has_back_button_navigation()
    {
        $response = $this->get(route('food-logs.create-ingredient', $this->ingredient));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertArrayHasKey('backButton', $titleComponent['data']);
        $this->assertEquals(route('mobile-entry.foods'), $titleComponent['data']['backButton']['url']);
    }

    /** @test */
    public function meal_form_has_back_button_navigation()
    {
        $response = $this->get(route('food-logs.create-meal', $this->meal));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertArrayHasKey('backButton', $titleComponent['data']);
        $this->assertEquals(route('mobile-entry.foods'), $titleComponent['data']['backButton']['url']);
    }

    /** @test */
    public function ingredient_form_preserves_date_parameter()
    {
        $date = '2024-01-15';
        $response = $this->get(route('food-logs.create-ingredient', [
            'ingredient' => $this->ingredient,
            'date' => $date
        ]));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertStringContainsString("date={$date}", $titleComponent['data']['backButton']['url']);
        
        // Check form has hidden date field
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertArrayHasKey('date', $formComponent['data']['hiddenFields']);
        $this->assertEquals($date, $formComponent['data']['hiddenFields']['date']);
    }

    /** @test */
    public function meal_form_preserves_date_parameter()
    {
        $date = '2024-01-15';
        $response = $this->get(route('food-logs.create-meal', [
            'meal' => $this->meal,
            'date' => $date
        ]));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertStringContainsString("date={$date}", $titleComponent['data']['backButton']['url']);
        
        // Check form has hidden date field
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $this->assertArrayHasKey('meal_date', $formComponent['data']['hiddenFields']);
        $this->assertEquals($date, $formComponent['data']['hiddenFields']['meal_date']);
    }

    /** @test */
    public function complete_ingredient_logging_workflow()
    {
        // 1. Navigate to ingredient form
        $response = $this->get(route('food-logs.create-ingredient', $this->ingredient));
        $response->assertOk();

        // 2. Submit form
        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 150,
            'logged_at' => '14:30',
            'date' => '2024-01-15',
            'notes' => 'Afternoon snack',
            'redirect_to' => 'mobile-entry-foods'
        ]);

        // 3. Verify redirect back to foods page
        $response->assertRedirect(route('mobile-entry.foods', ['date' => '2024-01-15']));
        
        // 4. Verify food log was created
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 150,
            'notes' => 'Afternoon snack',
        ]);
    }

    /** @test */
    public function complete_meal_logging_workflow()
    {
        // 1. Navigate to meal form
        $response = $this->get(route('food-logs.create-meal', $this->meal));
        $response->assertOk();

        // 2. Submit form
        $response = $this->post(route('food-logs.add-meal'), [
            'meal_id' => $this->meal->id,
            'portion' => 1.5,
            'logged_at_meal' => '12:00',
            'meal_date' => '2024-01-15',
            'notes' => 'Lunch',
            'redirect_to' => 'mobile-entry-foods'
        ]);

        // 3. Verify redirect back to foods page
        $response->assertRedirect(route('mobile-entry.foods', ['date' => '2024-01-15']));
        
        // 4. Verify food log was created with correct portion
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 150, // 100 * 1.5
        ]);
    }

    /** @test */
    public function ingredient_form_returns_404_for_nonexistent_ingredient()
    {
        $response = $this->get(route('food-logs.create-ingredient', 99999));
        $response->assertNotFound();
    }

    /** @test */
    public function meal_form_returns_404_for_nonexistent_meal()
    {
        $response = $this->get(route('food-logs.create-meal', 99999));
        $response->assertNotFound();
    }

    /** @test */
    public function user_cannot_access_another_users_ingredient_form()
    {
        $otherUser = User::factory()->create();
        $otherIngredient = Ingredient::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('food-logs.create-ingredient', $otherIngredient));
        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_access_another_users_meal_form()
    {
        $otherUser = User::factory()->create();
        $otherMeal = Meal::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get(route('food-logs.create-meal', $otherMeal));
        $response->assertForbidden();
    }

    /** @test */
    public function ingredient_form_includes_hidden_time_field()
    {
        $response = $this->get(route('food-logs.create-ingredient', $this->ingredient));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        // Check for hidden time field (should be set to current time rounded to 15 minutes)
        $this->assertArrayHasKey('logged_at', $formComponent['data']['hiddenFields']);
        $this->assertNotEmpty($formComponent['data']['hiddenFields']['logged_at']);
    }

    /** @test */
    public function meal_form_includes_hidden_time_field()
    {
        $response = $this->get(route('food-logs.create-meal', $this->meal));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        // Check for hidden time field
        $this->assertArrayHasKey('logged_at_meal', $formComponent['data']['hiddenFields']);
        $this->assertNotEmpty($formComponent['data']['hiddenFields']['logged_at_meal']);
    }

    /** @test */
    public function redirect_to_parameter_is_preserved_through_workflow()
    {
        $response = $this->get(route('food-logs.create-ingredient', [
            'ingredient' => $this->ingredient,
            'redirect_to' => 'mobile-entry.foods'
        ]));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        // Check for hidden redirect_to field
        $this->assertArrayHasKey('redirect_to', $formComponent['data']['hiddenFields']);
        $this->assertEquals('mobile-entry.foods', $formComponent['data']['hiddenFields']['redirect_to']);
    }

    /** @test */
    public function forms_work_without_date_parameter_defaulting_to_today()
    {
        $response = $this->get(route('food-logs.create-ingredient', $this->ingredient));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        // Date field should default to today
        $this->assertArrayHasKey('date', $formComponent['data']['hiddenFields']);
        $this->assertEquals(now()->format('Y-m-d'), $formComponent['data']['hiddenFields']['date']);
    }
}