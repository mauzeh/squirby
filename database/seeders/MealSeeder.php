<?php

namespace Database\Seeders;

use App\Models\Meal;
use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class MealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Meal::truncate();
        $ingredients = Ingredient::all()->keyBy('name');

        // Meal 1: Breakfast Bowl
        $meal1 = Meal::create(['name' => 'Breakfast Bowl']);
        $meal1->ingredients()->attach($ingredients['Oats']->id, ['quantity' => 50]);
        $meal1->ingredients()->attach($ingredients['Milk (2%, Clover Sonoma)']->id, ['quantity' => 200]);
        $meal1->ingredients()->attach($ingredients['Blueberries (fresh)']->id, ['quantity' => 75]);

        // Meal 2: Chicken Salad
        $meal2 = Meal::create(['name' => 'Chicken Salad']);
        $meal2->ingredients()->attach($ingredients['Chicken Breast (Raw)']->id, ['quantity' => 150]);
        $meal2->ingredients()->attach($ingredients['Brussels sprouts (steamed, no oil)']->id, ['quantity' => 100]);
        $meal2->ingredients()->attach($ingredients['Bell Pepper (Fresh)']->id, ['quantity' => 50]);
        $meal2->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 10]);

        // Meal 3: Salmon Dinner
        $meal3 = Meal::create(['name' => 'Salmon Dinner']);
        $meal3->ingredients()->attach($ingredients['Atlantic Salmon (Skin On - Trader Joe\'s)']->id, ['quantity' => 180]);
        $meal3->ingredients()->attach($ingredients['Rice, Brown Jasmine (Cooked - Trader Joe\'s)']->id, ['quantity' => 120]);
        $meal3->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
    }
}