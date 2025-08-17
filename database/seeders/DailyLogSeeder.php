<?php

namespace Database\Seeders;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class DailyLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ingredient = Ingredient::first();
        $unit = Unit::where('abbreviation', 'g')->first(); // Assuming 'g' unit exists from UnitSeeder

        if ($ingredient && $unit) {
            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $unit->id,
                'quantity' => 100.5,
            ]);

            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $unit->id,
                'quantity' => 250.0,
            ]);

            // Add 7 more log entries
            $apple = Ingredient::where('name', 'Apple')->first();
            $banana = Ingredient::where('name', 'Banana')->first();
            $chicken = Ingredient::where('name', 'Chicken Breast')->first();
            $broccoli = Ingredient::where('name', 'Broccoli')->first();
            $rice = Ingredient::where('name', 'Rice (cooked)')->first();
            $salmon = Ingredient::where('name', 'Salmon')->first();
            $egg = Ingredient::where('name', 'Egg')->first();

            $g = Unit::where('abbreviation', 'g')->first();
            $pc = Unit::where('abbreviation', 'pc')->first();
            $cup = Unit::where('abbreviation', 'cup')->first();

            if ($apple && $g) {
                DailyLog::create([
                    'ingredient_id' => $apple->id,
                    'unit_id' => $g->id,
                    'quantity' => 150.0,
                ]);
            }
            if ($banana && $pc) {
                DailyLog::create([
                    'ingredient_id' => $banana->id,
                    'unit_id' => $pc->id,
                    'quantity' => 1.0,
                ]);
            }
            if ($chicken && $g) {
                DailyLog::create([
                    'ingredient_id' => $chicken->id,
                    'unit_id' => $g->id,
                    'quantity' => 200.0,
                ]);
            }
            if ($broccoli && $cup) {
                DailyLog::create([
                    'ingredient_id' => $broccoli->id,
                    'unit_id' => $cup->id,
                    'quantity' => 0.5,
                ]);
            }
            if ($rice && $g) {
                DailyLog::create([
                    'ingredient_id' => $rice->id,
                    'unit_id' => $g->id,
                    'quantity' => 180.0,
                ]);
            }
            if ($salmon && $g) {
                DailyLog::create([
                    'ingredient_id' => $salmon->id,
                    'unit_id' => $g->id,
                    'quantity' => 120.0,
                ]);
            }
            if ($egg && $pc) {
                DailyLog::create([
                    'ingredient_id' => $egg->id,
                    'unit_id' => $pc->id,
                    'quantity' => 2.0,
                ]);
            }
        }
    }
}
