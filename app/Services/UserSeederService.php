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
     * Create default ingredients for the user.
     */
    private function createDefaultIngredients(User $user): void
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
                'protein' => 31,
                'carbs' => 0,
                'added_sugars' => 0,
                'fats' => 3.6,
                'sodium' => 0,
                'iron' => 0,
                'potassium' => 0,
                'fiber' => 0,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => $gramUnit->id,
            ],
            [
                'name' => 'Rice (dry, brown)',
                'base_quantity' => 45,
                'protein' => 4,
                'carbs' => 34,
                'added_sugars' => 0,
                'fats' => 1.5,
                'sodium' => 0,
                'iron' => 0,
                'potassium' => 0,
                'fiber' => 0,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => $gramUnit->id,
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
            ],
            [
                'name' => 'Olive Oil',
                'base_quantity' => 15,
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
                'base_unit_id' => $milliliterUnit->id,
            ],
            [
                'name' => 'Egg (whole, large)',
                'base_quantity' => 1,
                'protein' => 6,
                'carbs' => 0.6,
                'added_sugars' => 0,
                'fats' => 5,
                'sodium' => 0,
                'iron' => 0,
                'potassium' => 0,
                'fiber' => 0,
                'calcium' => 0,
                'caffeine' => 0,
                'base_unit_id' => $pieceUnit->id,
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
        $rice = $user->ingredients()->where('name', 'Rice (dry, brown)')->first();
        $broccoli = $user->ingredients()->where('name', 'Broccoli (raw)')->first();
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
            $sampleMeal->ingredients()->attach($oliveOil->id, ['quantity' => 10]);
        }
    }
}