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

        if (!$adminUser) {
            $this->command->error('Admin user not found. Please run UserSeeder first.');
            return;
        }

        \Illuminate\Support\Facades\Log::info('Ingredients count before MealSeeder: ' . Ingredient::count());

        $ingredients = Ingredient::where('user_id', $adminUser->id)->get()->keyBy('name');

        // Create simple meals using available hardcoded ingredients
        if (isset($ingredients['Greek Yogurt']) && isset($ingredients['Banana'])) {
            $meal = Meal::create(['name' => 'Greek Yogurt with Banana', 'user_id' => $adminUser->id]);
            $meal->ingredients()->attach($ingredients['Greek Yogurt']->id, ['quantity' => 150]);
            $meal->ingredients()->attach($ingredients['Banana']->id, ['quantity' => 1]);
        }

        if (isset($ingredients['Oats']) && isset($ingredients['Milk'])) {
            $meal = Meal::create(['name' => 'Oatmeal with Milk', 'user_id' => $adminUser->id]);
            $meal->ingredients()->attach($ingredients['Oats']->id, ['quantity' => 50]);
            $meal->ingredients()->attach($ingredients['Milk']->id, ['quantity' => 1]);
        }

        if (isset($ingredients['Chicken Breast']) && isset($ingredients['Brown Rice']) && isset($ingredients['Broccoli'])) {
            $meal = Meal::create(['name' => 'Chicken Rice Bowl', 'user_id' => $adminUser->id]);
            $meal->ingredients()->attach($ingredients['Chicken Breast']->id, ['quantity' => 150]);
            $meal->ingredients()->attach($ingredients['Brown Rice']->id, ['quantity' => 100]);
            $meal->ingredients()->attach($ingredients['Broccoli']->id, ['quantity' => 100]);
        }

        if (isset($ingredients['Eggs']) && isset($ingredients['Spinach'])) {
            $meal = Meal::create(['name' => 'Spinach Scramble', 'user_id' => $adminUser->id]);
            $meal->ingredients()->attach($ingredients['Eggs']->id, ['quantity' => 2]);
            $meal->ingredients()->attach($ingredients['Spinach']->id, ['quantity' => 50]);
        }

        $this->command->info('Created sample meals using available hardcoded ingredients');
    }
}
