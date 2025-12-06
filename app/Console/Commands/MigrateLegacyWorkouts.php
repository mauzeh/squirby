<?php

namespace App\Console\Commands;

use App\Models\Workout;
use Illuminate\Console\Command;

class MigrateLegacyWorkouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workouts:migrate-legacy
                          {--dry-run : Show what would be migrated without making changes}
                          {--user= : Only migrate workouts for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy workouts by generating WOD syntax from linked exercises';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');

        // Find legacy workouts (have exercises but no WOD syntax)
        $query = Workout::whereNull('wod_syntax')
            ->orWhere('wod_syntax', '')
            ->has('exercises');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $legacyWorkouts = $query->with('exercises.exercise')->get();

        if ($legacyWorkouts->isEmpty()) {
            $this->info('No legacy workouts found.');
            return 0;
        }

        $this->info("Found {$legacyWorkouts->count()} legacy workout(s) to migrate.");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->newLine();

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($legacyWorkouts as $workout) {
            if ($workout->exercises->isEmpty()) {
                $this->line("Skipping workout #{$workout->id} '{$workout->name}' - no exercises");
                $skippedCount++;
                continue;
            }

            // Generate flat bullet list of exercises
            $exerciseLines = [];
            foreach ($workout->exercises as $workoutExercise) {
                if ($workoutExercise->exercise) {
                    $exerciseName = $workoutExercise->exercise->title;
                    $scheme = $workoutExercise->scheme ?? '';
                    
                    if ($scheme) {
                        $exerciseLines[] = "- [[{$exerciseName}]]: {$scheme}";
                    } else {
                        $exerciseLines[] = "- [[{$exerciseName}]]";
                    }
                }
            }

            $wodSyntax = implode("\n", $exerciseLines);

            $this->line("Workout #{$workout->id} '{$workout->name}' (User: {$workout->user_id})");
            $this->line("  Exercises: {$workout->exercises->count()}");
            
            if ($this->output->isVerbose()) {
                $this->line("  Generated syntax:");
                foreach ($exerciseLines as $line) {
                    $this->line("    {$line}");
                }
            }

            if (!$dryRun) {
                $workout->wod_syntax = $wodSyntax;
                $workout->save();
                $this->info("  ✓ Migrated");
            } else {
                $this->comment("  → Would migrate");
            }

            $migratedCount++;
            $this->newLine();
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Migrated: {$migratedCount}");
        
        if ($skippedCount > 0) {
            $this->info("  Skipped: {$skippedCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return 0;
    }
}
