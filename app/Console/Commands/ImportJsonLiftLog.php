<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;

class ImportJsonLiftLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lift-log:import-json {file} {--user-email=} {--date=} {--overwrite : Overwrite existing lift logs for the same date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import lift log data from a JSON file';

    /**
     * Execute the console command.
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

        // Exercise not found in global exercises, prompt user
        $this->warn("Exercise '{$exerciseData['exercise']}' (canonical: {$canonicalName}) not found in global exercises.");
        
        $choice = $this->choice(
            'What would you like to do?',
            ['Create new global exercise', 'Map to existing exercise'],
            0
        );

        if ($choice === 'Create new global exercise') {
            return $this->createNewGlobalExercise($exerciseData);
        } else {
            return $this->mapToExistingExercise($exerciseData);
        }
    }

    /**
     * Create a new global exercise
     */
    private function createNewGlobalExercise(array $exerciseData): Exercise
    {
        return Exercise::create([
            'title' => $exerciseData['exercise'],
            'canonical_name' => $exerciseData['canonical_name'],
            'description' => "Imported from JSON file",
            'is_bodyweight' => $exerciseData['is_bodyweight'] ?? false,
            'user_id' => null // Global exercise
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
     * Find duplicate lift logs for the given exercises and date
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
     * Delete existing lift logs that match the duplicates
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