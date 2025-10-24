<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Class IngredientSeeder
 *
 * This seeder is responsible for populating the 'ingredients' table in the database
 * with a minimal but comprehensive set of hardcoded ingredients covering major food categories.
 * This replaces the previous CSV/TSV-based approach for improved performance and simplicity.
 */
class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the admin user to associate the seeded ingredients with.
        $adminUser = User::where('email', 'admin@example.com')->first();
        
        if (!$adminUser) {
            $this->command->error('Admin user not found. Please run UserSeeder first.');
            return;
        }

        $ingredients = $this->getHardcodedIngredients();
        
        foreach ($ingredients as $ingredientData) {
            // Get the unit by abbreviation
            $unit = Unit::where('abbreviation', $ingredientData['unit_abbreviation'])->first();
            
            if (!$unit) {
                Log::warning('Unit not found for abbreviation: ' . $ingredientData['unit_abbreviation']);
                continue;
            }

            // Create ingredient record
            Ingredient::create([
                'name' => $ingredientData['name'],
                'user_id' => $adminUser->id,
                'protein' => $ingredientData['protein'],
                'carbs' => $ingredientData['carbs'],
                'added_sugars' => $ingredientData['added_sugars'],
                'fats' => $ingredientData['fats'],
                'sodium' => $ingredientData['sodium'],
                'iron' => $ingredientData['iron'],
                'potassium' => $ingredientData['potassium'],
                'fiber' => $ingredientData['fiber'],
                'calcium' => $ingredientData['calcium'],
                'caffeine' => $ingredientData['caffeine'],
                'base_quantity' => $ingredientData['base_quantity'],
                'base_unit_id' => $unit->id,
                'cost_per_unit' => $ingredientData['cost_per_unit']
            ]);
        }

        $this->command->info('Successfully created ' . count($ingredients) . ' ingredients');
    }

    /**
     * Get hardcoded ingredient dataset covering major food categories
     * 
     * @return array Array of ingredient data with complete nutritional information
     */
    private function getHardcodedIngredients(): array
    {
        return [
            // PROTEINS
            [
                'name' => 'Chicken Breast',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
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
                'cost_per_unit' => 0.12
            ],
            [
                'name' => 'Salmon Fillet',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 25.4,
                'carbs' => 0.0,
                'added_sugars' => 0.0,
                'fats' => 13.4,
                'sodium' => 59,
                'iron' => 0.8,
                'potassium' => 363,
                'fiber' => 0.0,
                'calcium' => 12,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.25
            ],
            [
                'name' => 'Eggs',
                'base_quantity' => 1,
                'unit_abbreviation' => 'pc',
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
                'cost_per_unit' => 0.25
            ],
            [
                'name' => 'Greek Yogurt',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 10.0,
                'carbs' => 3.6,
                'added_sugars' => 0.0,
                'fats' => 0.4,
                'sodium' => 36,
                'iron' => 0.1,
                'potassium' => 141,
                'fiber' => 0.0,
                'calcium' => 110,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.08
            ],

            // CARBOHYDRATES
            [
                'name' => 'Brown Rice',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
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
                'cost_per_unit' => 0.03
            ],
            [
                'name' => 'Oats',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 16.9,
                'carbs' => 66.3,
                'added_sugars' => 0.0,
                'fats' => 6.9,
                'sodium' => 2,
                'iron' => 4.7,
                'potassium' => 429,
                'fiber' => 10.6,
                'calcium' => 54,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.04
            ],
            [
                'name' => 'Banana',
                'base_quantity' => 1,
                'unit_abbreviation' => 'pc',
                'protein' => 1.1,
                'carbs' => 22.8,
                'added_sugars' => 0.0,
                'fats' => 0.3,
                'sodium' => 1,
                'iron' => 0.3,
                'potassium' => 358,
                'fiber' => 2.6,
                'calcium' => 5,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.25
            ],
            [
                'name' => 'Sweet Potato',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 2.0,
                'carbs' => 20.1,
                'added_sugars' => 0.0,
                'fats' => 0.1,
                'sodium' => 7,
                'iron' => 0.6,
                'potassium' => 337,
                'fiber' => 3.0,
                'calcium' => 30,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.05
            ],

            // FATS
            [
                'name' => 'Avocado',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 2.0,
                'carbs' => 8.5,
                'added_sugars' => 0.0,
                'fats' => 14.7,
                'sodium' => 7,
                'iron' => 0.6,
                'potassium' => 485,
                'fiber' => 6.7,
                'calcium' => 12,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.15
            ],
            [
                'name' => 'Olive Oil',
                'base_quantity' => 1,
                'unit_abbreviation' => 'tbsp',
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
                'cost_per_unit' => 0.20
            ],
            [
                'name' => 'Almonds',
                'base_quantity' => 30,
                'unit_abbreviation' => 'g',
                'protein' => 6.0,
                'carbs' => 6.1,
                'added_sugars' => 0.0,
                'fats' => 14.2,
                'sodium' => 0,
                'iron' => 1.1,
                'potassium' => 208,
                'fiber' => 3.5,
                'calcium' => 76,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.50
            ],

            // VEGETABLES
            [
                'name' => 'Broccoli',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
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
                'cost_per_unit' => 0.06
            ],
            [
                'name' => 'Spinach',
                'base_quantity' => 100,
                'unit_abbreviation' => 'g',
                'protein' => 2.9,
                'carbs' => 3.6,
                'added_sugars' => 0.0,
                'fats' => 0.4,
                'sodium' => 79,
                'iron' => 2.7,
                'potassium' => 558,
                'fiber' => 2.2,
                'calcium' => 99,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.08
            ],

            // DAIRY
            [
                'name' => 'Milk',
                'base_quantity' => 1,
                'unit_abbreviation' => 'cup',
                'protein' => 8.1,
                'carbs' => 12.2,
                'added_sugars' => 0.0,
                'fats' => 3.2,
                'sodium' => 107,
                'iron' => 0.1,
                'potassium' => 366,
                'fiber' => 0.0,
                'calcium' => 276,
                'caffeine' => 0.0,
                'cost_per_unit' => 0.30
            ]
        ];
    }
}

