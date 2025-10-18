<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use Illuminate\Support\Str;

class GenerateCanonicalNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:generate-canonical-names {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and persist canonical names for exercises that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        $this->info('Finding exercises without canonical names...');

        $exercisesWithoutCanonicalNames = Exercise::whereNull('canonical_name')
            ->orWhere('canonical_name', '')
            ->get();

        if ($exercisesWithoutCanonicalNames->isEmpty()) {
            $this->info('All exercises already have canonical names.');
            return Command::SUCCESS;
        }

        $this->info("Found {$exercisesWithoutCanonicalNames->count()} exercises without canonical names.");

        $updated = 0;
        $skipped = 0;

        foreach ($exercisesWithoutCanonicalNames as $exercise) {
            $canonicalName = $this->generateCanonicalName($exercise->title);
            
            // Check if this canonical name already exists
            $existingExercise = Exercise::where('canonical_name', $canonicalName)
                ->where('id', '!=', $exercise->id)
                ->first();

            if ($existingExercise) {
                $this->warn("Skipping '{$exercise->title}' - canonical name '{$canonicalName}' already exists for exercise ID {$existingExercise->id}");
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("Would update: '{$exercise->title}' -> '{$canonicalName}'");
            } else {
                $exercise->canonical_name = $canonicalName;
                $exercise->save();
                $this->comment("Updated: '{$exercise->title}' -> '{$canonicalName}'");
            }
            
            $updated++;
        }

        if ($isDryRun) {
            $this->info("Dry run completed. Would update {$updated} exercises, {$skipped} skipped due to conflicts.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info("Successfully updated {$updated} exercises with canonical names.");
            if ($skipped > 0) {
                $this->warn("{$skipped} exercises were skipped due to canonical name conflicts.");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Generate a canonical name from an exercise title
     */
    private function generateCanonicalName(string $title): string
    {
        return Str::slug($title, '_');
    }
}