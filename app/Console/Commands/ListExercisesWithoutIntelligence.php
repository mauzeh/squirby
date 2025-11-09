<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;

class ListExercisesWithoutIntelligence extends Command
{
    protected $signature = 'exercises:list-without-intelligence 
                            {--global : Only show global exercises}
                            {--user : Only show user exercises}
                            {--user-id= : Filter by specific user ID}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'List all exercises that do not have intelligence data';

    public function handle()
    {
        $this->info('Finding exercises without intelligence data...');

        // Build query for exercises without intelligence
        $query = Exercise::doesntHave('intelligence');

        // Apply filters based on options
        if ($this->option('global')) {
            $query->whereNull('user_id');
        } elseif ($this->option('user')) {
            $query->whereNotNull('user_id');
        }

        if ($userId = $this->option('user-id')) {
            $query->where('user_id', $userId);
        }

        $exercises = $query->with('user:id,name')->get();

        if ($exercises->isEmpty()) {
            $this->info('✓ All exercises have intelligence data!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$exercises->count()} exercises without intelligence data:");
        $this->newLine();

        // Output based on format
        $format = $this->option('format');
        
        match($format) {
            'json' => $this->outputJson($exercises),
            'csv' => $this->outputCsv($exercises),
            default => $this->outputTable($exercises),
        };

        return Command::SUCCESS;
    }

    private function outputTable($exercises): void
    {
        $data = $exercises->map(function ($exercise) {
            return [
                'ID' => $exercise->id,
                'Title' => $exercise->title,
                'Canonical Name' => $exercise->canonical_name ?? 'N/A',
                'Type' => $exercise->user_id ? 'User' : 'Global',
                'Owner' => $exercise->user ? $exercise->user->name : 'System',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Title', 'Canonical Name', 'Type', 'Owner'],
            $data
        );
    }

    private function outputJson($exercises): void
    {
        $data = $exercises->map(function ($exercise) {
            return [
                'id' => $exercise->id,
                'title' => $exercise->title,
                'canonical_name' => $exercise->canonical_name,
                'user_id' => $exercise->user_id,
                'owner_name' => $exercise->user?->name,
            ];
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function outputCsv($exercises): void
    {
        $this->line('ID,Title,Canonical Name,Type,Owner');
        
        foreach ($exercises as $exercise) {
            $this->line(sprintf(
                '%d,"%s","%s",%s,"%s"',
                $exercise->id,
                str_replace('"', '""', $exercise->title),
                str_replace('"', '""', $exercise->canonical_name ?? 'N/A'),
                $exercise->user_id ? 'User' : 'Global',
                str_replace('"', '""', $exercise->user?->name ?? 'System')
            ));
        }
    }
}
