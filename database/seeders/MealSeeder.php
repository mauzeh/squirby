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

        // Meal 3: Salmon Dinner
        $meal3 = Meal::create(['name' => 'Salmon Dinner']);
        $meal3->ingredients()->attach($ingredients['Atlantic Salmon (Skin On - Trader Joe\'s)']->id, ['quantity' => 180]);
        $meal3->ingredients()->attach($ingredients['Rice, Brown Jasmine (Cooked - Trader Joe\'s)']->id, ['quantity' => 120]);
        $meal3->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
    }
}