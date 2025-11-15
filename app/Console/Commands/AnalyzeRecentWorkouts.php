<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class AnalyzeRecentWorkouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workouts:analyze-recent {user_id?} {--days=14 : Number of days to look back for workout data (max 365)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyzes the lift-log data from a configurable lookback window using Gemini AI.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $lookbackDays = (int) $this->option('days');

        if ($lookbackDays < 1 || $lookbackDays > 365) {
            $this->error('The --days option must be an integer between 1 and 365.');
            return 1;
        }

        if (!$userId) {
            $users = User::all();
            $this->info('Please select a user to analyze:');
            $users->each(function ($user) {
                $this->line("  ID: {$user->id} - {$user->name}");
            });

            $userId = $this->ask('Enter the user ID');
        }

        $user = User::find($userId);

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $this->info("Analyzing recent workouts for {$user->name} for the last {$lookbackDays} days...");

        $liftLogs = LiftLog::with('exercise')->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays($lookbackDays))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($liftLogs->isEmpty()) {
            $this->info("No lift logs found in the last {$lookbackDays} days.");
            return 0;
        }

        $prompt = $this->buildPrompt($liftLogs, $lookbackDays);
        $this->info("Workout data sent to Gemini:\n" . $prompt);
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            $this->error('GEMINI_API_KEY not set in .env file.');
            return 1;
        }

        $response = $this->callGemini($prompt, $apiKey);

        if ($response) {
            $this->line($response);
        } else {
            $this->error('Failed to get analysis from Gemini AI.');
        }

        return 0;
    }

    private function buildPrompt($liftLogs, int $lookbackDays)
    {
        $prompt = "Analyze the following workout data for an athlete. The data is from the last {$lookbackDays} days. The most recent workout should be analyzed against the backdrop of the workouts before that. If the same exercise is performed then it should analyze how well the athlete did last time against the rest of the exercise's history for that athlete.\n\n";

        $workouts = $liftLogs->groupBy(function ($log) {
            return $log->created_at->format('Y-m-d H:i:s');
        });

        foreach ($workouts as $date => $logs) {
            $prompt .= "Workout on " . Carbon::parse($date)->format('F jS, Y') . ":\n";
            foreach ($logs as $log) {
                $prompt .= "- {$log->exercise->title}: {$log->display_rounds} sets of {$log->display_reps} reps at {$log->display_weight} lbs";
                if (!empty($log->comments)) {
                    $prompt .= " (Comments: {$log->comments})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }

        return $prompt;
    }

    private function callGemini(string $prompt, string $apiKey): ?string
    {
        $model = 'gemini-2.0-flash-001';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $this->info("Gemini API URL: {$url}");
        $this->info("Gemini API Request Body: " . json_encode([
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ]));

        $response = Http::post($url, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ]);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }

        $this->error('Gemini API call failed:');
        $this->error('Status: ' . $response->status());
        $this->error('Headers: ' . json_encode($response->headers()));
        $this->error('Body: ' . $response->body());
        return null;
    }
}
