<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;

class SyncExerciseIntelligence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:sync-intelligence {--file= : Custom JSON file path relative to database/imports/} {--dry-run : Preview changes without executing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes exercise intelligence data from JSON file to the database. Use --file option to specify custom file and --dry-run to preview changes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made to the database');
        }
        
        $this->info('Starting synchronization of exercise intelligence data...');

        // Allow custom file path via --file option
        $customFile = $this->option('file');
        if ($customFile) {
            $jsonPath = database_path('imports/' . $customFile);
        } else {
            $jsonPath = database_path('seeders/json/exercise_intelligence_data.json');
        }

        if (!file_exists($jsonPath)) {
            $this->error('Exercise intelligence JSON file not found at: ' . $jsonPath);
            return Command::FAILURE;
        }

        $jsonData = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error decoding JSON file: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        foreach ($jsonData as $exerciseKey => $data) {
            $exercise = null;
            
            // First try to find by canonical_name (preferred method)
            if (isset($data['canonical_name'])) {
                $exercise = Exercise::where('canonical_name', $data['canonical_name'])
                    ->whereNull('user_id') // Ensure it's a global exercise
                    ->first();
            }
            
            // Fallback to title-based lookup if canonical name lookup fails
            if (!$exercise) {
                $exercise = Exercise::where('title', $exerciseKey)
                    ->whereNull('user_id') // Ensure it's a global exercise
                    ->first();
            }

            if ($exercise) {
                $exerciseIdentifier = $data['canonical_name'] ?? $exerciseKey;
                
                if ($dryRun) {
                    // Check if intelligence already exists
                    $existingIntelligence = ExerciseIntelligence::where('exercise_id', $exercise->id)->first();
                    if ($existingIntelligence) {
                        $this->comment("[DRY RUN] Would UPDATE intelligence for: {$exerciseIdentifier} (Exercise ID: {$exercise->id})");
                    } else {
                        $this->comment("[DRY RUN] Would CREATE intelligence for: {$exerciseIdentifier} (Exercise ID: {$exercise->id})");
                    }
                } else {
                    ExerciseIntelligence::updateOrCreate(
                        ['exercise_id' => $exercise->id],
                        $data
                    );
                    $this->comment("Synchronized intelligence for: {$exerciseIdentifier}");
                }
            } else {
                $this->warn("Exercise not found or not global: {$exerciseKey}. Skipping.");
            }
        }

        if ($dryRun) {
            $this->info('DRY RUN completed - No changes were made to the database.');
        } else {
            $this->info('Exercise intelligence synchronization completed.');
        }
        return Command::SUCCESS;
    }
}
