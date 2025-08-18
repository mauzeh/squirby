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
        $meal1->ingredients()->attach($ingredients['Greek Yogurt (Whole Milk - Chobani)']->id, ['quantity' => 250]);
        $meal1->ingredients()->attach($ingredients['Granola (Chocolate Coffee, Trader Joe\'s)']->id, ['quantity' => 50]);

        // Meal 2: Evening Yogurt
        $meal2 = Meal::create(['name' => 'Evening Yogurt']);
        $meal2->ingredients()->attach($ingredients['Greek Yogurt (Whole Milk - Chobani)']->id, ['quantity' => 250]);
        $meal2->ingredients()->attach($ingredients['Blueberries (fresh)']->id, ['quantity' => 65]);
        $meal2->ingredients()->attach($ingredients['Honey']->id, ['quantity' => 15]);

        // Meal 3: Nasi Goreng
        $meal3 = Meal::create(['name' => 'Nasi Goreng (Vegetarian Fried Rice)']);
        $meal3->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 1]);
        $meal3->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
        $meal3->ingredients()->attach($ingredients['Rice, Brown Jasmine (Dry - Trader Joe\'s)']->id, ['quantity' => 100]);
        $meal3->ingredients()->attach($ingredients['Salt for Life (Nature\'s Alternative Salt)']->id, ['quantity' => .125]);
        $meal3->ingredients()->attach($ingredients['Multi-Purpose Umami Seasoning Blend (Trader Joe\'s)']->id, ['quantity' => .25]);
        $meal3->ingredients()->attach($ingredients['Sriracha (Trader Joe\'s)']->id, ['quantity' => 1]);
        $meal3->ingredients()->attach($ingredients['Egg (L) whole']->id, ['quantity' => 2]);

        // Meal 4: Carb Loading Shake
        $meal3 = Meal::create(['name' => 'Carb Loading Shake']);
        $meal3->ingredients()->attach($ingredients['Oats']->id, ['quantity' => 100]);
        $meal3->ingredients()->attach($ingredients['Trader Joe\'s - Frozen Fruits (Average)']->id, ['quantity' => 75]);
        $meal3->ingredients()->attach($ingredients['Milk (2%, Clover Sonoma)']->id, ['quantity' => 480]);
        $meal3->ingredients()->attach($ingredients['Whole Psyllium Husks']->id, ['quantity' => 1]);

    }
}