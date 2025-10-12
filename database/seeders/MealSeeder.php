<?php

namespace Database\Seeders;

use App\Models\Meal;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\Seeder;

class MealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();

        \Illuminate\Support\Facades\Log::info('Ingredients count before MealSeeder: ' . Ingredient::count());

        $ingredients = Ingredient::all()->keyBy('name');

        $meal = Meal::create(['name' => 'Breakfast Bowl', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Greek Yogurt (Whole Milk - Chobani)']->id, ['quantity' => 250]);
        $meal->ingredients()->attach($ingredients['Granola (Chocolate Coffee, Trader Joe\'s)']->id, ['quantity' => 50]);

        $meal = Meal::create(['name' => 'Evening Yogurt', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Greek Yogurt (Whole Milk - Chobani)']->id, ['quantity' => 250]);
        $meal->ingredients()->attach($ingredients['Blueberries (fresh)']->id, ['quantity' => 65]);
        $meal->ingredients()->attach($ingredients['Honey']->id, ['quantity' => 15]);

        $meal = Meal::create(['name' => 'Nasi Goreng (Vegetarian Fried Rice)', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 1]);
        $meal->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Rice, Brown Jasmine (Dry - Trader Joe\'s)']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients["Seasoning, Salt for Life (Nature's Alternative Salt)"]->id, ['quantity' => .125]);
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25]);
        $meal->ingredients()->attach($ingredients['Sriracha (Trader Joe\'s)']->id, ['quantity' => 2]);
        $meal->ingredients()->attach($ingredients['Egg (L) whole']->id, ['quantity' => 2]);

        $meal = Meal::create(['name' => 'Carb Loading Shake', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Oats']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Fruits, Frozen (Average, Trader Joe\'s)']->id, ['quantity' => 75]);
        $meal->ingredients()->attach($ingredients['Milk (2%, Clover Sonoma)']->id, ['quantity' => 480]);
        $meal->ingredients()->attach($ingredients['Husks (Whole Psyllium)']->id, ['quantity' => 1]);

        $meal = Meal::create(['name' => 'Roasted Chicken from the Oven (1 serving of 140g chicken)', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Chicken Thigh (Skinless, Boneless)']->id, ['quantity' => 700/5]);
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .5/5]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2/5]);

        $meal = Meal::create(['name' => 'Japanese Sweet Potato (250g) from the Oven', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Sweet Potato']->id, ['quantity' => 500/2]);
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .5/2]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2/2]);

        $meal = Meal::create(['name' => 'Roasted Chicken from the Oven (5 servings @ 140g chicken/serving)', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Broccoli (dry)']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .125]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => .5]);

        $meal = Meal::create(['name' => 'Mexican Spiced Chicken Tacos', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Tortillas (Mini, Flour, Trader Joe\'s)']->id, ['quantity' => 4]);
        $meal->ingredients()->attach($ingredients['Chicken Thigh (Skinless, Boneless)']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Black Beans (canned, rinsed)']->id, ['quantity' => 200]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2]);
        $meal->ingredients()->attach($ingredients['Seasoning, Umami Multi-Purpose (Trader Joe\'s)']->id, ['quantity' => 0.5]);

        $meal = Meal::create(['name' => 'Fusili with Veggies & Red Sauce (1 serving)', 'user_id' => $adminUser->id]);
        // The original sauce (see above)
        $meal->ingredients()->attach($ingredients['Beef, Ground (90% Lean, 10% Fat)']->id, ['quantity' => 452/5]);
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25/5]);
        $meal->ingredients()->attach($ingredients['Tomato Sauce (Muir Glen)']->id, ['quantity' => 1/5]);
        $meal->ingredients()->attach($ingredients['Tomato Paste (Organic, Trader Joe\'s)']->id, ['quantity' => 1/5]);
        // What I add to make it a full meal
        $meal->ingredients()->attach($ingredients['Pasta, Fusilli, Whole Wheat (De Cecco)']->id, ['quantity' => 125]);
        $meal->ingredients()->attach($ingredients['Green Beans']->id, ['quantity' => 50]);
        $meal->ingredients()->attach($ingredients['Bell Pepper (Fresh)']->id, ['quantity' => 50]);
        // From frying the veggies
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 1]);
        // Additional seasoning of the veggies
        $meal->ingredients()->attach($ingredients["Seasoning, Umami Multi-Purpose (Trader Joe's)"]->id, ['quantity' => .25]);
        // Additional seasoning in the pasta water
        $meal->ingredients()->attach($ingredients["Seasoning, Salt for Life (Nature's Alternative Salt)"]->id, ['quantity' => .25]);

        $meal = Meal::create(['name' => 'Hans made Rice Bowl with Chicken and Veggies', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Rice, White Jasmine (Dry - Trader Joe\'s)']->id, ['quantity' => 125]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 3]);
        $meal->ingredients()->attach($ingredients["Chicken Thigh (Skinless, Boneless)"]->id, ['quantity' => 125]);
        $meal->ingredients()->attach($ingredients['Cucumber']->id, ['quantity' => 75]);
        $meal->ingredients()->attach($ingredients['Corn, Roasted Frozen (Trader Joe\'s)']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Grape Tomato']->id, ['quantity' => 75]);

        $meal = Meal::create(['name' => 'Direct Entry Template (1000 cal, 35g fat, 1500mg sodium)', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Fat (g)']->id, ['quantity' => 50]);
        $meal->ingredients()->attach($ingredients["Direct Entry - Carbohydrate (g)"]->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Protein (g)']->id, ['quantity' => 35]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Sodium (mg)']->id, ['quantity' => 2000]);

        $meal = Meal::create(['name' => 'Apple & Cashews', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Apple (small, around 150g in total)']->id, ['quantity' => 1]);
        $meal->ingredients()->attach($ingredients["Cashews, Whole (Salted, 50% Less Sodium, Trader Joe's)"]->id, ['quantity' => 35]);

        $meal = Meal::create(['name' => 'Spanish Tortilla with Caramelized Onion', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Added Sugar (g) - ZERO calories']->id, ['quantity' => 8]);
        $meal->ingredients()->attach($ingredients['Potatoes, Yellow Fingerling (Southwind Farms)']->id, ['quantity' => 227]);
        $meal->ingredients()->attach($ingredients['Butter (unsalted, Clover)']->id, ['quantity' => 15]);
        $meal->ingredients()->attach($ingredients['Seasoning, Umami Multi-Purpose (Trader Joe\'s)']->id, ['quantity' => 1]);
        $meal->ingredients()->attach($ingredients['Egg (L) whole']->id, ['quantity' => 2]);
        $meal->ingredients()->attach($ingredients['Cheese, Goat (Crumbled, Trader Joe\'s)']->id, ['quantity' => 28]);
        $meal->ingredients()->attach($ingredients['Olive oil']->id, ['quantity' => 2]);
        $meal->ingredients()->attach($ingredients['Onion, Yellow']->id, ['quantity' => 100]);
        $meal->ingredients()->attach($ingredients['Bell Pepper (Fresh)']->id, ['quantity' => 50]);

        $meal = Meal::create(['name' => 'Souvla Chicken Sandwich (with 1 tbsp Granch Yogurt and no cheese at all).', 'user_id' => $adminUser->id]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Carbohydrate (g)']->id, ['quantity' => 36]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Fat (g)']->id, ['quantity' => 33]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Protein (g)']->id, ['quantity' => 44]);
        $meal->ingredients()->attach($ingredients['Direct Entry - Sodium (mg)']->id, ['quantity' => 1500]);

    }
}
