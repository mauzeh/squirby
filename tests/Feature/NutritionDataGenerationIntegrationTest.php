<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\RealisticVariationService;
use App\Models\User;
use App\Models\Unit;
use App\Models\Ingredient;
use App\Models\FoodLog;
use App\Models\Meal;
use App\Models\MealIngredient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NutritionDataGenerationIntegrationTest extends TestCase
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
        
        // Seed basic units
        $this->artisan('db:seed', ['--class' => 'UnitSeeder']);
    }

    public function test_can_generate_and_store_nutrition_data()
    {
        $date = Carbon::now();
        $weight = 180.0;
        $weekNumber = 1;
        
        // Generate nutrition data
        $nutritionData = $this->generator->generateDayNutritionData($date, $weight, $weekNumber, $this->testUser->id);
        
        // Apply variations
        $variatedData = $this->variationService->applyNutritionVariations($nutritionData, $date);
        
        // Create meals and food logs from the generated data
        $createdMeals = [];
        $createdFoodLogs = [];
        
        foreach ($variatedData['meals'] as $mealType => $mealData) {
            if (isset($mealData['skipped']) && $mealData['skipped']) {
                continue; // Skip meals that were marked as skipped
            }
            
            // Create meal
            $meal = Meal::create([
                'name' => ucfirst($mealType) . ' - ' . $date->format('Y-m-d'),
                'user_id' => $this->testUser->id,
                'comments' => 'Generated meal for transformation seeder'
            ]);
            
            $createdMeals[] = $meal;
            
            // Create ingredients and food logs
            foreach ($mealData['ingredients'] as $ingredientData) {
                // Ensure ingredient exists
                $ingredient = $this->generator->ensureIngredientExists($ingredientData['name'], $this->testUser->id);
                
                // Get appropriate unit
                $unit = Unit::where('abbreviation', $ingredientData['unit'])->first();
                if (!$unit) {
                    $unit = Unit::where('abbreviation', 'g')->first();
                }
                
                // Create food log entry
                $foodLog = FoodLog::create([
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $unit->id,
                    'quantity' => $ingredientData['quantity'],
                    'logged_at' => $mealData['time'],
                    'user_id' => $this->testUser->id,
                    'notes' => "Part of {$mealType} meal"
                ]);
                
                $createdFoodLogs[] = $foodLog;
                
                // Create meal ingredient relationship
                MealIngredient::create([
                    'meal_id' => $meal->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $ingredientData['quantity']
                ]);
            }
        }
        
        // Verify data was created
        $this->assertNotEmpty($createdMeals);
        $this->assertNotEmpty($createdFoodLogs);
        
        // Verify database records
        $this->assertDatabaseHas('meals', [
            'user_id' => $this->testUser->id
        ]);
        
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->testUser->id
        ]);
        
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->testUser->id
        ]);
        
        // Verify relationships work
        $meal = $createdMeals[0];
        $this->assertGreaterThan(0, $meal->ingredients()->count());
        
        $foodLog = $createdFoodLogs[0];
        $this->assertNotNull($foodLog->ingredient);
        $this->assertNotNull($foodLog->unit);
        $this->assertEquals($this->testUser->id, $foodLog->user_id);
    }

    public function test_fallback_ingredients_are_created_with_valid_nutrition()
    {
        $ingredientNames = ['Chicken Breast', 'Rice', 'Broccoli', 'Olive Oil'];
        
        foreach ($ingredientNames as $name) {
            $ingredient = $this->generator->ensureIngredientExists($name, $this->testUser->id);
            
            $this->assertNotNull($ingredient);
            $this->assertEquals($name, $ingredient->name);
            $this->assertEquals($this->testUser->id, $ingredient->user_id);
            
            // Verify nutritional values are reasonable
            $this->assertGreaterThanOrEqual(0, $ingredient->protein);
            $this->assertGreaterThanOrEqual(0, $ingredient->carbs);
            $this->assertGreaterThanOrEqual(0, $ingredient->fats);
            
            // Verify calories calculation works
            $calculatedCalories = ($ingredient->protein * 4) + ($ingredient->carbs * 4) + ($ingredient->fats * 9);
            $this->assertEquals($calculatedCalories, $ingredient->calories);
            
            // Verify unit relationship
            $this->assertNotNull($ingredient->baseUnit);
        }
    }

    public function test_refeed_day_generates_higher_calories()
    {
        $date = Carbon::now();
        $weight = 180.0;
        
        // Generate multiple days to test refeed logic
        $regularDayCalories = [];
        $refeedDayCalories = [];
        
        for ($i = 0; $i < 20; $i++) {
            $testDate = $date->copy()->addDays($i);
            $weekNumber = (int) ceil(($i + 1) / 7);
            
            $nutritionData = $this->generator->generateDayNutritionData($testDate, $weight, $weekNumber, $this->testUser->id);
            
            if ($nutritionData['is_refeed_day']) {
                $refeedDayCalories[] = $nutritionData['target_calories'];
            } else {
                $regularDayCalories[] = $nutritionData['target_calories'];
            }
        }
        
        // Should have both regular and refeed days
        $this->assertNotEmpty($regularDayCalories);
        
        // If we have refeed days, they should have higher calories on average
        if (!empty($refeedDayCalories)) {
            $avgRegularCalories = array_sum($regularDayCalories) / count($regularDayCalories);
            $avgRefeedCalories = array_sum($refeedDayCalories) / count($refeedDayCalories);
            
            $this->assertGreaterThan($avgRegularCalories, $avgRefeedCalories);
        }
    }

    public function test_meal_timing_is_realistic()
    {
        $date = Carbon::now();
        $weight = 180.0;
        $weekNumber = 1;
        
        $nutritionData = $this->generator->generateDayNutritionData($date, $weight, $weekNumber, $this->testUser->id);
        
        $mealTimes = [];
        foreach ($nutritionData['meals'] as $mealType => $mealData) {
            $mealTimes[$mealType] = $mealData['time']->format('H:i');
        }
        
        // Verify meal times are in logical order
        $breakfastTime = Carbon::createFromFormat('H:i', $mealTimes['breakfast']);
        $lunchTime = Carbon::createFromFormat('H:i', $mealTimes['lunch']);
        $dinnerTime = Carbon::createFromFormat('H:i', $mealTimes['dinner']);
        
        $this->assertTrue($breakfastTime->lt($lunchTime));
        $this->assertTrue($lunchTime->lt($dinnerTime));
        
        // Verify times are reasonable
        $this->assertGreaterThanOrEqual(6, $breakfastTime->hour); // After 6 AM
        $this->assertLessThanOrEqual(10, $breakfastTime->hour);   // Before 10 AM
        
        $this->assertGreaterThanOrEqual(11, $lunchTime->hour);    // After 11 AM
        $this->assertLessThanOrEqual(14, $lunchTime->hour);       // Before 2 PM
        
        $this->assertGreaterThanOrEqual(17, $dinnerTime->hour);   // After 5 PM
        $this->assertLessThanOrEqual(20, $dinnerTime->hour);      // Before 8 PM
    }
}