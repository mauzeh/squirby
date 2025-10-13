<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\RealisticVariationService;
use App\Models\User;
use App\Models\Unit;
use App\Models\Ingredient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransformationDataGeneratorNutritionTest extends TestCase
{
    use RefreshDatabase;

    private TransformationDataGenerator $generator;
    private RealisticVariationService $variationService;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new TransformationDataGenerator();
        $this->variationService = new RealisticVariationService();
        
        // Create test user
        $this->testUser = User::factory()->create();
        
        // Create basic units
        Unit::create(['name' => 'Gram', 'abbreviation' => 'g', 'conversion_factor' => 1.0]);
        Unit::create(['name' => 'Piece', 'abbreviation' => 'pc', 'conversion_factor' => 1.0]);
        Unit::create(['name' => 'Tablespoon', 'abbreviation' => 'tbsp', 'conversion_factor' => 15.0]);
        Unit::create(['name' => 'Milliliter', 'abbreviation' => 'ml', 'conversion_factor' => 1.0]);
    }

    public function test_daily_calorie_calculation()
    {
        $weight = 180.0;
        
        // Test weight loss calories
        $weightLossCalories = $this->generator->calculateDailyCalories($weight, 'weight_loss');
        $this->assertIsInt($weightLossCalories);
        $this->assertGreaterThan(1500, $weightLossCalories);
        $this->assertLessThan(2500, $weightLossCalories);
        
        // Test maintenance calories
        $maintenanceCalories = $this->generator->calculateDailyCalories($weight, 'maintenance');
        $this->assertGreaterThan($weightLossCalories, $maintenanceCalories);
        
        // Test muscle gain calories
        $gainCalories = $this->generator->calculateDailyCalories($weight, 'muscle_gain');
        $this->assertGreaterThan($maintenanceCalories, $gainCalories);
    }

    public function test_meal_plan_generation()
    {
        $targetCalories = 2000;
        $date = Carbon::now();
        
        $mealPlan = $this->generator->generateMealPlan($targetCalories, $date);
        
        // Check that all expected meals are present
        $this->assertArrayHasKey('breakfast', $mealPlan);
        $this->assertArrayHasKey('lunch', $mealPlan);
        $this->assertArrayHasKey('dinner', $mealPlan);
        $this->assertArrayHasKey('snacks', $mealPlan);
        
        // Check that calories roughly add up to target
        $totalCalories = 0;
        foreach ($mealPlan as $meal) {
            $this->assertArrayHasKey('calories', $meal);
            $this->assertArrayHasKey('time', $meal);
            $this->assertArrayHasKey('ingredients', $meal);
            $totalCalories += $meal['calories'];
        }
        
        $this->assertEqualsWithDelta($targetCalories, $totalCalories, 50);
    }

    public function test_meal_ingredients_generation()
    {
        $ingredients = $this->generator->generateMealIngredients('breakfast', 500);
        
        $this->assertIsArray($ingredients);
        $this->assertNotEmpty($ingredients);
        
        foreach ($ingredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('quantity', $ingredient);
            $this->assertArrayHasKey('unit', $ingredient);
            $this->assertArrayHasKey('calories', $ingredient);
            $this->assertGreaterThan(0, $ingredient['quantity']);
            $this->assertGreaterThan(0, $ingredient['calories']);
        }
    }

    public function test_refeed_day_logic()
    {
        $date = Carbon::now();
        
        // Test refeed day detection
        $isRefeedDay = $this->generator->isRefeedDay($date, 1);
        $this->assertIsBool($isRefeedDay);
        
        // Test refeed calories calculation
        $refeedCalories = $this->generator->calculateRefeedCalories(180.0);
        $this->assertIsInt($refeedCalories);
        $this->assertGreaterThan(2000, $refeedCalories);
    }

    public function test_day_nutrition_data_generation()
    {
        $date = Carbon::now();
        $weight = 180.0;
        $weekNumber = 1;
        
        $nutritionData = $this->generator->generateDayNutritionData($date, $weight, $weekNumber, $this->testUser->id);
        
        $this->assertArrayHasKey('date', $nutritionData);
        $this->assertArrayHasKey('target_calories', $nutritionData);
        $this->assertArrayHasKey('is_refeed_day', $nutritionData);
        $this->assertArrayHasKey('meals', $nutritionData);
        $this->assertArrayHasKey('user_id', $nutritionData);
        
        $this->assertEquals($this->testUser->id, $nutritionData['user_id']);
        $this->assertIsBool($nutritionData['is_refeed_day']);
        $this->assertIsInt($nutritionData['target_calories']);
    }

    public function test_fallback_ingredient_creation()
    {
        $ingredientName = 'Chicken Breast';
        
        // Ensure ingredient doesn't exist
        $this->assertDatabaseMissing('ingredients', ['name' => $ingredientName]);
        
        $ingredient = $this->generator->ensureIngredientExists($ingredientName, $this->testUser->id);
        
        $this->assertNotNull($ingredient);
        $this->assertEquals($ingredientName, $ingredient->name);
        $this->assertEquals($this->testUser->id, $ingredient->user_id);
        $this->assertGreaterThan(0, $ingredient->protein);
        
        // Verify it was created in database
        $this->assertDatabaseHas('ingredients', [
            'name' => $ingredientName,
            'user_id' => $this->testUser->id
        ]);
    }

    public function test_nutrition_variations()
    {
        $date = Carbon::now();
        $nutritionData = [
            'target_calories' => 2000,
            'meals' => [
                'breakfast' => [
                    'calories' => 500,
                    'time' => $date->copy()->setTime(7, 30),
                    'ingredients' => [
                        ['name' => 'Oats', 'quantity' => 50, 'unit' => 'g', 'calories' => 200],
                        ['name' => 'Banana', 'quantity' => 1, 'unit' => 'pc', 'calories' => 89]
                    ]
                ]
            ]
        ];
        
        $variatedData = $this->variationService->applyNutritionVariations($nutritionData, $date);
        
        $this->assertArrayHasKey('target_calories', $variatedData);
        $this->assertArrayHasKey('meals', $variatedData);
        
        // Calories should be varied but still reasonable
        $this->assertNotEquals($nutritionData['target_calories'], $variatedData['target_calories']);
        $this->assertGreaterThan(1500, $variatedData['target_calories']);
        $this->assertLessThan(2500, $variatedData['target_calories']);
    }

    public function test_calorie_variation()
    {
        $baseCalories = 2000;
        $variatedCalories = $this->variationService->addCalorieVariation($baseCalories, 10.0);
        
        $this->assertIsInt($variatedCalories);
        $this->assertNotEquals($baseCalories, $variatedCalories);
        $this->assertGreaterThan($baseCalories * 0.7, $variatedCalories); // Should not go below 70%
    }

    public function test_portion_variation()
    {
        $ingredients = [
            ['name' => 'Chicken Breast', 'quantity' => 150, 'unit' => 'g', 'calories' => 248],
            ['name' => 'Rice', 'quantity' => 100, 'unit' => 'g', 'calories' => 130]
        ];
        
        $variatedIngredients = $this->variationService->addPortionVariation($ingredients, 15.0);
        
        $this->assertCount(2, $variatedIngredients);
        
        foreach ($variatedIngredients as $i => $ingredient) {
            $this->assertArrayHasKey('quantity', $ingredient);
            $this->assertArrayHasKey('calories', $ingredient);
            
            // Quantities should be varied
            $this->assertNotEquals($ingredients[$i]['quantity'], $ingredient['quantity']);
            
            // Should not be less than 10% of original
            $this->assertGreaterThan($ingredients[$i]['quantity'] * 0.1, $ingredient['quantity']);
        }
    }
}