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
        }
    }
}
