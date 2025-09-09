<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\NutritionService;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\FoodLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NutritionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $nutritionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nutritionService = new NutritionService();

        // Seed necessary units
        Unit::create(['name' => 'grams', 'abbreviation' => 'g', 'conversion_factor' => 1.0]);
        Unit::create(['name' => 'pieces', 'abbreviation' => 'pc', 'conversion_factor' => 1.0]);
    }

    /** @test */
    public function it_calculates_total_macro_for_calories_correctly()
    {
        $ingredient = Ingredient::create([
            'name' => 'Test Food',
            'calories' => 165, // Calories are calculated from macros
            'protein' => 10,
            'carbs' => 20,
            'added_sugars' => 5,
            'fats' => 5,
            'sodium' => 0,
            'iron' => 0,
            'potassium' => 0,
            'base_quantity' => 100, // per 100g
            'base_unit_id' => Unit::where('abbreviation', 'g')->first()->id,
            'cost_per_unit' => 1.00,
        ]);

        // For 100g: (9*5) + (4*20) + (4*10) = 45 + 80 + 40 = 165 calories
        $this->assertEquals(165, $this->nutritionService->calculateTotalMacro($ingredient, 'calories', 100));
        // For 50g: 165 / 2 = 82.5 calories
        $this->assertEquals(82.5, $this->nutritionService->calculateTotalMacro($ingredient, 'calories', 50));
    }

    /** @test */
    public function it_calculates_total_macro_for_other_nutrients_correctly()
    {
        $ingredient = Ingredient::create([
            'name' => 'Test Food',
            'calories' => 0,
            'protein' => 10,
            'carbs' => 20,
            'added_sugars' => 5,
            'fats' => 5,
            'sodium' => 100,
            'iron' => 1,
            'potassium' => 200,
            'base_quantity' => 100, // per 100g
            'base_unit_id' => Unit::where('abbreviation', 'g')->first()->id,
            'cost_per_unit' => 1.00,
        ]);

        // For 100g
        $this->assertEquals(10, $this->nutritionService->calculateTotalMacro($ingredient, 'protein', 100));
        $this->assertEquals(20, $this->nutritionService->calculateTotalMacro($ingredient, 'carbs', 100));
        $this->assertEquals(5, $this->nutritionService->calculateTotalMacro($ingredient, 'fats', 100));
        $this->assertEquals(100, $this->nutritionService->calculateTotalMacro($ingredient, 'sodium', 100));
        $this->assertEquals(1, $this->nutritionService->calculateTotalMacro($ingredient, 'iron', 100));
        $this->assertEquals(200, $this->nutritionService->calculateTotalMacro($ingredient, 'potassium', 100));
        $this->assertEquals(5, $this->nutritionService->calculateTotalMacro($ingredient, 'added_sugars', 100));

        // For 50g
        $this->assertEquals(5, $this->nutritionService->calculateTotalMacro($ingredient, 'protein', 50));
        $this->assertEquals(10, $this->nutritionService->calculateTotalMacro($ingredient, 'carbs', 50));
        $this->assertEquals(2.5, $this->nutritionService->calculateTotalMacro($ingredient, 'fats', 50));
        $this->assertEquals(50, $this->nutritionService->calculateTotalMacro($ingredient, 'sodium', 50));
        $this->assertEquals(0.5, $this->nutritionService->calculateTotalMacro($ingredient, 'iron', 50));
        $this->assertEquals(100, $this->nutritionService->calculateTotalMacro($ingredient, 'potassium', 50));
        $this->assertEquals(2.5, $this->nutritionService->calculateTotalMacro($ingredient, 'added_sugars', 50));
    }

    /** @test */
    public function it_calculates_cost_for_quantity_correctly()
    {
        $ingredient = Ingredient::create([
            'name' => 'Test Cost Food',
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'added_sugars' => 0, 'fats' => 0,
            'sodium' => 0, 'iron' => 0, 'potassium' => 0,
            'base_quantity' => 100, // per 100g
            'base_unit_id' => Unit::where('abbreviation', 'g')->first()->id,
            'cost_per_unit' => 2.50, // $2.50 per 100g
        ]);

        // For 100g: $2.50
        $this->assertEquals(2.50, $this->nutritionService->calculateCostForQuantity($ingredient, 100));
        // For 50g: $1.25
        $this->assertEquals(1.25, $this->nutritionService->calculateCostForQuantity($ingredient, 50));
        // For 200g: $5.00
        $this->assertEquals(5.00, $this->nutritionService->calculateCostForQuantity($ingredient, 200));
    }

    /** @test */
    public function it_calculates_daily_totals_from_food_logs_correctly()
    {
        $gUnit = Unit::where('abbreviation', 'g')->first();
        $pcUnit = Unit::where('abbreviation', 'pc')->first();

        $ingredient1 = Ingredient::create([
            'name' => 'Food A',
            'calories' => 165, 'protein' => 10, 'carbs' => 20, 'added_sugars' => 5, 'fats' => 5,
            'sodium' => 100, 'iron' => 1, 'potassium' => 200,
            'base_quantity' => 100, 'base_unit_id' => $gUnit->id, 'cost_per_unit' => 1.00,
        ]); // 100g: 165 cal, 10p, 20c, 5f, $1.00

        $ingredient2 = Ingredient::create([
            'name' => 'Food B',
            'calories' => 210, 'protein' => 20, 'carbs' => 10, 'added_sugars' => 2, 'fats' => 10,
            'sodium' => 50, 'iron' => 0.5, 'potassium' => 100,
            'base_quantity' => 1, 'base_unit_id' => $pcUnit->id, 'cost_per_unit' => 0.50,
        ]); // 1pc: 180 cal, 20p, 10c, 10f, $0.50

        FoodLog::create([
            'ingredient_id' => $ingredient1->id,
            'unit_id' => $gUnit->id,
            'quantity' => 100,
            'logged_at' => now(),
        ]); // 100g of Food A

        FoodLog::create([
            'ingredient_id' => $ingredient2->id,
            'unit_id' => $pcUnit->id,
            'quantity' => 2,
            'logged_at' => now(),
        ]); // 2pc of Food B

        $foodLogs = FoodLog::with('ingredient')->get();
        $totals = $this->nutritionService->calculateFoodLogTotals($foodLogs);

        // Expected totals:
        // Calories: 165 (Food A) + (210 * 2) (Food B) = 165 + 420 = 585
        $this->assertEquals(585, $totals['calories']);
        // Protein: 10 (Food A) + (20 * 2) (Food B) = 10 + 40 = 50
        $this->assertEquals(50, $totals['protein']);
        // Carbs: 20 (Food A) + (10 * 2) (Food B) = 20 + 20 = 40
        $this->assertEquals(40, $totals['carbs']);
        // Fats: 5 (Food A) + (10 * 2) (Food B) = 5 + 20 = 25
        $this->assertEquals(25, $totals['fats']);
        // Cost: 1.00 (Food A) + (0.50 * 2) (Food B) = 1.00 + 1.00 = 2.00
        $this->assertEquals(2.00, $totals['cost']);
    }

    /** @test */
    public function it_calculates_food_log_totals_from_ingredients_collection_correctly()
    {
        $gUnit = Unit::where('abbreviation', 'g')->first();
        $pcUnit = Unit::where('abbreviation', 'pc')->first();

        $ingredient1 = Ingredient::create([
            'name' => 'Food X',
            'calories' => 232, 'protein' => 15, 'carbs' => 25, 'added_sugars' => 10, 'fats' => 8,
            'sodium' => 200, 'iron' => 2, 'potassium' => 300,
            'base_quantity' => 100, 'base_unit_id' => $gUnit->id, 'cost_per_unit' => 3.00,
        ]); // 100g: 208 cal, 15p, 25c, 8f, $3.00

        $ingredient2 = Ingredient::create([
            'name' => 'Food Y',
            'calories' => 58, 'protein' => 5, 'carbs' => 5, 'added_sugars' => 1, 'fats' => 2,
            'sodium' => 10, 'iron' => 0.1, 'potassium' => 20,
            'base_quantity' => 1, 'base_unit_id' => $pcUnit->id, 'cost_per_unit' => 0.20,
        ]); // 1pc: 48 cal, 5p, 5c, 2f, $0.20

        // Simulate meal ingredients with pivot quantities
        $mealIngredients = collect([
            $ingredient1->setAttribute('pivot', (object)['quantity' => 50]), // 50g of Food X
            $ingredient2->setAttribute('pivot', (object)['quantity' => 3]),  // 3pc of Food Y
        ]);

        $totals = $this->nutritionService->calculateFoodLogTotals($mealIngredients);

        // Expected totals:
        // Food X (50g): 116 cal, 7.5p, 12.5c, 4f, $1.50
        // Food Y (3pc): 174 cal, 15p, 15c, 6f, $0.60
        // Total Calories: 116 + 174 = 290
        $this->assertEquals(290, $totals['calories']);
        // Total Protein: 7.5 + 15 = 22.5
        $this->assertEquals(22.5, $totals['protein']);
        // Total Carbs: 12.5 + 15 = 27.5
        $this->assertEquals(27.5, $totals['carbs']);
        // Total Fats: 4 + 6 = 10
        $this->assertEquals(10, $totals['fats']);
        // Total Cost: 1.50 + 0.60 = 2.10
        $this->assertEquals(2.10, $totals['cost']);
    }

    /** @test */
    public function it_handles_empty_collections_in_calculate_food_log_totals()
    {
        $foodLogs = collect();
        $totals = $this->nutritionService->calculateFoodLogTotals($foodLogs);

        $expectedTotals = [
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'added_sugars' => 0, 'fats' => 0,
            'sodium' => 0, 'iron' => 0, 'potassium' => 0, 'cost' => 0, 'fiber' => 0, 'calcium' => 0, 'caffeine' => 0,
        ];

        $this->assertEquals($expectedTotals, $totals);
    }
}
