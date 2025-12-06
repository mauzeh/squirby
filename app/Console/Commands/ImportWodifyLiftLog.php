<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;

/**
 * Import lift log data from Wodify export format (tab-delimited text file).
 * 
 * Wodify exports workout data in a tab-delimited format with columns:
 * Date | Component Name | Performance Result Type | Fully Formatted Result | Comment
 * 
 * Example:
 * 08/29/2025	Deadlift	Weight	1 x 4 @ 410 lbs	285, 315, 345, 375, 410
 * 08/27/2025	Bench Press	Weight	3 x 2 @ 220 lbs	200 (3 reps), 210 (3 reps), 220, 220, 220 (all 2 reps)
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Basic import:
 *    php artisan lift-log:import-wodify john_fernandes_raw.txt --user-email=john@example.com
 * 
 * 2. Import with overwrite:
 *    php artisan lift-log:import-wodify workout_export.txt --user-email=user@example.com --overwrite
 * 
 * 3. Automated import:
 *    php artisan lift-log:import-wodify data.txt --user-email=user@example.com --overwrite --create-exercises
 * 
 * 4. Preview import (dry run):
 *    php artisan lift-log:import-wodify data.txt --user-email=user@example.com --dry-run
 */
class ImportWodifyLiftLog extends Command
{
    protected $signature = 'lift-log:import-wodify {file} {--user-email=} {--overwrite : Overwrite existing lift logs} {--create-exercises : Automatically create exercises when not found} {--dry-run : Preview without making changes}';

    protected $description = 'Import lift log data from Wodify export format (tab-delimited)';

    public function handle()
    {
        $filePath = $this->argument('file');
        $userEmail = $this->option('user-email');

        // Validate file
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        // Get user
        if (!$userEmail) {
            $userEmail = $this->ask('Enter the user email to import workouts for');
        }

        $user = User::where('email', $userEmail)->first();
        if (!$user) {
            $this->error("User not found with email: {$userEmail}");
            return Command::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info("DRY RUN MODE - No changes will be made");
        }
        
        $this->info("Importing Wodify data for {$user->name} ({$user->email})");

        // Parse the file
        $workouts = $this->parseWodifyFile($filePath);
        
        if (empty($workouts)) {
            $this->warn('No workouts found in file');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($workouts) . " workout entries to import");

        // Import workouts
        $imported = 0;
        $skipped = 0;

        foreach ($workouts as $workout) {
            try {
                if ($isDryRun) {
                    $this->previewWorkoutImport($workout, $user);
                } else {
                    $this->importWorkout($workout, $user);
                }
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $this->warn("✗ Skipped: {$workout['exercise']} on {$workout['date']} - {$e->getMessage()}");
            }
        }

        $summaryTitle = $isDryRun ? "\nDry run completed:" : "\nImport completed:";
        $this->info($summaryTitle);
        $this->info("Workouts imported: {$imported}");
        $this->info("Workouts skipped: {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Parse Wodify tab-delimited file
     */
    private function parseWodifyFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $workouts = [];

        foreach ($lines as $index => $line) {
            // Skip header row and empty lines
            if ($index === 0 || trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);
            
            // Need at least 4 columns: Date, Component Name, Type, Result
            if (count($parts) < 4) {
                continue;
            }

            $date = trim($parts[0]);
            $exerciseName = trim($parts[1]);
            $type = trim($parts[2]);
            $result = trim($parts[3]);
            $comment = isset($parts[4]) ? trim($parts[4]) : null;

            // Only process "Weight" type entries
            if ($type !== 'Weight') {
                continue;
            }

            // Parse the result format: "3 x 5 @ 200 lbs"
            if (preg_match('/(\d+)\s*x\s*(\d+)\s*@\s*(\d+(?:\.\d+)?)\s*lbs?/i', $result, $matches)) {
                $sets = (int)$matches[1];
                $reps = (int)$matches[2];
                $weight = (float)$matches[3];

                $workouts[] = [
                    'date' => $date,
                    'exercise' => $exerciseName,
                    'sets' => $sets,
                    'reps' => $reps,
                    'weight' => $weight,
                    'comment' => $comment
                ];
            }
        }

        return $workouts;
    }

    /**
     * Preview workout import (dry-run mode)
     */
    private function previewWorkoutImport(array $workout, User $user): void
    {
        $canonicalName = $this->generateCanonicalName($workout['exercise']);
        $date = Carbon::parse($workout['date']);
        
        // Check if exercise exists
        $exercise = $this->findExercise($canonicalName, $user);
        
        if (!$exercise) {
            $this->line("⚠ Would create exercise: {$workout['exercise']} (canonical: {$canonicalName})");
        }
        
        $this->line("→ Would import: {$workout['exercise']} on {$date->format('Y-m-d')} - {$workout['weight']}lbs × {$workout['reps']} reps × {$workout['sets']} sets");
    }

    /**
     * Import a single workout
     */
    private function importWorkout(array $workout, User $user): void
    {
        $canonicalName = $this->generateCanonicalName($workout['exercise']);
        $date = Carbon::parse($workout['date']);
        
        // Find or create exercise
        $exercise = $this->findOrCreateExercise($workout['exercise'], $canonicalName, $user);
        
        // Check for duplicates if not overwriting
        if (!$this->option('overwrite')) {
            $existing = LiftLog::where('user_id', $user->id)
                ->where('exercise_id', $exercise->id)
                ->whereDate('logged_at', $date->format('Y-m-d'))
                ->whereHas('liftSets', function ($query) use ($workout) {
                    $query->where('weight', $workout['weight'])
                          ->where('reps', $workout['reps']);
                })
                ->first();
            
            if ($existing) {
                throw new \Exception('Duplicate entry exists (use --overwrite to replace)');
            }
        } else {
            // Delete existing entries for this exercise on this date with matching weight/reps
            $existing = LiftLog::where('user_id', $user->id)
                ->where('exercise_id', $exercise->id)
                ->whereDate('logged_at', $date->format('Y-m-d'))
                ->whereHas('liftSets', function ($query) use ($workout) {
                    $query->where('weight', $workout['weight'])
                          ->where('reps', $workout['reps']);
                })
                ->get();
            
            foreach ($existing as $log) {
                $log->liftSets()->delete();
                $log->delete();
            }
        }
        
        // Create lift log
        $liftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => $date,
            'comments' => $workout['comment']
        ]);

        // Create lift sets
        for ($i = 0; $i < $workout['sets']; $i++) {
            LiftSet::create([
                'lift_log_id' => $liftLog->id,
                'weight' => $workout['weight'],
                'reps' => $workout['reps']
            ]);
        }
        
        $this->line("✓ Imported: {$workout['exercise']} on {$date->format('Y-m-d')} - {$workout['weight']}lbs × {$workout['reps']} reps × {$workout['sets']} sets");
    }

    /**
     * Find exercise by canonical name
     */
    private function findExercise(string $canonicalName, User $user): ?Exercise
    {
        // Check global exercises
        $exercise = Exercise::global()
            ->where('canonical_name', $canonicalName)
            ->first();
        
        if ($exercise) {
            return $exercise;
        }
        
        // Check user exercises
        return Exercise::where('user_id', $user->id)
            ->where('canonical_name', $canonicalName)
            ->first();
    }

    /**
     * Find or create exercise
     */
    private function findOrCreateExercise(string $exerciseName, string $canonicalName, User $user): Exercise
    {
        $exercise = $this->findExercise($canonicalName, $user);
        
        if ($exercise) {
            return $exercise;
        }
        
        // Exercise not found
        if ($this->option('create-exercises')) {
            $this->line("⚠ Creating exercise: {$exerciseName}");
            return Exercise::create([
                'title' => $exerciseName,
                'canonical_name' => $canonicalName,
                'description' => "Imported from Wodify",
                'exercise_type' => 'regular',
                'user_id' => $user->id
            ]);
        }
        
        // Interactive mode
        $this->warn("Exercise '{$exerciseName}' not found");
        $choice = $this->choice(
            'What would you like to do?',
            ['Create new user exercise', 'Map to existing exercise', 'Skip this workout'],
            0
        );
        
        if ($choice === 'Skip this workout') {
            throw new \Exception('Skipped by user');
        }
        
        if ($choice === 'Create new user exercise') {
            return Exercise::create([
                'title' => $exerciseName,
                'canonical_name' => $canonicalName,
                'description' => "Imported from Wodify",
                'exercise_type' => 'regular',
                'user_id' => $user->id
            ]);
        }
        
        // Map to existing
        $existingCanonicalName = $this->ask('Enter canonical name of existing exercise');
        $existing = $this->findExercise($existingCanonicalName, $user);
        
        if (!$existing) {
            throw new \Exception("Exercise '{$existingCanonicalName}' not found");
        }
        
        return $existing;
    }

    /**
     * Generate canonical name from exercise title
     */
    private function generateCanonicalName(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($title)));
    }
}
