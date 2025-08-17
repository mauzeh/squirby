<?php

namespace Database\Seeders;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DailyLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ingredients = Ingredient::all();
        $units = Unit::all();

        if ($ingredients->isEmpty() || $units->isEmpty()) {
            $this->command->info('Ingredients or Units not seeded yet. Please run IngredientSeeder and UnitSeeder first.');
            return;
        }

        $g = $units->where('abbreviation', 'g')->first();
        $pc = $units->where('abbreviation', 'pc')->first();
        $cup = $units->where('abbreviation', 'cup')->first();
        $ml = $units->where('abbreviation', 'ml')->first();

        $apple = $ingredients->where('name', 'Apple')->first();
        $banana = $ingredients->where('name', 'Banana')->first();
        $chicken = $ingredients->where('name', 'Chicken Breast')->first();
        $broccoli = $ingredients->where('name', 'Broccoli')->first();
        $rice = $ingredients->where('name', 'Rice (cooked)')->first();
        $salmon = $ingredients->where('name', 'Salmon')->first();
        $egg = $ingredients->where('name', 'Egg')->first();
        $milk = $ingredients->where('name', 'Milk (whole)')->first();
        $bread = $ingredients->where('name', 'Bread (whole wheat)')->first();
        $spinach = $ingredients->where('name', 'Spinach')->first();

        // Ensure all necessary ingredients and units exist
        if (!$g || !$pc || !$cup || !$apple || !$banana || !$chicken || !$broccoli || !$rice || !$salmon || !$egg || !$milk || !$bread || !$spinach) {
            $this->command->info('Missing some ingredients or units for seeding daily logs.');
            return;
        }

        // Clear existing daily logs to avoid duplicates on re-run
        DailyLog::truncate();

        // Add 15+ more log entries with varying consecutive created_at days
        $startDate = Carbon::now()->subDays(15); // Start 15 days ago to ensure enough consecutive days

        for ($i = 0; $i < 20; $i++) { // Generate 20 days of logs
            $currentDate = $startDate->copy()->addDays($i);

            // Breakfast
            DailyLog::create([
                'ingredient_id' => $egg->id,
                'unit_id' => $egg->base_unit_id,
                'quantity' => 2.0,
                'logged_at' => $currentDate->copy()->setHour(7)->setMinute(0),
            ]);
            DailyLog::create([
                'ingredient_id' => $bread->id,
                'unit_id' => $bread->base_unit_id,
                'quantity' => 50.0,
                'logged_at' => $currentDate->copy()->setHour(7)->setMinute(15),
            ]);

            // Lunch
            DailyLog::create([
                'ingredient_id' => $chicken->id,
                'unit_id' => $chicken->base_unit_id,
                'quantity' => 120.0,
                'logged_at' => $currentDate->copy()->setHour(13)->setMinute(0),
            ]);
            DailyLog::create([
                'ingredient_id' => $rice->id,
                'unit_id' => $rice->base_unit_id,
                'quantity' => 100.0,
                'logged_at' => $currentDate->copy()->setHour(13)->setMinute(15),
            ]);

            // Dinner
            DailyLog::create([
                'ingredient_id' => $salmon->id,
                'unit_id' => $salmon->base_unit_id,
                'quantity' => 180.0,
                'logged_at' => $currentDate->copy()->setHour(19)->setMinute(0),
            ]);
            DailyLog::create([
                'ingredient_id' => $spinach->id,
                'unit_id' => $spinach->base_unit_id,
                'quantity' => 100.0,
                'logged_at' => $currentDate->copy()->setHour(19)->setMinute(15),
            ]);

            // Add some random snacks
            if ($i % 3 == 0) { // Every 3rd day, add an apple
                DailyLog::create([
                    'ingredient_id' => $apple->id,
                    'unit_id' => $apple->base_unit_id,
                    'quantity' => 1.0,
                    'logged_at' => $currentDate->copy()->setHour(10)->setMinute(0),
                ]);
            }
            if ($i % 2 == 0) { // Every 2nd day, add some milk
                DailyLog::create([
                    'ingredient_id' => $milk->id,
                    'unit_id' => $milk->base_unit_id,
                    'quantity' => 200.0,
                    'logged_at' => $currentDate->copy()->setHour(16)->setMinute(30),
                ]);
            }
        }
    }
}
