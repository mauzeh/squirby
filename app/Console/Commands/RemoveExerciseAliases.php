<?php

namespace App\Console\Commands;

use App\Models\ExerciseAlias;
use Illuminate\Console\Command;

class RemoveExerciseAliases extends Command
{
    protected $signature = 'aliases:remove {--user= : Filter by user ID}';

    protected $description = 'Interactively remove exercise aliases';

    public function handle()
    {
        $userId = $this->option('user');

        // Build query
        $query = ExerciseAlias::with(['user', 'exercise']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $aliases = $query->orderBy('user_id')->orderBy('exercise_id')->get();

        if ($aliases->isEmpty()) {
            $this->error('No aliases found.');
            return Command::FAILURE;
        }

        // Display aliases
        $this->info('Exercise Aliases:');
        $this->newLine();

        $headers = ['ID', 'User ID', 'User Email', 'Exercise ID', 'Exercise Title', 'Alias Name', 'Created At'];
        $rows = [];

        foreach ($aliases as $alias) {
            $rows[] = [
                $alias->id,
                $alias->user_id,
                $alias->user->email ?? 'N/A',
                $alias->exercise_id,
                $alias->exercise->title ?? 'N/A',
                $alias->alias_name,
                $alias->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Total aliases: ' . $aliases->count());
        $this->newLine();

        // Ask for IDs to remove
        $idsToRemove = $this->ask('Enter the alias IDs to remove (comma-separated, or "all" to remove all)');

        if (empty($idsToRemove)) {
            $this->info('No aliases removed.');
            return Command::SUCCESS;
        }

        // Parse IDs
        if (strtolower(trim($idsToRemove)) === 'all') {
            $aliasesToRemove = $aliases;
        } else {
            $ids = array_map('trim', explode(',', $idsToRemove));
            $ids = array_filter($ids, 'is_numeric');
            $aliasesToRemove = $aliases->whereIn('id', $ids);
        }

        if ($aliasesToRemove->isEmpty()) {
            $this->error('No valid alias IDs provided.');
            return Command::FAILURE;
        }

        // Confirm deletion
        $this->newLine();
        $this->warn('You are about to remove ' . $aliasesToRemove->count() . ' alias(es):');
        
        foreach ($aliasesToRemove as $alias) {
            $this->line('  - ID ' . $alias->id . ': ' . $alias->alias_name . ' (User: ' . ($alias->user->email ?? 'N/A') . ', Exercise: ' . ($alias->exercise->title ?? 'N/A') . ')');
        }

        $this->newLine();
        
        if (!$this->confirm('Are you sure you want to remove these aliases?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Remove aliases
        $removedCount = 0;
        foreach ($aliasesToRemove as $alias) {
            $alias->delete();
            $removedCount++;
        }

        $this->newLine();
        $this->info("Successfully removed {$removedCount} alias(es).");

        return Command::SUCCESS;
    }
}
