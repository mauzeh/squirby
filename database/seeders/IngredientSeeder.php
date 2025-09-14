<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Services\IngredientTsvProcessorService;

/**
 * Class IngredientSeeder
 *
 * This seeder is responsible for populating the 'ingredients' table in the database.
 * It reads ingredient data from a CSV file and uses the IngredientTsvProcessorService
 * to process and import the data.
 *
 * The reason for converting CSV to TSV within the seeder is that the
 * IngredientTsvProcessorService is designed to work with TSV (Tab-Separated Values) data,
 * as it's also used by the user-facing import feature which uses TSV.
 * The source data file (ingredients_from_real_world.csv) is, however, a CSV (Comma-Separated Values) file.
 * To maintain consistency and leverage the shared processing logic in IngredientTsvProcessorService,
 * the CSV content is first converted to TSV format before being passed to the processor.
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

        // Instantiate the IngredientTsvProcessorService which contains the core logic
        // for parsing and processing ingredient data from TSV format.
        $processor = new IngredientTsvProcessorService();
        
        // Read the CSV file content line by line.
        $csvLines = file(database_path('seeders/csv/ingredients_from_real_world.csv'));
        $tsvContent = '';

        // Convert CSV content to TSV content.
        // The IngredientTsvProcessorService expects TSV data, but the source file is CSV.
        // This loop reads each CSV line, parses it using str_getcsv (which handles CSV by default),
        // and then implodes the resulting array with tabs to create a TSV formatted line.
        foreach ($csvLines as $line) {
            $data = str_getcsv($line); // Parses CSV line into an array
            $tsvContent .= implode("\t", $data) . "\n"; // Joins array elements with tabs for TSV
        }

        // Define the expected header for the TSV data.
        // This header must exactly match the format expected by the IngredientTsvProcessorService.
        $expectedHeader = [
            'Ingredient', 'Amount', 'Type', 'Calories', 'Fat (g)', 'Sodium (mg)', 'Carb (g)', 'Fiber (g)', 'Added Sugar (g)', 'Protein (g)', 'Calcium (mg)', 'Potassium (mg)', 'Caffeine (mg)', 'Iron (mg)', 'Cost ($)'
        ];

        // Process the TSV content using the IngredientTsvProcessorService.
        // The processor iterates through each row and applies the provided callback function.
        $result = $processor->processTsv(
            $tsvContent,
            $expectedHeader,
            function ($rowData) use ($adminUser, $processor) {
                // Get the Unit model based on the 'Type' abbreviation from the row data.
                $unit = $processor->getUnitFromAbbreviation($rowData['Type']);

                // If the unit is not found, log an error and skip this row.
                if (!$unit) {
                    Log::info('Unit not found for abbreviation: ' . $rowData['Type']);
                    return;
                }

                // Create a new Ingredient record in the database.
                // Note: Seeders typically create new records and do not update existing ones,
                // unlike the import feature which performs an upsert.
                Ingredient::create([
                    'user_id' => $adminUser->id, // Associate with the admin user
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

        // Log any errors or invalid rows encountered during the processing,
        // which helps in debugging the seeder.
        if (!empty($result['errors'])) {
            Log::error('IngredientSeeder errors: ' . implode(', ', $result['errors']));
        }
        if (!empty($result['invalidRows'])) {
            Log::error('IngredientSeeder invalid rows: ' . implode(', ', $result['invalidRows']));
        }
        Log::info('IngredientSeeder processed count: ' . $result['processedCount']);
    }
}

