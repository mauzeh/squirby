<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;

class PersistGlobalExercises extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:persist-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize all global exercises from database to CSV file';

    /**
     * Collect global exercises from database with band_type field included.
     * Skip exercises without canonical_name.
     */
    private function collectGlobalExercises()
    {
        $globalExercises = Exercise::whereNull('user_id')
            ->select('title', 'description', 'canonical_name', 'is_bodyweight', 'band_type')
            ->get();
        
        // Filter out exercises without canonical_name and warn about them
        $validExercises = $globalExercises->filter(function ($exercise) {
            if (empty($exercise->canonical_name)) {
                $this->warn("Skipping exercise '{$exercise->title}' - missing canonical name");
                return false;
            }
            return true;
        });
        
        return $validExercises;
    }

    /**
     * Parse existing CSV file and index by canonical_name.
     * Includes proper error handling for malformed CSV entries.
     */
    private function parseExistingCsv(string $csvPath): array
    {
        $csvData = [];
        $header = [];
        
        try {
            $csvLines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if (empty($csvLines)) {
                $this->warn('CSV file is empty');
                return [];
            }
            
            // Parse header
            $header = str_getcsv(trim($csvLines[0]));
            
            // Validate required columns exist
            $requiredColumns = ['title', 'description', 'canonical_name', 'is_bodyweight', 'band_type'];
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $header)) {
                    $this->warn("Missing required column '{$column}' in CSV header");
                }
            }
            
            // Parse data rows
            for ($i = 1; $i < count($csvLines); $i++) {
                $lineNumber = $i + 1;
                
                try {
                    $row = str_getcsv(trim($csvLines[$i]));
                    
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }
                    
                    // Ensure row has enough columns
                    if (count($row) < count($header)) {
                        $this->warn("Row {$lineNumber}: Insufficient columns, padding with empty values");
                        $row = array_pad($row, count($header), '');
                    }
                    
                    // Create associative array
                    $rowData = array_combine($header, $row);
                    
                    // Skip rows without canonical_name
                    if (empty($rowData['canonical_name'])) {
                        $this->warn("Row {$lineNumber}: Skipping row with empty canonical_name");
                        continue;
                    }
                    
                    // Index by canonical_name for fast lookup
                    $csvData[$rowData['canonical_name']] = [
                        'title' => $rowData['title'] ?? '',
                        'description' => $rowData['description'] ?? '',
                        'canonical_name' => $rowData['canonical_name'],
                        'is_bodyweight' => $rowData['is_bodyweight'] ?? '0',
                        'band_type' => $rowData['band_type'] ?? '',
                        'line_number' => $lineNumber
                    ];
                    
                } catch (\Exception $e) {
                    $this->warn("Row {$lineNumber}: Malformed CSV entry - {$e->getMessage()}");
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to parse CSV file: {$e->getMessage()}");
            return [];
        }
        
        return $csvData;
    }

    /**
     * Compare exercise data between database and CSV.
     * Returns detailed array of differences between database and CSV data.
     * Handles null values and data type conversions properly.
     */
    private function compareExercises($dbExercise, array $csvData): array
    {
        $differences = [];
        
        // Compare title
        $dbTitle = $dbExercise->title ?? '';
        $csvTitle = $csvData['title'] ?? '';
        if ($dbTitle !== $csvTitle) {
            $differences['title'] = [
                'database' => $dbTitle,
                'csv' => $csvTitle,
                'changed' => true
            ];
        }
        
        // Compare description
        $dbDescription = $dbExercise->description ?? '';
        $csvDescription = $csvData['description'] ?? '';
        if ($dbDescription !== $csvDescription) {
            $differences['description'] = [
                'database' => $dbDescription,
                'csv' => $csvDescription,
                'changed' => true
            ];
        }
        
        // Compare is_bodyweight (handle boolean to string conversion)
        $dbIsBodyweight = $dbExercise->is_bodyweight ? '1' : '0';
        $csvIsBodyweight = $csvData['is_bodyweight'] ?? '0';
        // Normalize CSV value to '1' or '0'
        $csvIsBodyweight = in_array($csvIsBodyweight, ['1', 'true', true], true) ? '1' : '0';
        if ($dbIsBodyweight !== $csvIsBodyweight) {
            $differences['is_bodyweight'] = [
                'database' => $dbIsBodyweight,
                'csv' => $csvIsBodyweight,
                'changed' => true
            ];
        }
        
        // Compare band_type (handle null values)
        $dbBandType = $dbExercise->band_type ?? '';
        $csvBandType = $csvData['band_type'] ?? '';
        if ($dbBandType !== $csvBandType) {
            $differences['band_type'] = [
                'database' => $dbBandType,
                'csv' => $csvBandType,
                'changed' => true
            ];
        }
        
        return $differences;
    }

    /**
     * Identify changes needed by matching exercises by canonical_name.
     * Categorizes exercises as "update needed", "new entry", or "no change".
     * Tracks specific field changes for reporting.
     */
    private function identifyChanges($globalExercises, array $csvData): array
    {
        $changes = [
            'updates_needed' => [],
            'new_entries' => [],
            'no_change' => [],
            'summary' => [
                'total_global_exercises' => $globalExercises->count(),
                'updates_count' => 0,
                'new_entries_count' => 0,
                'no_change_count' => 0
            ]
        ];
        
        foreach ($globalExercises as $exercise) {
            $canonicalName = $exercise->canonical_name;
            
            // Check if exercise exists in CSV
            if (isset($csvData[$canonicalName])) {
                // Exercise exists in CSV, check for differences
                $differences = $this->compareExercises($exercise, $csvData[$canonicalName]);
                
                if (!empty($differences)) {
                    // Exercise needs update
                    $changes['updates_needed'][$canonicalName] = [
                        'exercise' => $exercise,
                        'csv_data' => $csvData[$canonicalName],
                        'differences' => $differences,
                        'field_changes' => array_keys($differences)
                    ];
                    $changes['summary']['updates_count']++;
                } else {
                    // No changes needed
                    $changes['no_change'][$canonicalName] = [
                        'exercise' => $exercise,
                        'csv_data' => $csvData[$canonicalName]
                    ];
                    $changes['summary']['no_change_count']++;
                }
            } else {
                // Exercise doesn't exist in CSV, needs to be added
                $changes['new_entries'][$canonicalName] = [
                    'exercise' => $exercise
                ];
                $changes['summary']['new_entries_count']++;
            }
        }
        
        return $changes;
    }

    /**
     * Display comprehensive change reporting to the user.
     * Shows total exercises found, updates needed, and new entries.
     */
    private function displayChangeReport(array $changes): void
    {
        $summary = $changes['summary'];
        
        // Display summary statistics
        $this->info("=== Global Exercise Synchronization Report ===");
        $this->info("Total global exercises found: {$summary['total_global_exercises']}");
        $this->info("Exercises requiring updates: {$summary['updates_count']}");
        $this->info("New exercises to be added: {$summary['new_entries_count']}");
        $this->info("Exercises with no changes: {$summary['no_change_count']}");
        $this->newLine();
        
        // Display exercises requiring updates
        if (!empty($changes['updates_needed'])) {
            $this->info("=== Exercises Requiring Updates ===");
            foreach ($changes['updates_needed'] as $canonicalName => $updateInfo) {
                $exercise = $updateInfo['exercise'];
                $differences = $updateInfo['differences'];
                
                $this->line("• {$exercise->title} (canonical: {$canonicalName})");
                
                foreach ($differences as $field => $diff) {
                    $dbValue = $diff['database'] === '' ? '(empty)' : $diff['database'];
                    $csvValue = $diff['csv'] === '' ? '(empty)' : $diff['csv'];
                    $this->line("  - {$field}: '{$csvValue}' → '{$dbValue}'");
                }
                $this->newLine();
            }
        }
        
        // Display new exercises to be added
        if (!empty($changes['new_entries'])) {
            $this->info("=== New Exercises to be Added ===");
            foreach ($changes['new_entries'] as $canonicalName => $entryInfo) {
                $exercise = $entryInfo['exercise'];
                $this->line("• {$exercise->title} (canonical: {$canonicalName})");
                
                // Show key details of the new exercise
                $details = [];
                if ($exercise->description) {
                    $details[] = "description: '{$exercise->description}'";
                }
                if ($exercise->is_bodyweight) {
                    $details[] = "bodyweight: yes";
                }
                if ($exercise->band_type) {
                    $details[] = "band_type: '{$exercise->band_type}'";
                }
                
                if (!empty($details)) {
                    $this->line("  " . implode(', ', $details));
                }
            }
            $this->newLine();
        }
        
        // Show total changes summary
        $totalChanges = $summary['updates_count'] + $summary['new_entries_count'];
        if ($totalChanges > 0) {
            $this->info("Total changes to be made: {$totalChanges}");
        } else {
            $this->info("No changes needed - CSV is already synchronized with database.");
        }
    }

    /**
     * Synchronize CSV file with database changes.
     * Writes entire CSV file with updated and new data while maintaining proper formatting.
     */
    private function synchronizeCsv(string $csvPath, array $changes, array $existingCsvData): void
    {
        // Define CSV header structure
        $header = ['title', 'description', 'is_bodyweight', 'canonical_name', 'band_type'];
        
        // Prepare all exercise data for CSV writing
        $allExercises = [];
        
        // First, add existing exercises (updated or unchanged)
        foreach ($existingCsvData as $canonicalName => $csvEntry) {
            if (isset($changes['updates_needed'][$canonicalName])) {
                // Use updated data from database
                $exercise = $changes['updates_needed'][$canonicalName]['exercise'];
                $allExercises[$canonicalName] = $this->prepareExerciseForCsv($exercise);
            } elseif (isset($changes['no_change'][$canonicalName])) {
                // Keep existing CSV data
                $allExercises[$canonicalName] = [
                    'title' => $csvEntry['title'],
                    'description' => $csvEntry['description'],
                    'is_bodyweight' => $csvEntry['is_bodyweight'],
                    'canonical_name' => $csvEntry['canonical_name'],
                    'band_type' => $csvEntry['band_type']
                ];
            }
        }
        
        // Then, append new exercises at the end
        foreach ($changes['new_entries'] as $canonicalName => $entryInfo) {
            $exercise = $entryInfo['exercise'];
            $allExercises[$canonicalName] = $this->prepareExerciseForCsv($exercise);
        }
        
        // Write the complete CSV file
        $this->writeCsvFile($csvPath, $header, $allExercises);
    }
    
    /**
     * Prepare exercise data for CSV format.
     * Handles data type conversions and null value handling.
     */
    private function prepareExerciseForCsv($exercise): array
    {
        return [
            'title' => $exercise->title ?? '',
            'description' => $exercise->description ?? '',
            'is_bodyweight' => $exercise->is_bodyweight ? '1' : '0',
            'canonical_name' => $exercise->canonical_name ?? '',
            'band_type' => $exercise->band_type ?? ''
        ];
    }
    
    /**
     * Write complete CSV file with proper formatting and error handling.
     * Maintains CSV structure and handles file operations safely.
     */
    private function writeCsvFile(string $csvPath, array $header, array $allExercises): void
    {
        // Create backup of original file
        $backupPath = $csvPath . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($csvPath, $backupPath)) {
            throw new \Exception("Failed to create backup file: {$backupPath}");
        }
        
        // Open file for writing
        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            throw new \Exception("Failed to open CSV file for writing: {$csvPath}");
        }
        
        try {
            // Write header
            if (fputcsv($handle, $header) === false) {
                throw new \Exception("Failed to write CSV header");
            }
            
            // Write all exercise data
            foreach ($allExercises as $exerciseData) {
                // Prepare row data in header order
                $row = [];
                foreach ($header as $column) {
                    $row[] = $exerciseData[$column] ?? '';
                }
                
                if (fputcsv($handle, $row) === false) {
                    throw new \Exception("Failed to write CSV row for exercise: {$exerciseData['canonical_name']}");
                }
            }
            
            // Ensure data is written to disk
            if (fflush($handle) === false) {
                throw new \Exception("Failed to flush CSV file buffer");
            }
            
        } finally {
            // Always close file handle
            if (fclose($handle) === false) {
                $this->warn("Warning: Failed to properly close CSV file handle");
            }
        }
        
        // Verify file was written successfully
        if (!file_exists($csvPath) || filesize($csvPath) === 0) {
            throw new \Exception("CSV file appears to be empty or missing after write operation");
        }
        
        $this->info("Backup created: {$backupPath}");
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Only allow this command to run in local environment
        if (!app()->environment('local')) {
            $this->error('This command can only be run in local environment for security reasons.');
            return Command::FAILURE;
        }
                
        $csvPath = database_path('seeders/csv/exercises_from_real_world.csv');
        
        // Comprehensive CSV file validation
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return Command::FAILURE;
        }
        
        if (!is_readable($csvPath)) {
            $this->error("CSV file is not readable: {$csvPath}");
            return Command::FAILURE;
        }
        
        if (!is_writable($csvPath)) {
            $this->error("CSV file is not writable: {$csvPath}");
            return Command::FAILURE;
        }
        
        if (filesize($csvPath) === 0) {
            $this->error("CSV file is empty: {$csvPath}");
            return Command::FAILURE;
        }
        
        // Check if directory is writable for backup creation
        $csvDir = dirname($csvPath);
        if (!is_writable($csvDir)) {
            $this->error("CSV directory is not writable for backup creation: {$csvDir}");
            return Command::FAILURE;
        }
        
        // Collect global exercises and parse existing CSV data
        $globalExercises = $this->collectGlobalExercises();
        $csvData = $this->parseExistingCsv($csvPath);
        
        // Identify all changes needed
        $changes = $this->identifyChanges($globalExercises, $csvData);
        
        // Display comprehensive change report
        $this->displayChangeReport($changes);
        
        // Check if any changes are needed
        $totalChanges = $changes['summary']['updates_count'] + $changes['summary']['new_entries_count'];
        if ($totalChanges === 0) {
            $this->info('CSV is already synchronized with database. No changes needed.');
            return Command::SUCCESS;
        }
        
        // Ask for user confirmation before making changes
        if (!$this->confirm("Do you want to proceed with these {$totalChanges} changes?")) {
            $this->info('Operation cancelled by user.');
            return Command::SUCCESS;
        }
        
        // Perform CSV synchronization
        try {
            $this->synchronizeCsv($csvPath, $changes, $csvData);
            $this->info("Successfully synchronized {$totalChanges} changes to CSV file.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to synchronize CSV: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}