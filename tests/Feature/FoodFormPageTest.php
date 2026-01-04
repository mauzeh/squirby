<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodFormPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function ingredient_form_page_displays_correct_title_structure()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast'
        ]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertNotNull($titleComponent);
        $this->assertEquals('Log Ingredient: Chicken Breast', $titleComponent['data']['main']);
        $this->assertArrayHasKey('backButton', $titleComponent['data']);
    }

    /** @test */
    public function meal_form_page_displays_correct_title_structure()
    {
        $meal = Meal::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Protein Shake'
        ]);

        $response = $this->get(route('food-logs.create-meal', $meal));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertNotNull($titleComponent);
        $this->assertEquals('Log Meal: Protein Shake', $titleComponent['data']['main']);
        $this->assertArrayHasKey('backButton', $titleComponent['data']);
    }

    /** @test */
    public function back_button_generates_correct_url_without_date()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertEquals(route('mobile-entry.foods'), $titleComponent['data']['backButton']['url']);
    }

    /** @test */
    public function back_button_generates_correct_url_with_date()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);
        $date = '2024-01-15';

        $response = $this->get(route('food-logs.create-ingredient', [
            'ingredient' => $ingredient,
            'date' => $date
        ]));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $expectedUrl = route('mobile-entry.foods', ['date' => $date]);
        $this->assertEquals($expectedUrl, $titleComponent['data']['backButton']['url']);
    }

    /** @test */
    public function ingredient_form_has_correct_action_and_method()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        $this->assertNotNull($formComponent);
        $this->assertEquals(route('food-logs.store'), $formComponent['data']['formAction']);
    }

    /** @test */
    public function meal_form_has_correct_action_and_method()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-meal', $meal));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        $this->assertNotNull($formComponent);
        $this->assertEquals(route('food-logs.add-meal'), $formComponent['data']['formAction']);
    }

    /** @test */
    public function ingredient_form_includes_required_hidden_fields()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);
        $date = '2024-01-15';

        $response = $this->get(route('food-logs.create-ingredient', [
            'ingredient' => $ingredient,
            'date' => $date,
            'redirect_to' => 'mobile-entry.foods'
        ]));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $hiddenFields = $formComponent['data']['hiddenFields'];
        
        // Check ingredient_id field
        $this->assertArrayHasKey('ingredient_id', $hiddenFields);
        $this->assertEquals($ingredient->id, $hiddenFields['ingredient_id']);
        
        // Check date field
        $this->assertArrayHasKey('date', $hiddenFields);
        $this->assertEquals($date, $hiddenFields['date']);
        
        // Check redirect_to field
        $this->assertArrayHasKey('redirect_to', $hiddenFields);
        $this->assertEquals('mobile-entry.foods', $hiddenFields['redirect_to']);
        
        // Check logged_at field (time)
        $this->assertArrayHasKey('logged_at', $hiddenFields);
        $this->assertNotEmpty($hiddenFields['logged_at']);
    }

    /** @test */
    public function meal_form_includes_required_hidden_fields()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $date = '2024-01-15';

        $response = $this->get(route('food-logs.create-meal', [
            'meal' => $meal,
            'date' => $date,
            'redirect_to' => 'mobile-entry.foods'
        ]));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $hiddenFields = $formComponent['data']['hiddenFields'];
        
        // Check meal_id field
        $this->assertArrayHasKey('meal_id', $hiddenFields);
        $this->assertEquals($meal->id, $hiddenFields['meal_id']);
        
        // Check meal_date field
        $this->assertArrayHasKey('meal_date', $hiddenFields);
        $this->assertEquals($date, $hiddenFields['meal_date']);
        
        // Check redirect_to field
        $this->assertArrayHasKey('redirect_to', $hiddenFields);
        $this->assertEquals('mobile-entry.foods', $hiddenFields['redirect_to']);
        
        // Check logged_at_meal field (time)
        $this->assertArrayHasKey('logged_at_meal', $hiddenFields);
        $this->assertNotEmpty($hiddenFields['logged_at_meal']);
    }

    /** @test */
    public function ingredient_form_defaults_date_to_today_when_not_provided()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $hiddenFields = $formComponent['data']['hiddenFields'];
        
        $this->assertArrayHasKey('date', $hiddenFields);
        $this->assertEquals(now()->format('Y-m-d'), $hiddenFields['date']);
    }

    /** @test */
    public function meal_form_defaults_date_to_today_when_not_provided()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-meal', $meal));

        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        $hiddenFields = $formComponent['data']['hiddenFields'];
        
        $this->assertArrayHasKey('meal_date', $hiddenFields);
        $this->assertEquals(now()->format('Y-m-d'), $hiddenFields['meal_date']);
    }

    /** @test */
    public function ingredient_form_handles_missing_ingredient_base_unit_gracefully()
    {
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => null // No base unit
        ]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $response->assertOk();
        $data = $response->viewData('data');
        $formComponent = collect($data['components'])->firstWhere('type', 'form');
        
        $this->assertNotNull($formComponent);
        // Form should still render even without base unit
    }

    /** @test */
    public function unauthenticated_user_cannot_access_ingredient_form()
    {
        $this->app['auth']->logout();
        
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-ingredient', $ingredient));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_meal_form()
    {
        $this->app['auth']->logout();
        
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('food-logs.create-meal', $meal));

        $response->assertRedirect(route('login'));
    }
}