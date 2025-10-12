<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;

class UserSeederService
{
    /**
     * Seed a new user with default data including measurement types, ingredients, and a sample meal.
     */
    public function seedNewUser(User $user): void
    {
        $this->createMeasurementTypes($user);
        $this->createDefaultIngredients($user);
        $this->createSampleMeal($user);
    }

    /**
     * Seed admin user with only measurement types (no sample meal or basic ingredients).
     * Admin gets ingredients from IngredientSeeder and meals from MealSeeder.
     */
    public function seedAdminUser(User $user): void
    {
        $this->createMeasurementTypes($user);
    }

    /**
     * Create default measurement types for the user.
     */
    private function createMeasurementTypes(User $user): void
    {
        $measurementTypes = [
            ['name' => 'Bodyweight', 'default_unit' => 'lbs'],
            ['name' => 'Waist', 'default_unit' => 'cm'],
        ];

        foreach ($measurementTypes as $measurementType) {
            $user->measurementTypes()->create($measurementType);
        }
    }

    /**
     * Create default ingredients for the user by copying from admin user's ingredients.
     * If no admin user exists, create basic ingredients directly.
     */
    private function createDefaultIngredients(User $user): void
    {
        // Find the admin user who has the seeded ingredients
        $adminUser = User::where('email', 'admin@example.com')->first();
        
        if ($adminUser) {
            // Copy ingredients from admin user
            $this->copyIngredientsFromAdmin($user, $adminUser);
        } else {
            // Create basic ingredients directly (for tests or when no admin exists)
            $this->createBasicIngredients($user);
        }
    }

    /**
     * Copy specific ingredients from admin user to the new user.
     */
    private function copyIngredientsFromAdmin(User $user, User $adminUser): void
    {
        // Get the specific ingredients we want to copy for the sample meal
        $ingredientNames = [
            'Chicken Breast (Raw)', // Updated to match CSV data
            'Rice, Brown Jasmine (Dry - Trader Joe\'s)', // Updated to match CSV data
            'Broccoli (raw)',
            'Olive oil', // Updated to match CSV data (lowercase)
            'Egg (L) whole', // Updated to match CSV data
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
     * Create basic ingredients directly when no admin user exists.
     */
    private function createBasicIngredients(User $user): void
    {
        $gramUnit = Unit::firstOrCreate(
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['conversion_factor' => 1]
        );

        $milliliterUnit = Unit::firstOrCreate(
            ['name' => 'Milliliter', 'abbreviation' => 'ml'],
            ['conversion_factor' => 1]
        );

        $pieceUnit = Unit::firstOrCreate(
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['conversion_factor' => 1]
        );

        $ingredients = [
            [
                'name' => 'Chicken Breast (Raw)',
                'base_quantity' => 100,
                'protein' => 25,
                'carbs' => 0,
                'added_sugars' => 0,
                'fats' => 3,
                'sodium' => 60,
                'iron' => 0,
                'potassium' => 250,
                'fiber' => 0,
                'calcium' => 10,
                'caffeine' => 0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0,
            ],
            [
                'name' => 'Rice, Brown Jasmine (Dry - Trader Joe\'s)',
                'base_quantity' => 45,
                'protein' => 4,
                'carbs' => 34,
                'added_sugars' => 0,
                'fats' => 1.5,
                'sodium' => 0,
                'iron' => 1.1,
                'potassium' => 100,
                'fiber' => 2,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0.13,
            ],
            [
                'name' => 'Broccoli (raw)',
                'base_quantity' => 100,
                'protein' => 2.8,
                'carbs' => 6.6,
                'added_sugars' => 0,
                'fats' => 0.4,
                'sodium' => 0,
                'iron' => 0,
                'potassium' => 0,
                'fiber' => 0,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0,
            ],
            [
                'name' => 'Olive oil',
                'base_quantity' => 1,
                'protein' => 0,
                'carbs' => 0,
                'added_sugars' => 0,
                'fats' => 14,
                'sodium' => 0,
                'iron' => 0,
                'potassium' => 0,
                'fiber' => 0,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => Unit::where('abbreviation', 'tbsp')->first()->id ?? $milliliterUnit->id,
                'cost_per_unit' => 0.20,
            ],
            [
                'name' => 'Egg (L) whole',
                'base_quantity' => 1,
                'protein' => 6.3,
                'carbs' => 0.8,
                'added_sugars' => 0,
                'fats' => 4.6,
                'sodium' => 63,
                'iron' => 0.6,
                'potassium' => 73,
                'fiber' => 0,
                'calcium' => 24,
                'caffeine' => 0,
                'base_unit_id' => $pieceUnit->id,
                'cost_per_unit' => 0.62,
            ],
        ];

        foreach ($ingredients as $ingredient) {
            $user->ingredients()->create($ingredient);
        }
    }

    /**
     * Create a sample meal with default ingredients for the user.
     */
    private function createSampleMeal(User $user): void
    {
        // Create a sample meal
        $sampleMeal = $user->meals()->create([
            'name' => 'Chicken, Rice & Broccoli',
            'comments' => 'A balanced meal with protein, carbs, and vegetables.',
        ]);

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