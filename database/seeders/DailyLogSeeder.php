<?php

namespace Database\Seeders;

use App\Models\DailyLog;
use App\Models\Ingredient;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DailyLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ingredients = Ingredient::all()->keyBy('name');

        if ($ingredients->isEmpty()) {
            $this->command->info('No ingredients found. Please run the IngredientSeeder first.');
            return;
        }

        DailyLog::truncate();

        // Always create logs for today and tomorrow
        $this->createMealPlanForDate(Carbon::today(), $ingredients);
        $this->createMealPlanForDate(Carbon::tomorrow(), $ingredients);

        // Create logs for the past 28 days
        $startDate = Carbon::now()->subDays(28);

        for ($i = 0; $i < 28; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $this->createMealPlanForDate($currentDate, $ingredients);
        }
    }

    private function createMealPlanForDate(Carbon $date, $ingredients)
    {
        // Breakfast
        $this->createBreakfast($date, $ingredients);

        // Lunch
        $this->createLunch($date, $ingredients);

        // Dinner
        $this->createDinner($date, $ingredients);

        // Snack
        if (rand(0, 1)) {
            $this->createSnack($date, $ingredients);
        }
    }

    private function createBreakfast(Carbon $date, $ingredients)
    {
        $breakfastTime = $date->copy()->setHour(rand(7, 9))->setMinute(rand(0, 59));
        $mealType = rand(1, 3);

        switch ($mealType) {
            case 1: // Oats with milk and fruits
                $this->logIngredient($ingredients, 'Oats', $breakfastTime, [40, 60]);
                $this->logIngredient($ingredients, 'Milk (2%, Clover Sonoma)', $breakfastTime, [150, 250]);
                $this->logIngredient($ingredients, 'Blueberries (fresh)', $breakfastTime, [50, 100]);
                break;
            case 2: // Eggs with bacon and toast
                $this->logIngredient($ingredients, 'Egg (L) whole', $breakfastTime, [2, 3]);
                $this->logIngredient($ingredients, 'Bacon', $breakfastTime, [20, 40]);
                $this->logIngredient($ingredients, 'Organic Whole Wheat Bread (Trader Joe\'s)', $breakfastTime, [1, 2]);
                break;
            case 3: // Yogurt with granola
                $this->logIngredient($ingredients, 'Greek Yogurt (0% Fat - Trader Joe\'s)', $breakfastTime, [150, 200]);
                $this->logIngredient($ingredients, 'Granola (Chocolate Coffee, Trader Joe\'s)', $breakfastTime, [30, 50]);
                break;
        }
    }

    private function createLunch(Carbon $date, $ingredients)
    {
        $lunchTime = $date->copy()->setHour(rand(12, 14))->setMinute(rand(0, 59));
        $mealType = rand(1, 3);

        switch ($mealType) {
            case 1: // Salad with chicken
                $this->logIngredient($ingredients, 'Chicken Breast', $lunchTime, [100, 150]);
                $this->logIngredient($ingredients, 'Spinach', $lunchTime, [50, 100]);
                $this->logIngredient($ingredients, 'Bell Pepper (Fresh)', $lunchTime, [30, 60]);
                $this->logIngredient($ingredients, 'Olive oil', $lunchTime, [10, 15]);
                break;
            case 2: // Sandwich
                $this->logIngredient($ingredients, 'Organic Whole Wheat Bread (Trader Joe\'s)', $lunchTime, [2, 3]);
                $this->logIngredient($ingredients, 'Turkey (Sliced, Honey Roasted)', $lunchTime, [50, 100]);
                $this->logIngredient($ingredients, 'Cheese, Gouda Double Cream (Trader Joe\'s)', $lunchTime, [20, 40]);
                break;
            case 3: // Pasta
                $this->logIngredient($ingredients, 'Spaghetti (dry weight)', $lunchTime, [80, 120]);
                $this->logIngredient($ingredients, 'Tomato Sauce (Muir Glen)', $lunchTime, [100, 150]);
                $this->logIngredient($ingredients, 'Cheese, Parmesan', $lunchTime, [10, 20]);
                break;
        }
    }

    private function createDinner(Carbon $date, $ingredients)
    {
        $dinnerTime = $date->copy()->setHour(rand(18, 20))->setMinute(rand(0, 59));
        $mealType = rand(1, 3);

        switch ($mealType) {
            case 1: // Salmon with rice and broccoli
                $this->logIngredient($ingredients, 'Atlantic Salmon (Skin On - Trader Joe\'s)', $dinnerTime, [120, 180]);
                $this->logIngredient($ingredients, 'Rice, Brown Jasmine (Cooked - Trader Joe\'s)', $dinnerTime, [100, 150]);
                $this->logIngredient($ingredients, 'Broccoli (dry)', $dinnerTime, [80, 120]);
                break;
            case 2: // Steak with potatoes and spinach
                $this->logIngredient($ingredients, 'Beef, Ground (90% Lean, 10% Fat)', $dinnerTime, [150, 200]);
                $this->logIngredient($ingredients, 'Potatoes, Yellow Fingerling (Southwind Farms)', $dinnerTime, [150, 250]);
                $this->logIngredient($ingredients, 'Spinach', $dinnerTime, [50, 100]);
                break;
            case 3: // Chicken with pasta
                $this->logIngredient($ingredients, 'Chicken Thigh (Skinless, Boneless)', $dinnerTime, [120, 180]);
                $this->logIngredient($ingredients, 'Casarecce (Durum Wheat Semolina - Whole Foods) - Dry', $dinnerTime, [80, 120]);
                // Assuming pesto is not in the ingredients list
                // $this->logIngredient($ingredients, 'Pesto (from a jar)', $dinnerTime, [30, 50]); 
                break;
        }
    }

    private function createSnack(Carbon $date, $ingredients)
    {
        $snackTime = $date->copy()->setHour(rand(15, 17))->setMinute(rand(0, 59));
        $snackType = rand(1, 4);

        switch ($snackType) {
            case 1:
                $this->logIngredient($ingredients, 'Apple (small, around 150g in total)', $snackTime, [1, 1]);
                break;
            case 2:
                $this->logIngredient($ingredients, 'Banana', $snackTime, [1, 1]);
                break;
            case 3:
                $this->logIngredient($ingredients, 'Protein Bar (Think! Any Flavor)', $snackTime, [1, 1]);
                break;
            case 4:
                $this->logIngredient($ingredients, 'Cashews, Whole (Salted, 50% Less Sodium, Trader Joe\'s)', $snackTime, [20, 40]);
                break;
        }
    }

    private function logIngredient($ingredients, $name, Carbon $time, array $quantityRange)
    {
        if (isset($ingredients[$name])) {
            $ingredient = $ingredients[$name];
            $quantity = rand($quantityRange[0] * 10, $quantityRange[1] * 10) / 10;

            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $quantity,
                'logged_at' => $time,
            ]);
        }
    }
}