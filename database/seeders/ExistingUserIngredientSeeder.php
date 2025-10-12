<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExistingUserIngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the admin user who has the seeded ingredients
        $adminUser = User::where('email', 'admin@example.com')->first();
        
        if (!$adminUser) {
            return; // No admin user found, skip
        }
        
        // Get all non-admin users who don't have ingredients yet
        $users = User::where('email', '!=', 'admin@example.com')
            ->whereDoesntHave('ingredients')
            ->get();
        
        foreach ($users as $user) {
            // Only create ingredients - don't create meals or measurement types again
            $this->copyIngredientsFromAdmin($user, $adminUser);
            
            // Populate the existing sample meal with ingredients
            $this->populateExistingSampleMeal($user);
        }
    }
    
    /**
     * Copy specific ingredients from admin user to the new user.
     */
    private function copyIngredientsFromAdmin(User $user, User $adminUser): void
    {
        // Get the specific ingredients we want to copy for the sample meal
        $ingredientNames = [
            'Chicken Breast (Raw)',
            'Rice, Brown Jasmine (Dry - Trader Joe\'s)',
            'Broccoli (raw)',
            'Olive oil',
            'Egg (L) whole',
        ];

        foreach ($ingredientNames as $ingredientName) {
            $adminIngredient = $adminUser->ingredients()->where('name', $ingredientName)->first();
            
            if ($adminIngredient) {
                // Create a copy of the ingredient for this user
                $user->ingredients()->create([
                    'name' => $adminIngredient->name,
                    'base_quantity' => $adminIngredient->base_quantity,
                    'protein' => $adminIngredient->protein,
                    'carbs' => $adminIngredient->carbs,
                    'added_sugars' => $adminIngredient->added_sugars,
                    'fats' => $adminIngredient->fats,
                    'sodium' => $adminIngredient->sodium,
                    'iron' => $adminIngredient->iron,
                    'potassium' => $adminIngredient->potassium,
                    'fiber' => $adminIngredient->fiber,
                    'calcium' => $adminIngredient->calcium,
                    'caffeine' => $adminIngredient->caffeine,
                    'base_unit_id' => $adminIngredient->base_unit_id,
                    'cost_per_unit' => $adminIngredient->cost_per_unit,
                ]);
            }
        }
    }
    
    /**
     * Populate the existing sample meal with ingredients.
     */
    private function populateExistingSampleMeal(User $user): void
    {
        // Find the existing sample meal
        $sampleMeal = $user->meals()->where('name', 'Chicken, Rice & Broccoli')->first();
        
        if (!$sampleMeal) {
            return; // No sample meal found
        }
        
        // Attach ingredients to the sample meal using the correct names from CSV
        $chickenBreast = $user->ingredients()->where('name', 'Chicken Breast (Raw)')->first();
        $rice = $user->ingredients()->where('name', 'Rice, Brown Jasmine (Dry - Trader Joe\'s)')->first();
        $broccoli = $user->ingredients()->where('name', 'Broccoli (raw)')->first();
        $oliveOil = $user->ingredients()->where('name', 'Olive oil')->first();

        if ($chickenBreast) {
            $sampleMeal->ingredients()->attach($chickenBreast->id, ['quantity' => 150]);
        }
        if ($rice) {
            $sampleMeal->ingredients()->attach($rice->id, ['quantity' => 100]);
        }
        if ($broccoli) {
            $sampleMeal->ingredients()->attach($broccoli->id, ['quantity' => 200]);
        }
        if ($oliveOil) {
            $sampleMeal->ingredients()->attach($oliveOil->id, ['quantity' => 1]);
        }
    }
}
