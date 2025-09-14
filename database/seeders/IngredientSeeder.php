<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Services\IngredientTsvProcessorService;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();
        $processor = new IngredientTsvProcessorService();

        
        $csvContent = file_get_contents(database_path('seeders/csv/ingredients_from_real_world.csv'));
        $expectedHeader = ['Ingredient', 'Amount', 'Type', 'Calories', 'Fat (g)', 'Sodium (mg)', 'Carb (g)', 'Fiber (g)', 'Added Sugar (g)', 'Protein (g)', 'Calcium (mg)', 'Potassium (mg)', 'Caffeine (mg)', 'Iron (mg)', 'Cost ($)', 'Food Source'];

        $processor->processTsv(
            $csvContent,
            $expectedHeader,
            function ($rowData) use ($adminUser, $processor) {
                $unit = $processor->getUnitFromAbbreviation($rowData['Type']);

                if (!$unit) {
                    Log::info('Unit not found for abbreviation: ' . $rowData['Type']);
                    return;
                }

                Ingredient::create([
                    'user_id' => $adminUser->id,
                    'name' => $rowData['Ingredient'],
                    'protein' => (float)($rowData['Protein (g)'] ?? 0),
                    'carbs' => (float)($rowData['Carb (g)'] ?? 0),
                    'added_sugars' => (float)($rowData['Added Sugar (g)'] ?? 0),
                    'fats' => (float)($rowData['Fat (g)'] ?? 0),
                    'sodium' => (float)($rowData['Sodium (mg)'] ?? 0),
                    'iron' => (float)($rowData['Iron (mg)'] ?? 0),
                    'potassium' => (float)($rowData['Potassium (mg)'] ?? 0),
                    'fiber' => (float)($rowData['Fiber (g)'] ?? 0),
                    'calcium' => (float)($rowData['Calcium (mg)'] ?? 0),
                    'caffeine' => (float)($rowData['Caffeine (mg)'] ?? 0),
                    'base_quantity' => (float)($rowData['Amount'] ?? 1),
                    'base_unit_id' => $unit->id,
                    'cost_per_unit' => (float)(str_replace("$", "", $rowData['Cost ($)']) ?? 0)
                ]);
            }
        );
    }
}
