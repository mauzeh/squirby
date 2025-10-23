<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;

/**
 * Synchronize exercise intelligence data from JSON files to the database with flexible targeting options.
 * 
 * This command imports biomechanical and training data for exercises, including muscle activation patterns,
 * movement archetypes, difficulty levels, and recovery requirements. It supports both global and user-specific
 * exercises with comprehensive preview and validation capabilities.
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Basic sync with default intelligence file:
 *    php artisan exercises:sync-intelligence
 * 
 * 2. Sync custom intelligence data:
 *    php artisan exercises:sync-intelligence --file=stefan_intelligence.json
 * 
 * 3. Preview changes without executing (dry run):
 *    php artisan exercises:sync-intelligence --file=custom_data.json --dry-run
 * 
 * 4. Include user exercises in synchronization:
 *    php artisan exercises:sync-intelligence --include-user-exercises
 * 
 * 5. Sync custom file with user exercises included:
 *    php artisan exercises:sync-intelligence --file=user_data.json --include-user-exercises
 * 
 * 6. Preview sync including user exercises:
 *    php artisan exercises:sync-intelligence --file=test_data.json --include-user-exercises --dry-run
 * 
 * 7. Update specific user's exercise intelligence:
 *    php artisan exercises:sync-intelligence --file=personal_intelligence.json --include-user-exercises
 * 
 * 8. Bulk intelligence update for global exercises:
 *    php artisan exercises:sync-intelligence --file=comprehensive_intelligence.json
 * 
 * 9. Test intelligence data before applying:
 *    php artisan exercises:sync-intelligence --file=new_intelligence.json --dry-run
 * 
 * 10. Full sync with all options:
 *     php artisan exercises:sync-intelligence --file=complete_data.json --include-user-exercises --dry-run
 * 
 * JSON FORMAT REQUIREMENTS:
 * The JSON file must contain an object with exercise canonical names as keys and intelligence data as values:
 * 
 * {
 *   "bench_press": {
 *     "canonical_name": "bench_press",
 *     "muscle_data": {
 *       "muscles": [
 *         {
 *           "name": "pectoralis_major",
 *           "role": "primary_mover",
 *           "contraction_type": "isotonic"
 *         },
 *         {
 *           "name": "anterior_deltoid",
 *           "role": "synergist",
 *           "contraction_type": "isotonic"
 *         },
 *         {
 *           "name": "rectus_abdominis",
 *           "role": "stabilizer",
 *           "contraction_type": "isometric"
 *         }
 *       ]
 *     },
 *     "primary_mover": "pectoralis_major",
 *     "largest_muscle": "pectoralis_major",
 *     "movement_archetype": "push",
 *     "category": "strength",
 *     "difficulty_level": 3,
 *     "recovery_hours": 48
 *   },
 *   "squat": {
 *     "canonical_name": "squat",
 *     "muscle_data": {
 *       "muscles": [
 *         {
 *           "name": "rectus_femoris",
 *           "role": "primary_mover",
 *           "contraction_type": "isotonic"
 *         },
 *         {
 *           "name": "gluteus_maximus",
 *           "role": "primary_mover",
 *           "contraction_type": "isotonic"
 *         }
 *       ]
 *     },
 *     "primary_mover": "rectus_femoris",
 *     "largest_muscle": "gluteus_maximus",
 *     "movement_archetype": "squat",
 *     "category": "strength",
 *     "difficulty_level": 4,
 *     "recovery_hours": 72
 *   }
 * }
 * 
 * REQUIRED FIELDS:
 * - canonical_name: Unique identifier matching exercise canonical_name
 * - muscle_data: Object containing muscles array with activation patterns
 * - primary_mover: Main muscle responsible for the movement
 * - largest_muscle: Largest muscle involved in the exercise
 * - movement_archetype: Movement pattern (push, pull, squat, hinge, core, etc.)
 * - category: Exercise category (strength, cardio, flexibility, etc.)
 * - difficulty_level: Integer from 1-5 indicating exercise complexity
 * - recovery_hours: Hours needed between sessions (24, 48, 72, etc.)
 * 
 * MUSCLE DATA STRUCTURE:
 * Each muscle in the muscles array must include:
 * - name: Anatomical muscle name (e.g., "pectoralis_major")
 * - role: Muscle function ("primary_mover", "synergist", "stabilizer")
 * - contraction_type: Type of muscle contraction ("isotonic", "isometric")
 * 
 * EXERCISE MATCHING:
 * The command matches exercises using two methods in order of preference:
 * 1. Canonical name matching (preferred): Matches exercise.canonical_name field
 * 2. Title fallback: Matches exercise.title field if canonical name fails
 * 
 * By default, only global exercises (user_id = null) are updated.
 * Use --include-user-exercises to also update user-specific exercises.
 * 
 * EXERCISE TARGETING:
 * 
 * Default behavior (global exercises only):
 * - Matches exercises where user_id IS NULL
 * - Skips user-specific exercises
 * - Safer for system-wide intelligence updates
 * - Maintains separation between global and user data
 * 
 * With --include-user-exercises flag:
 * - Matches both global AND user-specific exercises
 * - Updates any exercise regardless of ownership
 * - Useful for comprehensive intelligence updates
 * - Required when updating user-created exercises
 * 
 * DRY RUN MODE:
 * Use --dry-run to preview what would happen without making changes:
 * - Shows which exercises would be matched
 * - Indicates whether intelligence would be CREATED or UPDATED
 * - Displays exercise IDs and types (global/user)
 * - Reports exercises that would be skipped
 * - No database modifications are made
 * 
 * INTELLIGENCE DATA MANAGEMENT:
 * 
 * Create vs Update behavior:
 * - CREATE: Exercise exists but has no intelligence data
 * - UPDATE: Exercise exists and already has intelligence data
 * - The command uses updateOrCreate() for seamless handling
 * 
 * Data validation:
 * - JSON structure is validated before processing
 * - Required fields are enforced by the database schema
 * - Invalid muscle_data will cause import to fail
 * - Canonical names must match existing exercises
 * 
 * ADMIN WORKFLOWS:
 * 
 * For system-wide intelligence updates:
 *   php artisan exercises:sync-intelligence --file=global_intelligence.json
 * 
 * For user-specific intelligence (personal trainers, custom exercises):
 *   php artisan exercises:sync-intelligence --file=user_intelligence.json --include-user-exercises
 * 
 * For testing new intelligence data:
 *   php artisan exercises:sync-intelligence --file=test_data.json --dry-run
 * 
 * For comprehensive updates (all exercises):
 *   php artisan exercises:sync-intelligence --file=complete_data.json --include-user-exercises
 * 
 * For automated scripts (CI/CD):
 *   php artisan exercises:sync-intelligence --file=production_data.json --no-interaction
 * 
 * CURRENT LIMITATIONS:
 * These limitations are intentional design decisions:
 * 
 * 1. EXACT MATCHING REQUIRED:
 *    Exercise matching requires exact canonical_name or title matches.
 *    Fuzzy matching is not supported to prevent accidental data corruption.
 *    If exercises don't match, they are skipped with a warning.
 * 
 * 2. NO EXERCISE CREATION:
 *    The command only updates existing exercises and does not create new ones.
 *    Exercises must exist in the database before intelligence can be added.
 *    This prevents accidental exercise creation from typos in intelligence data.
 * 
 * 3. COMPLETE REPLACEMENT:
 *    Intelligence data is completely replaced, not merged.
 *    Partial updates to specific fields are not supported.
 *    All required fields must be provided in the JSON data.
 * 
 * 4. SINGLE FILE PROCESSING:
 *    Only one JSON file can be processed per command execution.
 *    Batch processing multiple files requires separate command runs.
 * 
 * RECOMMENDATION ENGINE INTEGRATION:
 * Intelligence data is used by the recommendation engine for:
 * - Muscle group balancing in workout suggestions
 * - Recovery time calculations between sessions
 * - Exercise difficulty progression planning
 * - Movement pattern variety in program design
 * - Biomechanical analysis for form guidance
 */
class SyncExerciseIntelligence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:sync-intelligence {--file= : Custom JSON file path relative to database/imports/} {--dry-run : Preview changes without executing them} {--include-user-exercises : Allow updating user exercises in addition to global exercises}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes exercise intelligence data from JSON file to the database. Use --file option to specify custom file, --dry-run to preview changes, and --include-user-exercises to update user exercises.';

    /**
     * Execute the console command.
     * 
     * Main workflow:
     * 1. Parse command options and display mode information
     * 2. Determine JSON file path (custom or default)
     * 3. Validate file existence and JSON format
     * 4. Process each exercise in the JSON data
     * 5. Match exercises using canonical_name or title fallback
     * 6. Apply exercise targeting rules (global vs user exercises)
     * 7. Create or update intelligence data (or preview in dry-run mode)
     * 8. Display comprehensive results and statistics
     * 
     * @return int Command exit code (SUCCESS or FAILURE)
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $includeUserExercises = $this->option('include-user-exercises');
        
        // Display mode information for user clarity
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made to the database');
        }
        
        if ($includeUserExercises) {
            $this->info('Including user exercises in synchronization');
        }
        
        $this->info('Starting synchronization of exercise intelligence data...');

        // Determine JSON file path based on --file option
        $customFile = $this->option('file');
        if ($customFile) {
            $jsonPath = database_path('imports/' . $customFile);
            $this->info("Using custom file: {$customFile}");
        } else {
            $jsonPath = database_path('seeders/json/exercise_intelligence_data.json');
            $this->info('Using default intelligence file');
        }

        // Validate file existence
        if (!file_exists($jsonPath)) {
            $this->error('Exercise intelligence JSON file not found at: ' . $jsonPath);
            return Command::FAILURE;
        }

        // Parse and validate JSON data
        $jsonData = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error decoding JSON file: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        if (empty($jsonData)) {
            $this->warn('No exercise intelligence data found in the file');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($jsonData) . " exercises to process");

        // Process each exercise in the JSON data
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($jsonData as $exerciseKey => $data) {
            $result = $this->processExerciseIntelligence($exerciseKey, $data, $includeUserExercises, $dryRun);
            
            if ($result['status'] === 'processed') {
                $processed++;
                if ($result['action'] === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $skipped++;
            }
        }

        // Display comprehensive results
        $this->displayResults($dryRun, $processed, $created, $updated, $skipped);

        return Command::SUCCESS;
    }

    /**
     * Process intelligence data for a single exercise.
     * 
     * This method handles the core logic for matching exercises and applying intelligence data.
     * It supports both canonical_name and title-based matching with flexible targeting rules.
     * 
     * @param string $exerciseKey The key from JSON (used for title fallback)
     * @param array $data The intelligence data for this exercise
     * @param bool $includeUserExercises Whether to include user exercises in matching
     * @param bool $dryRun Whether to preview changes without executing them
     * @return array Result information with status, action, and details
     */
    private function processExerciseIntelligence(string $exerciseKey, array $data, bool $includeUserExercises, bool $dryRun): array
    {
        $exercise = $this->findExercise($exerciseKey, $data, $includeUserExercises);
        
        if (!$exercise) {
            $restrictionMessage = $includeUserExercises ? 'Exercise not found' : 'Exercise not found or not global';
            $this->warn("{$restrictionMessage}: {$exerciseKey}. Skipping.");
            return ['status' => 'skipped', 'reason' => 'not_found'];
        }

        $exerciseIdentifier = $data['canonical_name'] ?? $exerciseKey;
        $exerciseType = $exercise->user_id ? 'user' : 'global';
        
        if ($dryRun) {
            return $this->previewIntelligenceUpdate($exercise, $exerciseIdentifier, $exerciseType);
        } else {
            return $this->applyIntelligenceUpdate($exercise, $data, $exerciseIdentifier, $exerciseType);
        }
    }

    /**
     * Find an exercise using canonical_name or title matching with targeting rules.
     * 
     * Matching priority:
     * 1. canonical_name field (preferred for accuracy)
     * 2. title field (fallback for legacy data)
     * 
     * Targeting rules:
     * - Default: Only global exercises (user_id IS NULL)
     * - With --include-user-exercises: Both global and user exercises
     * 
     * @param string $exerciseKey The key from JSON (used for title fallback)
     * @param array $data The intelligence data containing canonical_name
     * @param bool $includeUserExercises Whether to include user exercises
     * @return Exercise|null The matched exercise or null if not found
     */
    private function findExercise(string $exerciseKey, array $data, bool $includeUserExercises): ?Exercise
    {
        $exercise = null;
        
        // First try to find by canonical_name (preferred method)
        if (isset($data['canonical_name'])) {
            $query = Exercise::where('canonical_name', $data['canonical_name']);
            
            // Apply targeting rules
            if (!$includeUserExercises) {
                $query->whereNull('user_id');
            }
            
            $exercise = $query->first();
        }
        
        // Fallback to title-based lookup if canonical name lookup fails
        if (!$exercise) {
            $query = Exercise::where('title', $exerciseKey);
            
            // Apply targeting rules
            if (!$includeUserExercises) {
                $query->whereNull('user_id');
            }
            
            $exercise = $query->first();
        }

        return $exercise;
    }

    /**
     * Preview what would happen when updating exercise intelligence (dry-run mode).
     * 
     * This method checks the current state and reports what action would be taken
     * without making any database changes. Useful for validating data before import.
     * 
     * @param Exercise $exercise The matched exercise
     * @param string $exerciseIdentifier Display name for the exercise
     * @param string $exerciseType Type of exercise (global or user)
     * @return array Result information with status and action
     */
    private function previewIntelligenceUpdate(Exercise $exercise, string $exerciseIdentifier, string $exerciseType): array
    {
        $existingIntelligence = ExerciseIntelligence::where('exercise_id', $exercise->id)->first();
        
        if ($existingIntelligence) {
            $this->comment("[DRY RUN] Would UPDATE intelligence for: {$exerciseIdentifier} (Exercise ID: {$exercise->id}, Type: {$exerciseType})");
            return ['status' => 'processed', 'action' => 'updated'];
        } else {
            $this->comment("[DRY RUN] Would CREATE intelligence for: {$exerciseIdentifier} (Exercise ID: {$exercise->id}, Type: {$exerciseType})");
            return ['status' => 'processed', 'action' => 'created'];
        }
    }

    /**
     * Apply intelligence data to an exercise (actual database update).
     * 
     * This method performs the actual database operation using updateOrCreate
     * to handle both new intelligence creation and existing data updates seamlessly.
     * 
     * @param Exercise $exercise The matched exercise
     * @param array $data The intelligence data to apply
     * @param string $exerciseIdentifier Display name for the exercise
     * @param string $exerciseType Type of exercise (global or user)
     * @return array Result information with status and action
     */
    private function applyIntelligenceUpdate(Exercise $exercise, array $data, string $exerciseIdentifier, string $exerciseType): array
    {
        $existingIntelligence = ExerciseIntelligence::where('exercise_id', $exercise->id)->first();
        $action = $existingIntelligence ? 'updated' : 'created';
        
        ExerciseIntelligence::updateOrCreate(
            ['exercise_id' => $exercise->id],
            $data
        );
        
        $this->comment("Synchronized intelligence for: {$exerciseIdentifier} (Type: {$exerciseType})");
        
        return ['status' => 'processed', 'action' => $action];
    }

    /**
     * Display comprehensive results and statistics.
     * 
     * Provides detailed feedback about the synchronization process including
     * counts of created, updated, and skipped exercises with appropriate
     * messaging for both dry-run and actual execution modes.
     * 
     * @param bool $dryRun Whether this was a dry-run execution
     * @param int $processed Total exercises processed successfully
     * @param int $created Number of new intelligence records created
     * @param int $updated Number of existing intelligence records updated
     * @param int $skipped Number of exercises skipped
     */
    private function displayResults(bool $dryRun, int $processed, int $created, int $updated, int $skipped): void
    {
        if ($dryRun) {
            $this->info('DRY RUN completed - No changes were made to the database.');
            $this->info("Summary of what would happen:");
            $this->info("  Exercises that would be processed: {$processed}");
            $this->info("  Intelligence that would be created: {$created}");
            $this->info("  Intelligence that would be updated: {$updated}");
            $this->info("  Exercises that would be skipped: {$skipped}");
        } else {
            $this->info('Exercise intelligence synchronization completed.');
            $this->info("Summary:");
            $this->info("  Exercises processed: {$processed}");
            $this->info("  Intelligence created: {$created}");
            $this->info("  Intelligence updated: {$updated}");
            $this->info("  Exercises skipped: {$skipped}");
        }
    }
}

/*
 * ADDITIONAL ADMIN EXAMPLES AND USE CASES:
 * 
 * 1. SYSTEM MAINTENANCE AND UPDATES:
 *    # Update global exercise intelligence with new research data
 *    php artisan exercises:sync-intelligence --file=updated_intelligence_2024.json
 *    # Preview system-wide intelligence updates before applying
 *    php artisan exercises:sync-intelligence --file=new_research_data.json --dry-run
 * 
 * 2. USER-SPECIFIC INTELLIGENCE MANAGEMENT:
 *    # Import intelligence for user-created exercises
 *    php artisan exercises:sync-intelligence --file=user_exercise_intelligence.json --include-user-exercises
 *    # Update intelligence for personal trainer's custom exercises
 *    php artisan exercises:sync-intelligence --file=trainer_intelligence.json --include-user-exercises
 * 
 * 3. DATA MIGRATION AND IMPORTS:
 *    # Import Stefan's exercise intelligence data
 *    php artisan exercises:sync-intelligence --file=stefan_intelligence.json
 *    # Migrate intelligence from external fitness database
 *    php artisan exercises:sync-intelligence --file=external_intelligence.json --include-user-exercises
 * 
 * 4. TESTING AND VALIDATION:
 *    # Test new intelligence data structure
 *    php artisan exercises:sync-intelligence --file=test_intelligence.json --dry-run
 *    # Validate user exercise intelligence before deployment
 *    php artisan exercises:sync-intelligence --file=user_data.json --include-user-exercises --dry-run
 * 
 * 5. BULK INTELLIGENCE UPDATES:
 *    # Update all exercise intelligence (global and user)
 *    php artisan exercises:sync-intelligence --file=comprehensive_intelligence.json --include-user-exercises
 *    # Update only global exercises (safer for production)
 *    php artisan exercises:sync-intelligence --file=global_intelligence.json
 * 
 * 6. RECOMMENDATION ENGINE PREPARATION:
 *    # Prepare intelligence data for recommendation engine updates
 *    php artisan exercises:sync-intelligence --file=recommendation_intelligence.json
 *    # Update muscle activation patterns for better recommendations
 *    php artisan exercises:sync-intelligence --file=muscle_patterns.json --dry-run
 * 
 * 7. RESEARCH DATA INTEGRATION:
 *    # Import biomechanical research findings
 *    php artisan exercises:sync-intelligence --file=research_2024.json
 *    # Update recovery time recommendations based on new studies
 *    php artisan exercises:sync-intelligence --file=recovery_updates.json --dry-run
 * 
 * 8. AUTOMATED WORKFLOWS (CI/CD):
 *    # Automated intelligence updates in deployment pipeline
 *    php artisan exercises:sync-intelligence --file=production_intelligence.json --no-interaction
 *    # Scheduled intelligence updates with logging
 *    php artisan exercises:sync-intelligence --file=scheduled_updates.json > intelligence_sync.log 2>&1
 * 
 * 9. DEVELOPMENT AND STAGING:
 *    # Sync development intelligence data
 *    php artisan exercises:sync-intelligence --file=dev_intelligence.json --include-user-exercises
 *    # Test staging environment with production-like data
 *    php artisan exercises:sync-intelligence --file=staging_intelligence.json --dry-run
 * 
 * 10. BACKUP AND RECOVERY:
 *     # Restore intelligence from backup
 *     php artisan exercises:sync-intelligence --file=backup_intelligence.json --include-user-exercises
 *     # Verify backup data before restoration
 *     php artisan exercises:sync-intelligence --file=backup_intelligence.json --dry-run
 * 
 * WORKING WITH DIFFERENT FILE LOCATIONS:
 * 
 * All custom files must be placed in database/imports/ directory:
 *   database/imports/stefan_intelligence.json
 *   database/imports/user_data.json
 *   database/imports/research_updates.json
 * 
 * Default file location (when --file is not specified):
 *   database/seeders/json/exercise_intelligence_data.json
 * 
 * EXERCISE MATCHING STRATEGIES:
 * 
 * For reliable matching, ensure JSON keys and canonical_name values match exactly:
 *   # Good - canonical_name matches database
 *   "bench_press": {"canonical_name": "bench_press", ...}
 *   
 *   # Fallback - uses JSON key for title matching
 *   "Bench Press": {"canonical_name": "bench_press", ...}
 * 
 * INTELLIGENCE DATA VALIDATION:
 * 
 * Required fields that must be present in JSON:
 *   - canonical_name (string): Must match existing exercise
 *   - muscle_data (object): Must contain muscles array
 *   - primary_mover (string): Main muscle for the movement
 *   - largest_muscle (string): Largest muscle involved
 *   - movement_archetype (string): Movement pattern classification
 *   - category (string): Exercise category
 *   - difficulty_level (integer): 1-5 complexity rating
 *   - recovery_hours (integer): Hours between sessions
 * 
 * MUSCLE DATA REQUIREMENTS:
 * 
 * Each muscle in muscle_data.muscles array needs:
 *   - name (string): Anatomical muscle name
 *   - role (string): "primary_mover", "synergist", or "stabilizer"
 *   - contraction_type (string): "isotonic" or "isometric"
 * 
 * Example muscle data structure:
 *   "muscle_data": {
 *     "muscles": [
 *       {
 *         "name": "pectoralis_major",
 *         "role": "primary_mover",
 *         "contraction_type": "isotonic"
 *       },
 *       {
 *         "name": "rectus_abdominis",
 *         "role": "stabilizer",
 *         "contraction_type": "isometric"
 *       }
 *     ]
 *   }
 * 
 * TROUBLESHOOTING:
 * 
 * Common issues and solutions:
 * 
 * 1. "Exercise not found" errors:
 *    - Verify canonical_name matches database exactly
 *    - Check if exercise exists in exercises table
 *    - Use --include-user-exercises if targeting user exercises
 *    - Ensure JSON key matches exercise title if canonical_name fails
 * 
 * 2. "File not found" errors:
 *    - Verify file exists in database/imports/ directory
 *    - Check file path and spelling
 *    - Ensure file has .json extension
 *    - Verify file permissions are readable
 * 
 * 3. "Invalid JSON format" errors:
 *    - Validate JSON syntax using online validators
 *    - Check for missing commas, brackets, or quotes
 *    - Ensure UTF-8 encoding without BOM
 *    - Verify all required fields are present
 * 
 * 4. "Integrity constraint violation" errors:
 *    - Ensure all required fields are provided
 *    - Check that muscle_data is properly structured
 *    - Verify foreign key relationships exist
 *    - Ensure data types match schema requirements
 * 
 * 5. No exercises processed:
 *    - Check if exercises exist in database
 *    - Verify canonical_name or title matching
 *    - Use --include-user-exercises if needed
 *    - Run with --dry-run to see matching details
 * 
 * PERFORMANCE CONSIDERATIONS:
 * 
 * For large intelligence datasets:
 *   - Process in smaller batches if memory issues occur
 *   - Use --dry-run first to validate data structure
 *   - Monitor database performance during large updates
 *   - Consider running during low-traffic periods
 * 
 * SECURITY CONSIDERATIONS:
 * 
 * When handling intelligence data:
 *   - Validate JSON files before processing
 *   - Use --dry-run to preview changes in production
 *   - Backup existing intelligence data before major updates
 *   - Restrict file access to authorized personnel only
 *   - Log intelligence updates for audit trails
 * 
 * INTEGRATION WITH RECOMMENDATION ENGINE:
 * 
 * Intelligence data directly impacts:
 *   - Exercise recommendations based on muscle groups
 *   - Recovery time calculations between workouts
 *   - Difficulty progression in training programs
 *   - Movement pattern balancing in routines
 *   - Biomechanical analysis for form guidance
 * 
 * After updating intelligence data, consider:
 *   - Clearing recommendation caches if implemented
 *   - Updating related training algorithms
 *   - Notifying users of improved recommendations
 *   - Testing recommendation quality with new data
 */
