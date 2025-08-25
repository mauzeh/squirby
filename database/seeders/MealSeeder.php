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
        $meal3->ingredients()->attach($ingredients["Seasoning, Salt for Life (Nature's Alternative Salt)"]->id, ['quantity' => .125]);
        $meal3->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25]);
        $meal3->ingredients()->attach($ingredients['Sriracha (Trader Joe\'s)']->id, ['quantity' => 2]);
        $meal3->ingredients()->attach($ingredients['Egg (L) whole']->id, ['quantity' => 2]);

        // Meal 4: Carb Loading Shake
        $meal3 = Meal::create(['name' => 'Carb Loading Shake']);
        $meal3->ingredients()->attach($ingredients['Oats']->id, ['quantity' => 100]);
        $meal3->ingredients()->attach($ingredients['Trader Joe\'s - Frozen Fruits (Average)']->id, ['quantity' => 75]);
        $meal3->ingredients()->attach($ingredients['Milk (2%, Clover Sonoma)']->id, ['quantity' => 480]);
        $meal3->ingredients()->attach($ingredients['Husks (Whole Psyllium)']->id, ['quantity' => 1]);

        // Meal 4: Roasted Chicken from the Oven (1 serving of 140g chicken)
        $meal3 = Meal::create(['name' => 'Roasted Chicken from the Oven (1 serving of 140g chicken)']);
        $meal3->ingredients()->attach($ingredients['Chicken Thigh (Skinless, Boneless)']->id, ['quantity' => 700/5]);
        $meal3->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .5/5]);
        $meal3->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2/5]);

        // Meal 4: Japanese Sweet Potato (500g) from the Oven
        $meal3 = Meal::create(['name' => 'Japanese Sweet Potato (250g) from the Oven']);
        $meal3->ingredients()->attach($ingredients['Sweet Potato']->id, ['quantity' => 500/2]);
        $meal3->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .5/2]);
        $meal3->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2/2]);

        // Meal 4: Broccoli from the Oven (100g portion)
        $meal3 = Meal::create(['name' => 'Roasted Chicken from the Oven (5 servings @ 140g chicken/serving)']);
        $meal3->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
        $meal3->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .125]);
        $meal3->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => .5]);

        // Meal 5: Fusili with Veggies & Red Sauce (1 serving)
        $meal4 = Meal::create(['name' => 'Fusili with Veggies & Red Sauce (1 serving)']);
        // The original sauce (see above)
        $meal4->ingredients()->attach($ingredients['Beef, Ground (90% Lean, 10% Fat)']->id, ['quantity' => 452/5]);
        $meal4->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25/5]);
        $meal4->ingredients()->attach($ingredients['Tomato Sauce (Muir Glen)']->id, ['quantity' => 1/5]);
        $meal4->ingredients()->attach($ingredients['Tomato Paste (Organic, Trader Joe\'s)']->id, ['quantity' => 1/5]);
        // What I add to make it a full meal
        $meal4->ingredients()->attach($ingredients['Pasta, Fusilli, Whole Wheat (De Cecco)']->id, ['quantity' => 125]);
        $meal4->ingredients()->attach($ingredients['Green Beans']->id, ['quantity' => 50]);
        $meal4->ingredients()->attach($ingredients['Bell Pepper (Fresh)']->id, ['quantity' => 50]);
        // From frying the veggies
        $meal4->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 1]);
        // Additional seasoning of the veggies
        $meal4->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25]);
        // Additional seasoning in the pasta water
        $meal4->ingredients()->attach($ingredients["Seasoning, Salt for Life (Nature's Alternative Salt)"]->id, ['quantity' => .25]);

        // Meal 5: Hans made Rice Bowl with Chicken and Veggies
        $meal5 = Meal::create(['name' => 'Hans made Rice Bowl with Chicken and Veggies']);
        $meal5->ingredients()->attach($ingredients['Rice, White Jasmine (Dry - Trader Joe\'s)']->id, ['quantity' => 125]);
        $meal5->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 3]);
        $meal5->ingredients()->attach($ingredients["Chicken Thigh (Skinless, Boneless)"]->id, ['quantity' => 125]);
        $meal5->ingredients()->attach($ingredients['Cucumber']->id, ['quantity' => 75]);
        $meal5->ingredients()->attach($ingredients['Corn, Roasted Frozen (Trader Joe\'s)']->id, ['quantity' => 100]);
        $meal5->ingredients()->attach($ingredients['Grape Tomato']->id, ['quantity' => 75]);

        // Meal 6: Direct Entry Template (1000 cal, 35g fat, 1500mg sodium)
        $meal6 = Meal::create(['name' => 'Direct Entry Template (1000 cal, 35g fat, 1500mg sodium)']);
        $meal6->ingredients()->attach($ingredients['Direct Entry - Fat (g)']->id, ['quantity' => 50]);
        $meal6->ingredients()->attach($ingredients["Direct Entry - Carbohydrate (g)"]->id, ['quantity' => 100]);
        $meal6->ingredients()->attach($ingredients['Direct Entry - Protein (g)']->id, ['quantity' => 35]);
        $meal6->ingredients()->attach($ingredients['Direct Entry - Sodium (mg)']->id, ['quantity' => 2000]);

        // Meal 7: Apple & Cashews
        $meal7 = Meal::create(['name' => 'Apple & Cashews']);
        $meal7->ingredients()->attach($ingredients['Apple (small, around 150g in total)']->id, ['quantity' => 1]);
        $meal7->ingredients()->attach($ingredients["Cashews, Whole (Salted, 50% Less Sodium, Trader Joe's)"]->id, ['quantity' => 35]);

    }
}