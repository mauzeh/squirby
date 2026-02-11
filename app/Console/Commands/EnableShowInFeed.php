<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;

class EnableShowInFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:enable-show-in-feed 
                            {--all : Enable for all exercises}
                            {--global : Enable for global exercises only}
                            {--user= : Enable for exercises logged by specific user}
                            {--ids= : Comma-separated list of exercise IDs}
                            {--no-interactive : Skip interactive selection and apply directly}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk enable show_in_feed flag for exercises';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $all = $this->option('all');
        $global = $this->option('global');
        $userId = $this->option('user');
        $ids = $this->option('ids');
        $noInteractive = $this->option('no-interactive');

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // Build query based on options
        $query = Exercise::where('show_in_feed', false);

        // If IDs are specified, use them directly (no interactive mode)
        if ($ids) {
            $exerciseIds = array_map('trim', explode(',', $ids));
            $query->whereIn('id', $exerciseIds);
            $scope = 'specified exercise IDs';
            $noInteractive = true; // Force non-interactive when IDs are provided
        } elseif ($global) {
            $query->global();
            $scope = 'global exercises';
        } elseif ($userId) {
            $query->whereHas('liftLogs', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
            $scope = "exercises logged by user ID {$userId}";
        } elseif ($all) {
            $scope = 'all exercises';
        } else {
            $this->error('Please specify one of: --all, --global, --user=ID, or --ids=1,2,3');
            return Command::FAILURE;
        }

        // Interactive mode is default unless --no-interactive or --ids is specified
        if (!$noInteractive && !$ids) {
            return $this->handleInteractive($isDryRun, $userId, $global, $all);
        }

        $exercises = $query
            ->withMax('liftLogs', 'logged_at')
            ->get()
            ->sortBy('lift_logs_max_logged_at');

        if ($exercises->isEmpty()) {
            $this->info("No exercises found with show_in_feed disabled for {$scope}.");
            return Command::SUCCESS;
        }

        $this->info("Found {$exercises->count()} exercises to update ({$scope}).");
        $this->newLine();

        // Always show which exercises will be updated
        $this->table(
            ['ID', 'Title', 'Type', 'User ID', 'Last Logged'],
            $exercises->map(fn($e) => [
                $e->id,
                $e->title,
                $e->exercise_type ?? 'regular',
                $e->user_id ?? 'global',
                $e->lift_logs_max_logged_at 
                    ? \Carbon\Carbon::parse($e->lift_logs_max_logged_at)->format('Y-m-d H:i')
                    : 'Never'
            ])
        );

        if ($isDryRun) {
            $this->newLine();
            $this->info("Dry run completed. Would enable show_in_feed for {$exercises->count()} exercises.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            if (!$this->confirm('Do you want to enable show_in_feed for these exercises?', true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            foreach ($exercises as $exercise) {
                $exercise->show_in_feed = true;
                $exercise->save();
            }

            $this->newLine();
            $this->info("Successfully enabled show_in_feed for {$exercises->count()} exercises:");
            foreach ($exercises as $exercise) {
                $this->comment("  ✓ [{$exercise->id}] {$exercise->title}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Handle interactive mode where user enters exercise IDs
     */
    protected function handleInteractive(bool $isDryRun, ?string $userId = null, bool $global = false, bool $all = false): int
    {
        $this->info('Interactive mode: Select exercises to enable show_in_feed.');
        $this->newLine();

        // Show all exercises with show_in_feed disabled
        $query = Exercise::where('show_in_feed', false);
        
        if ($global) {
            $query->global();
            $scope = 'global exercises';
        } elseif ($userId) {
            $query->whereHas('liftLogs', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
            $scope = "exercises logged by user ID {$userId}";
        } elseif ($all) {
            $scope = 'all exercises';
        } else {
            $scope = 'all exercises';
        }

        // Add last logged date and order by it
        $availableExercises = $query
            ->withMax('liftLogs', 'logged_at')
            ->get()
            ->sortBy('lift_logs_max_logged_at');

        if ($availableExercises->isEmpty()) {
            $this->info("No {$scope} found with show_in_feed disabled.");
            return Command::SUCCESS;
        }

        $this->info("Available {$scope} with show_in_feed disabled ({$availableExercises->count()} total):");
        $this->table(
            ['ID', 'Title', 'Type', 'User ID', 'Last Logged'],
            $availableExercises->map(fn($e) => [
                $e->id,
                $e->title,
                $e->exercise_type ?? 'regular',
                $e->user_id ?? 'global',
                $e->lift_logs_max_logged_at 
                    ? \Carbon\Carbon::parse($e->lift_logs_max_logged_at)->format('Y-m-d H:i')
                    : 'Never'
            ])
        );

        $this->newLine();
        $input = $this->ask('Enter exercise IDs to enable (comma-separated)');

        if (empty($input)) {
            $this->error('No input provided.');
            return Command::FAILURE;
        }

        $exerciseIds = array_map('trim', explode(',', $input));
        
        $selectedQuery = Exercise::where('show_in_feed', false)
            ->whereIn('id', $exerciseIds);

        $exercises = $selectedQuery->get();

        if ($exercises->isEmpty()) {
            $this->warn("No exercises found with the provided IDs that have show_in_feed disabled.");
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Found {$exercises->count()} exercises:");
        $this->table(
            ['ID', 'Title', 'Type', 'User ID'],
            $exercises->map(fn($e) => [
                $e->id,
                $e->title,
                $e->exercise_type ?? 'regular',
                $e->user_id ?? 'global'
            ])
        );

        if ($isDryRun) {
            $this->newLine();
            $this->info("Dry run completed. Would enable show_in_feed for {$exercises->count()} exercises.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            if (!$this->confirm('Do you want to enable show_in_feed for these exercises?', true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            foreach ($exercises as $exercise) {
                $exercise->show_in_feed = true;
                $exercise->save();
            }

            $this->newLine();
            $this->info("Successfully enabled show_in_feed for {$exercises->count()} exercises:");
            foreach ($exercises as $exercise) {
                $this->comment("  ✓ [{$exercise->id}] {$exercise->title}");
            }
        }

        return Command::SUCCESS;
    }
}
