<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;

class SyncExerciseIntelligence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-exercise-intelligence';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes exercise intelligence data from JSON file to the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting synchronization of exercise intelligence data...');

        $jsonPath = database_path('seeders/json/exercise_intelligence_data.json');

        if (!file_exists($jsonPath)) {
            $this->error('Exercise intelligence JSON file not found at: ' . $jsonPath);
            return Command::FAILURE;
        }

        $jsonData = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error decoding JSON file: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        foreach ($jsonData as $exerciseTitle => $data) {
            $exercise = Exercise::where('title', $exerciseTitle)
                ->whereNull('user_id') // Ensure it's a global exercise
                ->first();

            if ($exercise) {
                ExerciseIntelligence::updateOrCreate(
                    ['exercise_id' => $exercise->id],
                    $data
                );
                $this->comment("Synchronized intelligence for: {$exerciseTitle}");
            } else {
                $this->warn("Exercise not found or not global: {$exerciseTitle}. Skipping.");
            }
        }

        $this->info('Exercise intelligence synchronization completed.');
        return Command::SUCCESS;
    }
}
