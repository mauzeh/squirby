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
        $this->setExercisePreferences($user);
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
     * Set exercise preferences for new users.
     * Most preferences are "ON" except prefill_suggested_values which is "OFF".
     */
    private function setExercisePreferences(User $user): void
    {
        $user->update([
            'show_global_exercises' => true,
            'show_extra_weight' => true,
            'prefill_suggested_values' => false,
            'show_recommended_exercises' => true,
            'metrics_first_logging_flow' => true,
        ]);
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
            'Chicken Breast',
            'Brown Rice',
            'Broccoli',
            'Olive Oil',
            'Eggs',
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
                'name' => 'Chicken Breast',
                'base_quantity' => 100,
                'protein' => 31.0,
                'carbs' => 0.0,
                'added_sugars' => 0.0,
                'fats' => 3.6,
                'sodium' => 74,
                'iron' => 0.7,
                'potassium' => 256,
                'fiber' => 0.0,
                'calcium' => 15,
                'caffeine' => 0.0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0.12,
            ],
            [
                'name' => 'Brown Rice',
                'base_quantity' => 100,
                'protein' => 2.6,
                'carbs' => 23.0,
                'added_sugars' => 0.0,
                'fats' => 0.9,
                'sodium' => 5,
                'iron' => 0.4,
                'potassium' => 43,
                'fiber' => 1.8,
                'calcium' => 10,
                'caffeine' => 0.0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0.03,
            ],
            [
                'name' => 'Broccoli',
                'base_quantity' => 100,
                'protein' => 2.8,
                'carbs' => 6.6,
                'added_sugars' => 0.0,
                'fats' => 0.4,
                'sodium' => 33,
                'iron' => 0.7,
                'potassium' => 316,
                'fiber' => 2.6,
                'calcium' => 47,
                'caffeine' => 0.0,
                'base_unit_id' => $gramUnit->id,
                'cost_per_unit' => 0.06,
            ],
            [
                'name' => 'Olive Oil',
                'base_quantity' => 1,
                'protein' => 0.0,
                'carbs' => 0.0,
                'added_sugars' => 0.0,
                'fats' => 13.5,
                'sodium' => 0,
                'iron' => 0.1,
                'potassium' => 0,
                'fiber' => 0.0,
                'calcium' => 0,
                'caffeine' => 0.0,
                'base_unit_id' => Unit::where('abbreviation', 'tbsp')->first()->id ?? $milliliterUnit->id,
                'cost_per_unit' => 0.20,
            ],
            [
                'name' => 'Eggs',
                'base_quantity' => 1,
                'protein' => 6.3,
                'carbs' => 0.6,
                'added_sugars' => 0.0,
                'fats' => 5.3,
                'sodium' => 62,
                'iron' => 0.9,
                'potassium' => 69,
                'fiber' => 0.0,
                'calcium' => 28,
                'caffeine' => 0.0,
                'base_unit_id' => $pieceUnit->id,
                'cost_per_unit' => 0.25,
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

        // Attach ingredients to the sample meal
        $chickenBreast = $user->ingredients()->where('name', 'Chicken Breast')->first();
        $rice = $user->ingredients()->where('name', 'Brown Rice')->first();
        $broccoli = $user->ingredients()->where('name', 'Broccoli')->first();
        $oliveOil = $user->ingredients()->where('name', 'Olive Oil')->first();

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