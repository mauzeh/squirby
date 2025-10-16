<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;

class GlobalExercisesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/csv/exercises_from_real_world.csv');
        
        // Check if CSV file exists
        if (!file_exists($csvPath)) {
            throw new \Exception("CSV file not found: {$csvPath}. Please ensure the exercises CSV file exists.");
        }
        
        // Read the CSV file content line by line
        $csvLines = file($csvPath);
        
        // Check if file reading was successful
        if ($csvLines === false) {
            throw new \Exception("Failed to read CSV file: {$csvPath}. Please check file permissions.");
        }
        
        // Process each CSV line directly (simpler approach than IngredientSeeder)
        $header = null;
        
        foreach ($csvLines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue; // Skip empty lines
            }

            try {
                $rowData = str_getcsv($line); // Parse CSV line into an array
                
                // First line is the header
                if ($lineNumber === 0) {
                    $header = $rowData;
                    continue;
                }
                
                // Ensure we have the same number of columns as the header
                if (count($rowData) !== count($header)) {
                    continue; // Skip malformed rows
                }
                
                // Create associative array from header and row data
                $exerciseData = array_combine($header, $rowData);
                
                // Skip rows with empty or missing title
                if (!isset($exerciseData['title']) || empty(trim($exerciseData['title']))) {
                    continue;
                }

                // Convert is_bodyweight to boolean (1/true becomes true, others become false)
                $isBodyweight = isset($exerciseData['is_bodyweight']) && 
                               in_array(strtolower($exerciseData['is_bodyweight']), ['1', 'true']);

                // Create exercise array for firstOrCreate
                $exercise = [
                    'title' => $exerciseData['title'],
                    'description' => $exerciseData['description'] ?? '',
                    'user_id' => null,
                    'canonical_name' => $exerciseData['canonical_name'] ?? null,
                ];

                // Only add is_bodyweight if it's true (to match original seeder behavior)
                if ($isBodyweight) {
                    $exercise['is_bodyweight'] = true;
                }

                Exercise::firstOrCreate(
                    ['title' => $exercise['title'], 'user_id' => null],
                    $exercise
                );
            } catch (\Exception $e) {
                // Skip malformed rows gracefully and continue processing
                continue;
            }
        }
    }
}