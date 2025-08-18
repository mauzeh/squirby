<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Ingredient::truncate();
        $csvFile = file(database_path('seeders/csv/ingredients_from_real_world.csv'));
        $header = str_getcsv(array_shift($csvFile));

        $units = Unit::all()->keyBy('abbreviation');

        $unitMapping = [
            'gram' => 'g',
            'tbsp' => 'tbsp',
            'tsp' => 'tsp',
            'ml' => 'ml',
            'egg (L)' => 'pc',
            'apple (S)' => 'pc',
            'slice' => 'pc',
            'Pita' => 'pc',
            'can' => 'pc',
            'bottle' => 'pc',
            'shot' => 'pc',
            'raspberries' => 'pc',
        ];

        foreach ($csvFile as $row) {
            if (empty(trim($row))) {
                continue;
            }
            $values = str_getcsv($row);
            if (count($header) !== count($values)) {
                Log::info('Row with different number of columns:');
                Log::info($row);
                continue;
            }

            $rowData = array_combine($header, $values);

            if (empty($rowData['Ingredient'])) {
                continue;
            }

            $unitAbbreviation = $unitMapping[$rowData['Type']] ?? 'pc';
            $unit = $units[$unitAbbreviation] ?? $units['pc'];

            Ingredient::create([
                'name' => $rowData['Ingredient'],
                'calories' => (float)($rowData['Calories'] ?? 0),
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
                'cost_per_unit' => (float)($rowData['Cost ($)'] ?? 0)
            ]);
        }
    }
}
