<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;

class PersistMissingGlobalExercises extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:persist-missing-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Persist global exercises that are missing from the CSV file';

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
        
        // First, promote all exercises with user_id = 1 to global
        $this->info('Promoting exercises from user_id = 1 to global...');
        $userOneExercises = Exercise::where('user_id', 1)->get();
        
        if ($userOneExercises->isNotEmpty()) {
            foreach ($userOneExercises as $exercise) {
                // Generate canonical name if missing
                if (empty($exercise->canonical_name)) {
                    $canonicalName = strtolower(str_replace([' ', '(', ')', '-', ','], ['_', '', '', '_', ''], $exercise->title));
                    $canonicalName = preg_replace('/_{2,}/', '_', $canonicalName);
                    $canonicalName = trim($canonicalName, '_');
                    $exercise->canonical_name = $canonicalName;
                }
                
                // Promote to global
                $exercise->update(['user_id' => null, 'canonical_name' => $exercise->canonical_name]);
                $this->line("Promoted: {$exercise->title}");
            }
            $this->info("Promoted {$userOneExercises->count()} exercises to global.");
        } else {
            $this->info('No exercises found for user_id = 1 to promote.');
        }
        $this->newLine();
        
        // Then, run the GlobalExercisesSeeder to ensure database is up-to-date
        $this->info('Running GlobalExercisesSeeder to sync CSV with database...');
        $seeder = new \Database\Seeders\GlobalExercisesSeeder($this);
        $seeder->run();
        $this->info('GlobalExercisesSeeder completed.');
        $this->newLine();
        
        $csvPath = database_path('seeders/csv/exercises_from_real_world.csv');
        
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return Command::FAILURE;
        }
        
        // Read existing CSV titles
        $csvLines = file($csvPath);
        $header = str_getcsv(trim($csvLines[0]));
        $csvTitles = [];
        
        for ($i = 1; $i < count($csvLines); $i++) {
            $row = str_getcsv(trim($csvLines[$i]));
            if (!empty($row[0])) {
                $csvTitles[] = $row[0];
            }
        }
        
        // Find global exercises not in CSV
        $globalExercises = Exercise::whereNull('user_id')->get();
        $missingExercises = $globalExercises->filter(function ($exercise) use ($csvTitles) {
            return !in_array($exercise->title, $csvTitles);
        });
        
        if ($missingExercises->isEmpty()) {
            $this->info('No global exercises missing from CSV');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$missingExercises->count()} global exercises missing from CSV:");
        foreach ($missingExercises as $exercise) {
            $this->line("- {$exercise->title}");
        }
        
        if (!$this->confirm('Add these exercises to CSV?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }
        
        // Open CSV for appending
        $csvFile = fopen($csvPath, 'a');
        
        foreach ($missingExercises as $exercise) {
            // Generate canonical name if missing
            if (empty($exercise->canonical_name)) {
                $canonicalName = strtolower(str_replace([' ', '(', ')', '-', ','], ['_', '', '', '_', ''], $exercise->title));
                $canonicalName = preg_replace('/_{2,}/', '_', $canonicalName);
                $canonicalName = trim($canonicalName, '_');
                $exercise->update(['canonical_name' => $canonicalName]);
            }
            
            // Prepare CSV row
            $csvRow = [];
            foreach ($header as $column) {
                switch ($column) {
                    case 'title':
                        $csvRow[] = $exercise->title;
                        break;
                    case 'description':
                        $csvRow[] = $exercise->description ?? '';
                        break;
                    case 'canonical_name':
                        $csvRow[] = $exercise->canonical_name ?? '';
                        break;
                    case 'is_bodyweight':
                        $csvRow[] = $exercise->is_bodyweight ? '1' : '0';
                        break;
                    default:
                        $csvRow[] = '';
                        break;
                }
            }
            
            // Write to CSV
            fputcsv($csvFile, $csvRow);
            
            $this->info("Added to CSV: {$exercise->title} (canonical: {$exercise->canonical_name})");
        }
        
        fclose($csvFile);
        
        $this->info('All missing global exercises added to CSV successfully!');
        return Command::SUCCESS;
    }
}