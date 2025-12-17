<?php

namespace App\Services;

use App\Models\Role;
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
        $this->assignAthleteRole($user);
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
     * Set exercise preferences for new users using configuration values.
     */
    private function setExercisePreferences(User $user): void
    {
        $preferences = config('user_defaults.exercise_preferences');
        
        $user->update($preferences);
    }

    /**
     * Assign the Athlete role to new users.
     */
    private function assignAthleteRole(User $user): void
    {
        $athleteRole = Role::where('name', 'Athlete')->first();
        
        if ($athleteRole) {
            $user->roles()->attach($athleteRole);
        }
    }

    /**
     * Create default measurement types for the user using configuration values.
     */
    private function createMeasurementTypes(User $user): void
    {
        $measurementTypes = config('user_defaults.measurement_types');

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
     * Create basic ingredients directly when no admin user exists using configuration values.
     */
    private function createBasicIngredients(User $user): void
    {
        $configIngredients = config('user_defaults.ingredients');

        foreach ($configIngredients as $ingredientConfig) {
            // Get or create the unit based on the abbreviation
            $unit = $this->getUnitByAbbreviation($ingredientConfig['base_unit']);
            
            $ingredientData = $ingredientConfig;
            $ingredientData['base_unit_id'] = $unit->id;
            unset($ingredientData['base_unit']); // Remove the abbreviation key
            
            $user->ingredients()->create($ingredientData);
        }
    }

    /**
     * Get or create a unit by its abbreviation.
     */
    private function getUnitByAbbreviation(string $abbreviation): Unit
    {
        $unitMappings = [
            'g' => ['name' => 'Gram', 'abbreviation' => 'g', 'conversion_factor' => 1],
            'ml' => ['name' => 'Milliliter', 'abbreviation' => 'ml', 'conversion_factor' => 1],
            'pc' => ['name' => 'Piece', 'abbreviation' => 'pc', 'conversion_factor' => 1],
            'tbsp' => ['name' => 'Tablespoon', 'abbreviation' => 'tbsp', 'conversion_factor' => 15], // 15ml per tbsp
        ];

        $unitData = $unitMappings[$abbreviation] ?? $unitMappings['g']; // Default to grams

        return Unit::firstOrCreate(
            ['abbreviation' => $unitData['abbreviation']],
            $unitData
        );
    }

    /**
     * Create a sample meal with default ingredients for the user using configuration values.
     */
    private function createSampleMeal(User $user): void
    {
        $mealConfig = config('user_defaults.sample_meal');
        
        // Create a sample meal
        $sampleMeal = $user->meals()->create([
            'name' => $mealConfig['name'],
            'comments' => $mealConfig['comments'],
        ]);

        // Attach ingredients to the sample meal
        foreach ($mealConfig['ingredients'] as $ingredientName => $quantity) {
            $ingredient = $user->ingredients()->where('name', $ingredientName)->first();
            
            if ($ingredient) {
                $sampleMeal->ingredients()->attach($ingredient->id, ['quantity' => $quantity]);
            }
        }
    }
}