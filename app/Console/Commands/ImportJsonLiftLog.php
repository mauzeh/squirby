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
 * 8. Preview import without making changes (dry run):
 *    php artisan lift-log:import-json workout_data.json --user-email=user@example.com --dry-run
 * 
 * 9. Import and set user's global exercise visibility preference:
 *    php artisan lift-log:import-json workout_data.json --user-email=user@example.com --show-global-exercises=false
 * 
 * 10. Fully automated import with preference setting:
 *     php artisan lift-log:import-json data.json --user-email=user@example.com --overwrite --create-exercises --show-global-exercises=true
 * 
 * JSON FORMAT REQUIREMENTS:
 * The JSON file must contain an array of exercise objects with the following structure:
 * 
 * IMPORTANT: Each exercise can only appear ONCE per import (one lift log per exercise per day).
 * Each lift log entry creates multiple identical sets - individual sets cannot have different weights/reps.
 * 
 * [
 *   {
 *     "exercise": "Bench Press",
 *     "canonical_name": "bench_press",
 *     "description": "Barbell bench press exercise for chest development",
 *     "is_bodyweight": false,
 *     "band_type": "resistance", // Optional: "resistance" or "assistance" for banded exercises
 *     "lift_logs": [
 *       {
 *         "weight": 225,
 *         "reps": 5,
 *         "sets": 1,
 *         "band_color": "red", // Optional: color of band used (for banded exercises)
 *         "notes": "Optional notes about this specific lift"
 *       },
 *       {
 *         "weight": 215,
 *         "reps": 6,
 *         "sets": 1,
 *         "notes": "Second set with different weight"
 *       }
 *     ]
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
 * CURRENT LIMITATIONS:
 * These limitations are intentional and will remain until future requirements necessitate changes:
 * 
 * 1. SINGLE LIFT LOG PER EXERCISE PER DAY:
 *    The import does not support multiple lift-log entries for the same exercise on the same day.
 *    Each exercise can only have one lift log entry per import date. If you need to track
 *    multiple sessions of the same exercise on the same day, you must import them separately
 *    with different timestamps or combine them into a single lift log entry.
 * 
 * 2. NO INDIVIDUAL LIFT SET SUPPORT:
 *    The import does not support importing individual lift sets with different weights/reps.
 *    All sets within a lift log must have the same weight and reps. The 'sets' field creates
 *    multiple identical lift sets. For workouts with varying weights/reps per set, you must
 *    create separate lift log entries for each weight/rep combination.
 * 
 * BANDED EXERCISE REQUIREMENTS:
 * For data integrity, banded exercises have strict validation requirements:
 * - If band_type is provided, ALL lift logs must include valid band_color
 * - If any lift log has band_color, the exercise MUST have a valid band_type
 * - Valid band_type values: "resistance" or "assistance"
 * - Valid band_color values: Must be one of the predefined colors (e.g., "red", "blue", "green")
 * - This ensures complete tracking of which specific bands were used
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
 * 
 * For previewing imports before execution:
 *   php artisan lift-log:import-json data.json --user-email=user@example.com --dry-run
 * 
 * For setting user preferences during import:
 *   php artisan lift-log:import-json data.json --user-email=user@example.com --show-global-exercises=false
 */
class ImportJsonLiftLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lift-log:import-json {file} {--user-email=} {--date=} {--overwrite : Overwrite existing lift logs for the same date} {--create-exercises : Automatically create user exercises when not found} {--dry-run : Preview what would be imported without making changes} {--show-global-exercises= : Set user\'s global exercise visibility preference (true/false)}';

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

        $isDryRun = $this->option('dry-run');
        $showGlobalExercisesOption = $this->option('show-global-exercises');
        
        if ($isDryRun) {
            $this->info("DRY RUN MODE - No changes will be made to the database");
            $this->info("Previewing lift log data import for {$user->name} ({$user->email})");
        } else {
            $this->info("Importing lift log data for {$user->name} ({$user->email})");
        }
        $this->info("Date: {$loggedAt->format('Y-m-d H:i:s')}");
        
        // Get current preference for display
        $currentPreference = $user->shouldShowGlobalExercises();
        
        // Handle global exercise preference setting
        if ($showGlobalExercisesOption !== null) {
            $newPreference = filter_var($showGlobalExercisesOption, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            
            if ($newPreference === null) {
                $this->error("Invalid value for --show-global-exercises. Use 'true' or 'false'.");
                return Command::FAILURE;
            }
            
            if ($isDryRun) {
                $this->info("DRY RUN: Would set user's global exercise preference to: " . ($newPreference ? 'enabled' : 'disabled'));
                $this->info("Current preference: " . ($currentPreference ? 'enabled' : 'disabled'));
            } else {
                $user->update(['show_global_exercises' => $newPreference]);
                $this->info("Updated user's global exercise preference to: " . ($newPreference ? 'enabled' : 'disabled'));
                $this->info("Previous preference was: " . ($currentPreference ? 'enabled' : 'disabled'));
            }
        } else if ($isDryRun) {
            // Always show current preference during dry-run, even when option not provided
            $this->info("Current user's global exercise preference: " . ($currentPreference ? 'enabled' : 'disabled'));
        }

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
            
            if ($isDryRun) {
                $this->info('DRY RUN: Would prompt for duplicate handling in actual import');
                $skipDuplicates = true; // Default behavior for dry run
            } else {
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
            }
        } elseif (!empty($duplicates) && $this->option('overwrite')) {
            if ($isDryRun) {
                $this->info('DRY RUN: Would delete existing duplicate lift logs and proceed with import');
            } else {
                $this->deleteDuplicateLiftLogs($duplicates, $user, $loggedAt);
                $this->info('Overwrite flag detected. Existing lift logs deleted. Proceeding with import...');
            }
        }

        // Import each exercise
        $imported = 0;
        $skipped = 0;
        $totalLiftLogs = 0;

        foreach ($exercises as $exerciseData) {
            try {
                $shouldSkip = $skipDuplicates && 
                             !empty($duplicates) && 
                             $this->isDuplicate($exerciseData, $duplicates);
                
                if ($shouldSkip) {
                    $skipped++;
                    $prefix = $isDryRun ? "DRY RUN: Would skip duplicate" : "⚠ Skipped duplicate";
                    $this->line("{$prefix}: {$exerciseData['exercise']}");
                    continue;
                }
                
                if ($isDryRun) {
                    $result = $this->previewExerciseImport($exerciseData, $user, $loggedAt);
                } else {
                    $result = $this->importExercise($exerciseData, $user, $loggedAt);
                }
                
                $imported++;
                $totalLiftLogs += count($result['imported_lift_logs']);
                
                $prefix = $isDryRun ? "DRY RUN: Would import" : "✓ Imported";
                if ($result['exercise_created']) {
                    $this->line("{$prefix}: {$exerciseData['exercise']}");
                } else {
                    $this->line("{$prefix} lift logs for: {$exerciseData['exercise']}");
                }
                
                // Display each imported lift log
                foreach ($result['imported_lift_logs'] as $liftLogInfo) {
                    $arrow = $isDryRun ? "  → Would create" : "  →";
                    $this->line("{$arrow} {$liftLogInfo['weight']}lbs × {$liftLogInfo['reps']} reps × {$liftLogInfo['sets']} sets on {$liftLogInfo['date']}");
                }
                
            } catch (\Exception $e) {
                $skipped++;
                $prefix = $isDryRun ? "DRY RUN: Would skip" : "✗ Skipped";
                $this->warn("{$prefix}: {$exerciseData['exercise']} - {$e->getMessage()}");
            }
        }

        $summaryTitle = $isDryRun ? "\nDry run completed:" : "\nImport completed:";
        $importedLabel = $isDryRun ? "Exercises that would be imported" : "Exercises imported";
        $liftLogsLabel = $isDryRun ? "Total lift logs that would be imported" : "Total lift logs imported";
        $skippedLabel = $isDryRun ? "Exercises that would be skipped" : "Exercises skipped";
        
        $this->info($summaryTitle);
        $this->info("{$importedLabel}: {$imported}");
        $this->info("{$liftLogsLabel}: {$totalLiftLogs}");
        $this->info("{$skippedLabel}: {$skipped}");

        return Command::SUCCESS;
    }



    /**
     * Preview what would be imported for a single exercise (dry-run mode)
     */
    private function previewExerciseImport(array $exerciseData, User $user, Carbon $loggedAt): array
    {
        // Validate banded exercise requirements
        $this->validateBandedExerciseData($exerciseData);

        // Check if exercise exists or would be created
        $result = $this->previewFindOrCreateExercise($exerciseData, $user);

        // Preview each lift log for this exercise
        $liftLogs = $exerciseData['lift_logs'] ?? [];
        $importedLiftLogs = [];
        
        foreach ($liftLogs as $liftLogData) {
            $sets = $liftLogData['sets'] ?? 1;
            
            // Track what would be imported
            $importedLiftLogs[] = [
                'weight' => $liftLogData['weight'],
                'reps' => $liftLogData['reps'],
                'sets' => $sets,
                'date' => $loggedAt->format('Y-m-d H:i:s')
            ];
        }
        
        return [
            'exercise_created' => $result['created'],
            'imported_lift_logs' => $importedLiftLogs
        ];
    }

    /**
     * Import a single exercise with its lift logs
     */
    private function importExercise(array $exerciseData, User $user, Carbon $loggedAt): array
    {
        // Validate banded exercise requirements
        $this->validateBandedExerciseData($exerciseData);

        // Find or create exercise
        $result = $this->findOrCreateExercise($exerciseData, $user);
        $exercise = $result['exercise'];

        // Import each lift log for this exercise
        $liftLogs = $exerciseData['lift_logs'] ?? [];
        $importedLiftLogs = [];
        
        foreach ($liftLogs as $liftLogData) {
            // Create lift log
            $liftLog = LiftLog::create([
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'logged_at' => $loggedAt,
                'comments' => 'Imported from JSON file'
            ]);

            // Create lift sets based on the sets count
            $sets = $liftLogData['sets'] ?? 1;
            
            for ($i = 0; $i < $sets; $i++) {
                $liftSetData = [
                    'lift_log_id' => $liftLog->id,
                    'weight' => $liftLogData['weight'],
                    'reps' => $liftLogData['reps'],
                    'notes' => $liftLogData['notes'] ?? null
                ];

                // Add band_color if provided
                if (isset($liftLogData['band_color']) && !empty($liftLogData['band_color'])) {
                    $liftSetData['band_color'] = $liftLogData['band_color'];
                }

                LiftSet::create($liftSetData);
            }
            
            // Track imported lift log info for display
            $importedLiftLogs[] = [
                'weight' => $liftLogData['weight'],
                'reps' => $liftLogData['reps'],
                'sets' => $sets,
                'date' => $loggedAt->format('Y-m-d H:i:s')
            ];
        }
        
        return [
            'exercise_created' => $result['created'],
            'imported_lift_logs' => $importedLiftLogs
        ];
    }

    /**
     * Preview finding or creating exercise (dry-run mode)
     */
    private function previewFindOrCreateExercise(array $exerciseData, User $user): array
    {
        $canonicalName = $exerciseData['canonical_name'];
        
        // Look in global exercises first
        $exercise = Exercise::global()
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($exercise) {
            return ['exercise' => $exercise, 'created' => false];
        }

        // Look in user-specific exercises
        $userExercise = Exercise::where('user_id', $user->id)
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($userExercise) {
            return ['exercise' => $userExercise, 'created' => false];
        }

        // Exercise not found - would be created
        if ($this->option('create-exercises')) {
            $this->line("⚠ Exercise '{$exerciseData['exercise']}' not found. Would create user-specific exercise...");
            // Return a mock exercise object for preview
            $mockExercise = new Exercise([
                'title' => $exerciseData['exercise'],
                'canonical_name' => $exerciseData['canonical_name'],
                'user_id' => $user->id
            ]);
            return ['exercise' => $mockExercise, 'created' => true];
        }

        // Interactive mode - would prompt user
        $this->warn("Exercise '{$exerciseData['exercise']}' (canonical: {$canonicalName}) not found in global exercises.");
        $this->info('DRY RUN: Would prompt to create new user exercise or map to existing global exercise');
        
        // Return a mock exercise for preview
        $mockExercise = new Exercise([
            'title' => $exerciseData['exercise'],
            'canonical_name' => $exerciseData['canonical_name'],
            'user_id' => $user->id
        ]);
        return ['exercise' => $mockExercise, 'created' => true];
    }

    /**
     * Find existing exercise or create a new one
     */
    private function findOrCreateExercise(array $exerciseData, User $user): array
    {
        $canonicalName = $exerciseData['canonical_name'];
        
        // Look in global exercises first
        $exercise = Exercise::global()
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($exercise) {
            return ['exercise' => $exercise, 'created' => false];
        }

        // Look in user-specific exercises
        $userExercise = Exercise::where('user_id', $user->id)
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($userExercise) {
            return ['exercise' => $userExercise, 'created' => false];
        }

        // Exercise not found in global or user exercises
        if ($this->option('create-exercises')) {
            // Automatically create user exercise without prompting
            $this->line("⚠ Exercise '{$exerciseData['exercise']}' not found. Creating user-specific exercise...");
            $newExercise = $this->createNewUserExercise($exerciseData, $user);
            return ['exercise' => $newExercise, 'created' => true];
        }

        // Interactive mode - prompt user
        $this->warn("Exercise '{$exerciseData['exercise']}' (canonical: {$canonicalName}) not found in global exercises.");
        
        $choice = $this->choice(
            'What would you like to do?',
            ['Create new user exercise', 'Map to existing global exercise'],
            0
        );

        if ($choice === 'Create new user exercise') {
            $newExercise = $this->createNewUserExercise($exerciseData, $user);
            return ['exercise' => $newExercise, 'created' => true];
        } else {
            $existingExercise = $this->mapToExistingExercise($exerciseData);
            return ['exercise' => $existingExercise, 'created' => false];
        }
    }

    /**
     * Create a new user-specific exercise
     */
    private function createNewUserExercise(array $exerciseData, User $user): Exercise
    {
        $exerciseAttributes = [
            'title' => $exerciseData['exercise'],
            'canonical_name' => $exerciseData['canonical_name'],
            'description' => $exerciseData['description'] ?? "Imported from JSON file",
            'is_bodyweight' => $exerciseData['is_bodyweight'] ?? false,
            'user_id' => $user->id // User-specific exercise
        ];

        // Add band_type if provided and valid
        if (isset($exerciseData['band_type']) && in_array($exerciseData['band_type'], ['resistance', 'assistance'])) {
            $exerciseAttributes['band_type'] = $exerciseData['band_type'];
        }

        return Exercise::create($exerciseAttributes);
    }

    /**
     * Validate banded exercise data requirements
     * 
     * For data integrity, banded exercises must have both band_type and band_color:
     * - If band_type is provided, all lift logs must have valid band_color
     * - If any lift log has band_color, exercise must have band_type
     * - band_color must be one of the predefined valid colors
     * 
     * @param array $exerciseData Exercise data from JSON
     * @throws \Exception If validation fails
     */
    private function validateBandedExerciseData(array $exerciseData): void
    {
        $hasBandType = isset($exerciseData['band_type']) && 
                      in_array($exerciseData['band_type'], ['resistance', 'assistance']);
        
        $validBandColors = array_keys(config('bands.colors', []));
        
        $liftLogs = $exerciseData['lift_logs'] ?? [];
        $liftLogsWithBandColor = [];
        $liftLogsWithoutBandColor = [];
        $liftLogsWithInvalidBandColor = [];
        
        foreach ($liftLogs as $index => $liftLog) {
            $bandColor = $liftLog['band_color'] ?? null;
            $hasBandColor = isset($bandColor) && !empty(trim($bandColor));
            
            if ($hasBandColor) {
                $bandColor = trim($bandColor);
                if (in_array($bandColor, $validBandColors)) {
                    $liftLogsWithBandColor[] = $index + 1; // 1-based for user display
                } else {
                    $liftLogsWithInvalidBandColor[] = [
                        'index' => $index + 1,
                        'color' => $bandColor
                    ];
                }
            } else {
                $liftLogsWithoutBandColor[] = $index + 1;
            }
        }
        
        // Rule 1: Check for invalid band colors first
        if (!empty($liftLogsWithInvalidBandColor)) {
            $exerciseName = $exerciseData['exercise'] ?? $exerciseData['canonical_name'];
            $invalidColors = [];
            $invalidLogNumbers = [];
            
            foreach ($liftLogsWithInvalidBandColor as $invalidLog) {
                $invalidColors[] = "'{$invalidLog['color']}'";
                $invalidLogNumbers[] = $invalidLog['index'];
            }
            
            $invalidColorsStr = implode(', ', array_unique($invalidColors));
            $invalidLogsStr = implode(', ', $invalidLogNumbers);
            $validColorsStr = implode(', ', array_map(fn($c) => "'{$c}'", $validBandColors));
            
            throw new \Exception(
                "Exercise '{$exerciseName}' has invalid band_color(s) {$invalidColorsStr} in lift log(s) #{$invalidLogsStr}. " .
                "Valid band colors are: {$validColorsStr}."
            );
        }
        
        // Rule 2: If exercise has band_type, ALL lift logs must have valid band_color
        if ($hasBandType && !empty($liftLogsWithoutBandColor)) {
            $exerciseName = $exerciseData['exercise'] ?? $exerciseData['canonical_name'];
            $bandType = $exerciseData['band_type'];
            $missingColorLogs = implode(', ', $liftLogsWithoutBandColor);
            $validColorsStr = implode(', ', array_map(fn($c) => "'{$c}'", $validBandColors));
            
            throw new \Exception(
                "Exercise '{$exerciseName}' has band_type '{$bandType}' but lift log(s) #{$missingColorLogs} are missing band_color. " .
                "All lift logs for banded exercises must specify a valid band color: {$validColorsStr}."
            );
        }
        
        // Rule 3: If any lift log has band_color, exercise must have band_type
        if ((!empty($liftLogsWithBandColor) || !empty($liftLogsWithInvalidBandColor)) && !$hasBandType) {
            $exerciseName = $exerciseData['exercise'] ?? $exerciseData['canonical_name'];
            $allColoredLogs = array_merge($liftLogsWithBandColor, array_column($liftLogsWithInvalidBandColor, 'index'));
            $coloredLogs = implode(', ', $allColoredLogs);
            $providedBandType = $exerciseData['band_type'] ?? 'not provided';
            
            throw new \Exception(
                "Exercise '{$exerciseName}' has lift log(s) #{$coloredLogs} with band_color but no valid band_type. " .
                "Band type '{$providedBandType}' is invalid. Must be 'resistance' or 'assistance' for banded exercises."
            );
        }
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
            // Try to find the exercise first - check both global and user-specific exercises
            $exercise = Exercise::global()
                ->where('canonical_name', $exerciseData['canonical_name'])
                ->first();
            
            // If not found in global exercises, check user-specific exercises
            if (!$exercise) {
                $exercise = Exercise::where('user_id', $user->id)
                    ->where('canonical_name', $exerciseData['canonical_name'])
                    ->first();
            }
            
            if (!$exercise) {
                continue; // Skip if exercise doesn't exist yet
            }
            
            // Check each lift log in this exercise for duplicates
            $liftLogs = $exerciseData['lift_logs'] ?? [];
            
            foreach ($liftLogs as $liftLogData) {
                // Check for existing lift logs on the same date with same weight/reps
                $existingLog = LiftLog::where('user_id', $user->id)
                    ->where('exercise_id', $exercise->id)
                    ->whereDate('logged_at', $dateOnly)
                    ->whereHas('liftSets', function ($query) use ($liftLogData) {
                        $query->where('weight', $liftLogData['weight'])
                              ->where('reps', $liftLogData['reps']);
                    })
                    ->first();
                
                if ($existingLog) {
                    $duplicates[] = [
                        'exercise' => $exerciseData['exercise'],
                        'canonical_name' => $exerciseData['canonical_name'],
                        'weight' => $liftLogData['weight'],
                        'reps' => $liftLogData['reps'],
                        'exercise_id' => $exercise->id
                    ];
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * Check if an exercise data matches any duplicate
     */
    private function isDuplicate(array $exerciseData, array $duplicates): bool
    {
        $liftLogs = $exerciseData['lift_logs'] ?? [];
        
        foreach ($liftLogs as $liftLogData) {
            foreach ($duplicates as $duplicate) {
                if ($duplicate['canonical_name'] === $exerciseData['canonical_name'] &&
                    $duplicate['weight'] == $liftLogData['weight'] &&
                    $duplicate['reps'] == $liftLogData['reps']) {
                    return true;
                }
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
 *    # Import user data and set their preference for minimal exercise lists
 *    php artisan lift-log:import-json new_user_data.json --user-email=newuser@example.com --show-global-exercises=false
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
 * 8. PREVIEW IMPORTS (DRY RUN):
 *    # Preview what would be imported without making changes
 *    php artisan lift-log:import-json new_data.json --user-email=user@example.com --dry-run
 *    # Preview with all options to see full simulation
 *    php artisan lift-log:import-json data.json --user-email=user@example.com --dry-run --overwrite --create-exercises --show-global-exercises=false
 * 
 * 9. USER PREFERENCE MANAGEMENT:
 *    # Import data and disable global exercises for user who prefers minimal exercise lists
 *    php artisan lift-log:import-json personal_workouts.json --user-email=user@example.com --show-global-exercises=false
 *    # Import data and ensure global exercises are enabled for comprehensive access
 *    php artisan lift-log:import-json comprehensive_data.json --user-email=user@example.com --show-global-exercises=true
 * 
 * TROUBLESHOOTING:
 * 
 * - If exercises don't exist, the command will prompt to create them or map to existing ones
 * - Use --overwrite to skip duplicate prompts in automated scripts
 * - Check that canonical_name values match existing exercises in the database
 * - Ensure the user email exists in the system before importing
 * - Verify JSON format matches the required structure
 * 
 * WORKING WITH LIMITATIONS:
 * 
 * For multiple sessions of same exercise on same day:
 *   # Import morning session
 *   php artisan lift-log:import-json morning_workout.json --user-email=user@example.com --date="2024-01-15 08:00:00"
 *   # Import evening session  
 *   php artisan lift-log:import-json evening_workout.json --user-email=user@example.com --date="2024-01-15 18:00:00"
 * 
 * For sets with different weights (e.g., pyramid sets):
 *   Instead of: 225lbs x 5 reps, 245lbs x 3 reps, 265lbs x 1 rep
 *   Create separate lift log entries:
 *   [
 *     {"exercise": "Bench Press", "lift_logs": [{"weight": 225, "reps": 5, "sets": 1}]},
 *     {"exercise": "Bench Press", "lift_logs": [{"weight": 245, "reps": 3, "sets": 1}]},
 *     {"exercise": "Bench Press", "lift_logs": [{"weight": 265, "reps": 1, "sets": 1}]}
 *   ]
 *   Note: This will create 3 separate lift logs for the same exercise on the same day.
 * 
 * ERROR HANDLING:
 * 
 * - File not found: Check file path and permissions
 * - User not found: Verify email address exists in users table
 * - Invalid JSON: Validate JSON syntax and structure
 * - Exercise mapping: Follow prompts to create or map exercises
 * - Duplicate detection: Choose appropriate action based on your needs
 */