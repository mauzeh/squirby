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
        $exercise = $this->findOrCreateExercise($exerciseData['exercise'], $user);

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
    private function findOrCreateExercise(string $exerciseName, User $user): Exercise
    {
        // Normalize exercise name for matching
        $normalizedName = $this->normalizeExerciseName($exerciseName);

        // Try to find existing exercise (global or user-specific)
        $exercise = Exercise::availableToUser($user->id)
            ->where(function ($query) use ($normalizedName, $exerciseName) {
                $query->whereRaw('LOWER(title) = ?', [strtolower($exerciseName)])
                      ->orWhereRaw('LOWER(title) = ?', [strtolower($normalizedName)])
                      ->orWhere('canonical_name', str_replace(' ', '_', strtolower($normalizedName)));
            })
            ->first();

        if ($exercise) {
            return $exercise;
        }

        // Create new exercise for the user
        return Exercise::create([
            'title' => $normalizedName,
            'description' => "Imported from Stefan's workout log",
            'is_bodyweight' => $this->isBodyweightExercise($normalizedName),
            'user_id' => $user->id
        ]);
    }

    /**
     * Normalize exercise names for better matching
     */
    private function normalizeExerciseName(string $name): string
    {
        // Common abbreviations and normalizations
        $replacements = [
            'db' => 'dumbbell',
            'kb' => 'kettlebell',
            'bb' => 'barbell',
            'tricep' => 'triceps',
            'bicep' => 'biceps',
        ];

        $normalized = strtolower($name);
        
        foreach ($replacements as $abbrev => $full) {
            $normalized = str_replace($abbrev, $full, $normalized);
        }

        // Clean up spacing and capitalize properly
        $normalized = ucwords(trim($normalized));
        
        return $normalized;
    }

    /**
     * Determine if an exercise is bodyweight based on name
     */
    private function isBodyweightExercise(string $name): bool
    {
        $bodyweightKeywords = [
            'plank',
            'push up',
            'pushup',
            'pull up',
            'pullup',
            'chin up',
            'chinup',
            'dip',
            'lunge',
            'squat' // Can be bodyweight or weighted
        ];

        $lowerName = strtolower($name);
        
        foreach ($bodyweightKeywords as $keyword) {
            if (strpos($lowerName, $keyword) !== false) {
                // Special case: if it mentions weight/dumbbell/barbell, it's not bodyweight
                if (strpos($lowerName, 'dumbbell') !== false || 
                    strpos($lowerName, 'barbell') !== false ||
                    strpos($lowerName, 'weight') !== false) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }
}