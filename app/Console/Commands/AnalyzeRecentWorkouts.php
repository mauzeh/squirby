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
    protected $signature = 'workouts:analyze-recent {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyzes the lift-log data from the last 14 days using Gemini AI.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $this->info("Analyzing recent workouts for {$user->name}...");

        $liftLogs = LiftLog::with('exercise')->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(14))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($liftLogs->isEmpty()) {
            $this->info('No lift logs found in the last 14 days.');
            return 0;
        }

        $prompt = $this->buildPrompt($liftLogs);
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

    private function buildPrompt($liftLogs)
    {
        $prompt = "Analyze the following workout data for an athlete. The data is from the last 14 days. The most recent workout should be analyzed in the context of the workouts that came before it. If the same exercise is performed multiple times, analyze the athlete's performance in the most recent session against their historical performance for that exercise.\n\n";

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
