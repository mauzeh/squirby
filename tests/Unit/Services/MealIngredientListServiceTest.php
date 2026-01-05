<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MealIngredientListService;
use App\Services\NutritionService;
use App\Models\Meal;
use App\Models\Ingredient;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class MealIngredientListServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $nutritionService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->nutritionService = $this->createMock(NutritionService::class);
        
        $this->service = new MealIngredientListService($this->nutritionService);

        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_generates_empty_message_for_meal_with_no_ingredients()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);

        $result = $this->service->generateIngredientListTable($meal);

        $this->assertEquals('messages', $result['type']);
        $this->assertEquals('Add ingredients above to build your meal.', $result['data']['messages'][0]['text']);
    }

    public function test_ingredient_list_table_handles_disabled_buttons()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id, 'name' => 'Chicken Breast']);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $result = $this->service->generateIngredientListTable($meal, [
            'showEditButtons' => false,
            'showDeleteButtons' => false
        ]);

        $row = $result['data']['rows'][0];
        $this->assertEmpty($row['actions'] ?? []);
    }

    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(MealIngredientListService::class, $this->service);
    }

    public function test_service_has_nutrition_service_dependency()
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('nutritionService');
        $property->setAccessible(true);
        
        $this->assertInstanceOf(NutritionService::class, $property->getValue($this->service));
    }

    public function test_ingredient_list_table_shows_ingredient_data()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $unit = Unit::factory()->create(['name' => 'grams']);
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_unit_id' => $unit->id
        ]);
        
        // Add ingredient to meal
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $result = $this->service->generateIngredientListTable($meal, [
            'showEditButtons' => false,
            'showDeleteButtons' => false
        ]);

        $this->assertEquals('table', $result['type']);
        $this->assertCount(1, $result['data']['rows']);
        
        $row = $result['data']['rows'][0];
        $this->assertEquals('Chicken Breast', $row['line1']);
        $this->assertEquals('100 grams', $row['line2']);
    }

    public function test_ingredient_list_table_handles_ingredient_without_unit()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_unit_id' => null
        ]);
        
        // Add ingredient to meal
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $result = $this->service->generateIngredientListTable($meal, [
            'showEditButtons' => false,
            'showDeleteButtons' => false
        ]);

        $row = $result['data']['rows'][0];
        $this->assertEquals('Chicken Breast', $row['line1']);
        $this->assertEquals('100 ', $row['line2']); // No unit name
    }

    public function test_ingredient_list_table_handles_compact_mode()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id, 'name' => 'Chicken Breast']);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $result = $this->service->generateIngredientListTable($meal, [
            'compactMode' => true,
            'showEditButtons' => false,
            'showDeleteButtons' => false
        ]);

        $row = $result['data']['rows'][0];
        $this->assertTrue($row['compact']);
    }

    public function test_ingredient_list_table_shows_multiple_ingredients()
    {
        $meal = Meal::factory()->create(['user_id' => $this->user->id]);
        $unit = Unit::factory()->create(['name' => 'grams']);
        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_unit_id' => $unit->id
        ]);
        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Rice',
            'base_unit_id' => $unit->id
        ]);
        
        // Add ingredients to meal
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 50]);

        $result = $this->service->generateIngredientListTable($meal, [
            'showEditButtons' => false,
            'showDeleteButtons' => false
        ]);

        $this->assertEquals('table', $result['type']);
        $this->assertCount(2, $result['data']['rows']);
        
        $rows = $result['data']['rows'];
        $this->assertEquals('Chicken Breast', $rows[0]['line1']);
        $this->assertEquals('100 grams', $rows[0]['line2']);
        $this->assertEquals('Rice', $rows[1]['line1']);
        $this->assertEquals('50 grams', $rows[1]['line2']);
    }
}