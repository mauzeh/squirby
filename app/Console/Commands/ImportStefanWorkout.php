<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;

class ImportStefanWorkout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workout:import-stefan {file} {--user-email=} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Stefan\'s workout data from a JSON file';

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

        $this->info("Importing workout data for {$user->name} ({$user->email})");
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

        // Import each exercise
        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $exerciseData) {
            try {
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
            'comments' => 'Imported from Stefan\'s workout log'
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
        
        // Try to find existing exercise by canonical name
        $exercise = Exercise::availableToUser($user->id)
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($exercise) {
            return $exercise;
        }

        // Create new exercise for the user
        return Exercise::create([
            'title' => $exerciseData['exercise'],
            'canonical_name' => $canonicalName,
            'description' => "Imported from Stefan's workout log",
            'is_bodyweight' => $exerciseData['is_bodyweight'] ?? false,
            'user_id' => $user->id
        ]);
    }
}