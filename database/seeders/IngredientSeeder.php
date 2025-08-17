<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gramUnit = Unit::where('abbreviation', 'g')->first();
        $pieceUnit = Unit::where('abbreviation', 'pc')->first();
        $mlUnit = Unit::where('abbreviation', 'ml')->first();

        Ingredient::create([
            'name' => 'Apple',
            'calories' => 52,
            'protein' => 0,
            'carbs' => 14,
            'added_sugars' => 10,
            'fats' => 0,
            'sodium' => 1,
            'iron' => 0,
            'potassium' => 107,
            'base_quantity' => 1,
            'base_unit_id' => $pieceUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Banana',
            'calories' => 89,
            'protein' => 1,
            'carbs' => 23,
            'added_sugars' => 12,
            'fats' => 0,
            'sodium' => 1,
            'iron' => 0,
            'potassium' => 358,
            'base_quantity' => 1,
            'base_unit_id' => $pieceUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Chicken Breast',
            'calories' => 165,
            'protein' => 31,
            'carbs' => 0,
            'added_sugars' => 0,
            'fats' => 3,
            'sodium' => 74,
            'iron' => 1,
            'potassium' => 256,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Broccoli',
            'calories' => 55,
            'protein' => 4,
            'carbs' => 11,
            'added_sugars' => 2,
            'fats' => 1,
            'sodium' => 33,
            'iron' => 1,
            'potassium' => 316,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Rice (cooked)',
            'calories' => 130,
            'protein' => 3,
            'carbs' => 28,
            'added_sugars' => 0,
            'fats' => 0,
            'sodium' => 1,
            'iron' => 0,
            'potassium' => 55,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Salmon',
            'calories' => 208,
            'protein' => 20,
            'carbs' => 0,
            'added_sugars' => 0,
            'fats' => 13,
            'sodium' => 59,
            'iron' => 0,
            'potassium' => 363,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Egg',
            'calories' => 155,
            'protein' => 13,
            'carbs' => 1,
            'added_sugars' => 0,
            'fats' => 11,
            'sodium' => 124,
            'iron' => 1,
            'potassium' => 138,
            'base_quantity' => 1,
            'base_unit_id' => $pieceUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Milk (whole)',
            'calories' => 61,
            'protein' => 3,
            'carbs' => 5,
            'added_sugars' => 0,
            'fats' => 3,
            'sodium' => 43,
            'iron' => 0,
            'potassium' => 150,
            'base_quantity' => 100,
            'base_unit_id' => $mlUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Bread (whole wheat)',
            'calories' => 265,
            'protein' => 13,
            'carbs' => 49,
            'added_sugars' => 5,
            'fats' => 4,
            'sodium' => 400,
            'iron' => 2,
            'potassium' => 200,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);

        Ingredient::create([
            'name' => 'Spinach',
            'calories' => 23,
            'protein' => 3,
            'carbs' => 4,
            'added_sugars' => 0,
            'fats' => 0,
            'sodium' => 79,
            'iron' => 3,
            'potassium' => 558,
            'base_quantity' => 100,
            'base_unit_id' => $gramUnit->id,
        ]);
    }
}
