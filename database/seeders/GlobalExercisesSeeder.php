<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;

class GlobalExercisesSeeder extends Seeder
{
    protected $console;
    
    public function __construct($console = null)
    {
        $this->console = $console;
    }
    
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
        $processedCount = 0;
        
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

                // Handle band_type field
                if (isset($exerciseData['band_type']) && !empty(trim($exerciseData['band_type']))) {
                    $bandType = trim($exerciseData['band_type']);
                    $validBandTypes = ['resistance', 'assistance'];
                    if (in_array($bandType, $validBandTypes)) {
                        $exercise['band_type'] = $bandType;
                    }
                }

                // Check if exercise exists to determine if it's new or updated
                $existingExercise = Exercise::where('title', $exercise['title'])
                    ->whereNull('user_id')
                    ->first();
                
                $exerciseModel = Exercise::updateOrCreate(
                    ['title' => $exercise['title'], 'user_id' => null],
                    $exercise
                );
                
                // Output changes if console is available
                if ($this->console) {
                    if (!$existingExercise) {
                        $this->console->line("Created: {$exercise['title']}");
                    } else {
                        // Check what changed
                        $changes = [];
                        if ($existingExercise->canonical_name !== $exercise['canonical_name']) {
                            $changes[] = "canonical_name: '{$existingExercise->canonical_name}' → '{$exercise['canonical_name']}'";
                        }
                        if ($existingExercise->description !== $exercise['description']) {
                            $changes[] = "description updated";
                        }
                        if (($existingExercise->is_bodyweight ?? false) !== ($isBodyweight ?? false)) {
                            $changes[] = "is_bodyweight: " . ($existingExercise->is_bodyweight ? 'true' : 'false') . " → " . ($isBodyweight ? 'true' : 'false');
                        }
                        if (($existingExercise->band_type ?? null) !== ($exercise['band_type'] ?? null)) {
                            $oldBandType = $existingExercise->band_type ?? 'null';
                            $newBandType = $exercise['band_type'] ?? 'null';
                            $changes[] = "band_type: '{$oldBandType}' → '{$newBandType}'";
                        }
                        
                        if (!empty($changes)) {
                            $this->console->line("Updated: {$exercise['title']} (" . implode(', ', $changes) . ")");
                        }
                    }
                }
                
                $processedCount++;
            } catch (\Exception $e) {
                // Skip malformed rows gracefully and continue processing
                continue;
            }
        }
        
        if ($this->console) {
            $this->console->info("Processed {$processedCount} exercises from CSV");
        }
    }
}