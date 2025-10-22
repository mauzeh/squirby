<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;

/**
 * Import lift log data from a structured JSON file with duplicate detection and interactive prompts.
 * 
 * This command allows administrators to import workout data for users from JSON files.
 * It includes smart duplicate detection, interactive prompts for handling conflicts,
 * and an overwrite flag for automated workflows.
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Basic import with interactive prompts:
 *    php artisan lift-log:import-json stefan_workout.json --user-email=stefan@example.com
 * 
 * 2. Import for specific date:
 *    php artisan lift-log:import-json workout_data.json --user-email=john@example.com --date="2024-01-15"
 * 
 * 3. Automated import with overwrite (no prompts):
 *    php artisan lift-log:import-json backup_data.json --user-email=maria@example.com --overwrite
 * 
 * 4. Automated import with exercise creation (no prompts):
 *    php artisan lift-log:import-json new_data.json --user-email=user@example.com --create-exercises
 * 
 * 5. Fully automated import (no prompts for duplicates or exercises):
 *    php artisan lift-log:import-json migration_data.json --user-email=admin@example.com --overwrite --create-exercises
 * 
 * 6. Import historical data:
 *    php artisan lift-log:import-json old_workouts.json --user-email=alex@example.com --date="2023-12-01"
 * 
 * 7. Bulk import with overwrite for data migration:
 *    php artisan lift-log:import-json migration_data.json --user-email=admin@example.com --date="2024-02-01" --overwrite
 * 
 * JSON FORMAT REQUIREMENTS:
 * The JSON file must contain an array of exercise objects with the following structure:
 * 
 * [
 *   {
 *     "exercise": "Bench Press",
 *     "canonical_name": "bench_press",
 *     "weight": 225,
 *     "reps": 5,
 *     "sets": 1,
 *     "is_bodyweight": false,
 *     "notes": "Optional notes about the exercise"
 *   }
 * ]
 * 
 * DUPLICATE DETECTION:
 * The command detects duplicates based on:
 * - Same user
 * - Same exercise (by canonical_name)
 * - Same date
 * - Same weight and reps
 * 
 * INTERACTIVE PROMPTS:
 * When duplicates are found (without --overwrite flag), users can choose:
 * - Skip duplicates and import new ones only
 * - Overwrite existing lift logs
 * - Cancel import
 * 
 * EXERCISE MAPPING:
 * If an exercise doesn't exist in the global exercises:
 * - With --create-exercises flag: Automatically creates user-specific exercises
 * - Without flag: Prompts to create new user exercise or map to existing global exercise
 * 
 * ADMIN WORKFLOWS:
 * 
 * For user onboarding:
 *   php artisan lift-log:import-json new_user_data.json --user-email=newuser@example.com
 * 
 * For data corrections (overwrite existing):
 *   php artisan lift-log:import-json corrected_data.json --user-email=user@example.com --overwrite
 * 
 * For historical data import:
 *   php artisan lift-log:import-json historical_2023.json --user-email=user@example.com --date="2023-06-15"
 * 
 * For automated scripts (CI/CD):
 *   php artisan lift-log:import-json backup.json --user-email=user@example.com --overwrite --create-exercises --no-interaction
 */
class ImportJsonLiftLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lift-log:import-json {file} {--user-email=} {--date=} {--overwrite : Overwrite existing lift logs for the same date} {--create-exercises : Automatically create user exercises when not found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import lift log data from a JSON file with duplicate detection and interactive prompts';

    /**
     * Execute the console command.
     * 
     * Main workflow:
     * 1. Validate file and user
     * 2. Parse JSON data
     * 3. Check for duplicates
     * 4. Handle user interaction (if needed)
     * 5. Import exercises with duplicate handling
     * 
     * @return int Command exit code
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $userEmail = $this->option('user-email');
        $date = $this->option('date');

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        // Get or prompt for user email
        if (!$userEmail) {
            $userEmail = $this->ask('Enter the user email to import workouts for');
        }

        // Find user
        $user = User::where('email', $userEmail)->first();
        if (!$user) {
            $this->error("User not found with email: {$userEmail}");
            return Command::FAILURE;
        }

        // Parse date or use today
        $loggedAt = $date ? Carbon::parse($date) : Carbon::now();

        $this->info("Importing lift log data for {$user->name} ({$user->email})");
        $this->info("Date: {$loggedAt->format('Y-m-d H:i:s')}");

        // Read and parse the JSON file
        $content = file_get_contents($filePath);
        $exercises = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON format: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        if (empty($exercises)) {
            $this->warn('No exercises found in the file');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($exercises) . " exercises to import");

        // Check for duplicates
        $duplicates = $this->findDuplicates($exercises, $user, $loggedAt);
        $skipDuplicates = false;
        
        if (!empty($duplicates) && !$this->option('overwrite')) {
            $this->warn("Found " . count($duplicates) . " potential duplicate lift logs:");
            foreach ($duplicates as $duplicate) {
                $this->line("  - {$duplicate['exercise']} ({$duplicate['weight']}lbs x {$duplicate['reps']} reps)");
            }
            
            $choice = $this->choice(
                'What would you like to do?',
                ['Skip duplicates and import new ones only', 'Overwrite existing lift logs', 'Cancel import'],
                0
            );
            
            if ($choice === 'Cancel import') {
                $this->info('Import cancelled by user.');
                return Command::SUCCESS;
            } elseif ($choice === 'Overwrite existing lift logs') {
                $this->deleteDuplicateLiftLogs($duplicates, $user, $loggedAt);
                $this->info('Existing lift logs deleted. Proceeding with import...');
            } elseif ($choice === 'Skip duplicates and import new ones only') {
                $skipDuplicates = true;
            }
        } elseif (!empty($duplicates) && $this->option('overwrite')) {
            $this->deleteDuplicateLiftLogs($duplicates, $user, $loggedAt);
            $this->info('Overwrite flag detected. Existing lift logs deleted. Proceeding with import...');
        }

        // Import each exercise
        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $exerciseData) {
            try {
                $shouldSkip = $skipDuplicates && 
                             !empty($duplicates) && 
                             $this->isDuplicate($exerciseData, $duplicates);
                
                if ($shouldSkip) {
                    $skipped++;
                    $this->line("⚠ Skipped duplicate: {$exerciseData['exercise']}");
                    continue;
                }
                
                $this->importExercise($exerciseData, $user, $loggedAt);
                $imported++;
                $this->line("✓ Imported: {$exerciseData['exercise']}");
            } catch (\Exception $e) {
                $skipped++;
                $this->warn("✗ Skipped: {$exerciseData['exercise']} - {$e->getMessage()}");
            }
        }

        $this->info("\nImport completed:");
        $this->info("Imported: {$imported}");
        $this->info("Skipped: {$skipped}");

        return Command::SUCCESS;
    }



    /**
     * Import a single exercise
     */
    private function importExercise(array $exerciseData, User $user, Carbon $loggedAt): void
    {
        // Find or create exercise
        $exercise = $this->findOrCreateExercise($exerciseData, $user);

        // Create lift log
        $liftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => $loggedAt,
            'comments' => 'Imported from JSON file'
        ]);

        // Create lift sets based on the sets count
        $sets = $exerciseData['sets'] ?? 1;
        
        for ($i = 0; $i < $sets; $i++) {
            LiftSet::create([
                'lift_log_id' => $liftLog->id,
                'weight' => $exerciseData['weight'],
                'reps' => $exerciseData['reps'],
                'notes' => $exerciseData['notes'] ?? null
            ]);
        }
    }

    /**
     * Find existing exercise or create a new one
     */
    private function findOrCreateExercise(array $exerciseData, User $user): Exercise
    {
        $canonicalName = $exerciseData['canonical_name'];
        
        // Look only in global exercises
        $exercise = Exercise::global()
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($exercise) {
            return $exercise;
        }

        // Exercise not found in global exercises
        if ($this->option('create-exercises')) {
            // Automatically create user exercise without prompting
            $this->line("⚠ Exercise '{$exerciseData['exercise']}' not found. Creating user-specific exercise...");
            return $this->createNewUserExercise($exerciseData, $user);
        }

        // Interactive mode - prompt user
        $this->warn("Exercise '{$exerciseData['exercise']}' (canonical: {$canonicalName}) not found in global exercises.");
        
        $choice = $this->choice(
            'What would you like to do?',
            ['Create new user exercise', 'Map to existing global exercise'],
            0
        );

        if ($choice === 'Create new user exercise') {
            return $this->createNewUserExercise($exerciseData, $user);
        } else {
            return $this->mapToExistingExercise($exerciseData);
        }
    }

    /**
     * Create a new user-specific exercise
     */
    private function createNewUserExercise(array $exerciseData, User $user): Exercise
    {
        return Exercise::create([
            'title' => $exerciseData['exercise'],
            'canonical_name' => $exerciseData['canonical_name'],
            'description' => "Imported from JSON file",
            'is_bodyweight' => $exerciseData['is_bodyweight'] ?? false,
            'user_id' => $user->id // User-specific exercise
        ]);
    }

    /**
     * Map to an existing exercise by canonical name
     */
    private function mapToExistingExercise(array $exerciseData): Exercise
    {
        while (true) {
            $existingCanonicalName = $this->ask('Enter the canonical name of the existing exercise to map to');
            
            $exercise = Exercise::global()
                ->where('canonical_name', $existingCanonicalName)
                ->first();

            if ($exercise) {
                $this->info("Mapping '{$exerciseData['exercise']}' to '{$exercise->title}'");
                return $exercise;
            } else {
                $this->error("Exercise with canonical name '{$existingCanonicalName}' not found in global exercises.");
                $this->info("Available global exercises:");
                
                $globalExercises = Exercise::global()
                    ->select('title', 'canonical_name')
                    ->orderBy('title')
                    ->get();
                
                foreach ($globalExercises as $globalExercise) {
                    $this->line("  - {$globalExercise->title} (canonical: {$globalExercise->canonical_name})");
                }
                
                if (!$this->confirm('Try again?')) {
                    throw new \Exception('User cancelled exercise mapping');
                }
            }
        }
    }

    /**
     * Find duplicate lift logs for the given exercises and date.
     * 
     * Duplicates are identified by matching:
     * - User ID
     * - Exercise ID (from canonical_name)
     * - Date (same day, ignoring time)
     * - Weight and reps (exact match)
     * 
     * @param array $exercises Array of exercise data from JSON
     * @param User $user The target user for import
     * @param Carbon $loggedAt The target date for import
     * @return array Array of duplicate exercise data
     */
    private function findDuplicates(array $exercises, User $user, Carbon $loggedAt): array
    {
        $duplicates = [];
        $dateOnly = $loggedAt->format('Y-m-d');
        
        foreach ($exercises as $exerciseData) {
            // Try to find the exercise first
            $exercise = Exercise::global()
                ->where('canonical_name', $exerciseData['canonical_name'])
                ->first();
            
            if (!$exercise) {
                continue; // Skip if exercise doesn't exist yet
            }
            
            // Check for existing lift logs on the same date with same weight/reps
            $existingLog = LiftLog::where('user_id', $user->id)
                ->where('exercise_id', $exercise->id)
                ->whereDate('logged_at', $dateOnly)
                ->whereHas('liftSets', function ($query) use ($exerciseData) {
                    $query->where('weight', $exerciseData['weight'])
                          ->where('reps', $exerciseData['reps']);
                })
                ->first();
            
            if ($existingLog) {
                $duplicates[] = [
                    'exercise' => $exerciseData['exercise'],
                    'canonical_name' => $exerciseData['canonical_name'],
                    'weight' => $exerciseData['weight'],
                    'reps' => $exerciseData['reps'],
                    'exercise_id' => $exercise->id
                ];
            }
        }
        
        return $duplicates;
    }

    /**
     * Check if an exercise data matches any duplicate
     */
    private function isDuplicate(array $exerciseData, array $duplicates): bool
    {
        foreach ($duplicates as $duplicate) {
            if ($duplicate['canonical_name'] === $exerciseData['canonical_name'] &&
                $duplicate['weight'] == $exerciseData['weight'] &&
                $duplicate['reps'] == $exerciseData['reps']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete existing lift logs that match the duplicates.
     * 
     * This method safely removes lift logs and their associated lift sets
     * for the specified duplicates. It only affects exact matches based on
     * the duplicate detection criteria.
     * 
     * @param array $duplicates Array of duplicate data to delete
     * @param User $user The user whose lift logs to delete
     * @param Carbon $loggedAt The date to filter by
     */
    private function deleteDuplicateLiftLogs(array $duplicates, User $user, Carbon $loggedAt): void
    {
        $dateOnly = $loggedAt->format('Y-m-d');
        
        foreach ($duplicates as $duplicate) {
            $liftLogs = LiftLog::where('user_id', $user->id)
                ->where('exercise_id', $duplicate['exercise_id'])
                ->whereDate('logged_at', $dateOnly)
                ->whereHas('liftSets', function ($query) use ($duplicate) {
                    $query->where('weight', $duplicate['weight'])
                          ->where('reps', $duplicate['reps']);
                })
                ->get();
            
            foreach ($liftLogs as $liftLog) {
                // Delete associated lift sets first
                $liftLog->liftSets()->delete();
                // Then delete the lift log
                $liftLog->delete();
            }
        }
    }
}

/*
 * ADDITIONAL ADMIN EXAMPLES AND USE CASES:
 * 
 * 1. ONBOARDING NEW USERS:
 *    # Import Stefan's historical workout data
 *    php artisan lift-log:import-json stefan_workout_formatted.json --user-email=stefan@swaans.com --date="2024-01-15"
 * 
 * 2. DATA MIGRATION FROM OTHER SYSTEMS:
 *    # Migrate from MyFitnessPal export
 *    php artisan lift-log:import-json myfitnesspal_export.json --user-email=user@example.com --overwrite
 * 
 * 3. BULK IMPORT FOR MULTIPLE DATES:
 *    # Import January workouts
 *    php artisan lift-log:import-json january_workouts.json --user-email=athlete@example.com --date="2024-01-01"
 *    # Import February workouts  
 *    php artisan lift-log:import-json february_workouts.json --user-email=athlete@example.com --date="2024-02-01"
 * 
 * 4. CORRECTING IMPORTED DATA:
 *    # Fix incorrect data by overwriting
 *    php artisan lift-log:import-json corrected_workouts.json --user-email=user@example.com --date="2024-01-10" --overwrite
 * 
 * 5. AUTOMATED BACKUP RESTORATION:
 *    # Restore from backup without prompts
 *    php artisan lift-log:import-json backup_20240115.json --user-email=user@example.com --overwrite --create-exercises --no-interaction
 * 
 * 6. TESTING WITH SAMPLE DATA:
 *    # Import test data for development
 *    php artisan lift-log:import-json test_workouts.json --user-email=testuser@example.com
 * 
 * 7. IMPORTING COMPETITION DATA:
 *    # Import powerlifting meet results
 *    php artisan lift-log:import-json meet_results.json --user-email=powerlifter@example.com --date="2024-03-15"
 * 
 * TROUBLESHOOTING:
 * 
 * - If exercises don't exist, the command will prompt to create them or map to existing ones
 * - Use --overwrite to skip duplicate prompts in automated scripts
 * - Check that canonical_name values match existing exercises in the database
 * - Ensure the user email exists in the system before importing
 * - Verify JSON format matches the required structure
 * 
 * ERROR HANDLING:
 * 
 * - File not found: Check file path and permissions
 * - User not found: Verify email address exists in users table
 * - Invalid JSON: Validate JSON syntax and structure
 * - Exercise mapping: Follow prompts to create or map exercises
 * - Duplicate detection: Choose appropriate action based on your needs
 */