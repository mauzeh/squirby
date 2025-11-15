<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

use App\Presenters\LiftLogTablePresenter;

class AnalyzeRecentWorkouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workouts:analyze-recent {user_id?} {--days=14 : Number of days to look back for workout data (max 365)} {--dry-run : Run the command without actually calling the Gemini API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyzes the lift-log data from a configurable lookback window using Gemini AI.';

    /**
     * The presenter for formatting lift log data.
     *
     * @var LiftLogTablePresenter
     */
    private $presenter;

    /**
     * Create a new command instance.
     *
     * @param LiftLogTablePresenter $presenter
     */
    public function __construct(LiftLogTablePresenter $presenter)
    {
        parent::__construct();
        $this->presenter = $presenter;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $lookbackDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

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

        if ($dryRun) {
            $this->info('Dry run mode enabled. Skipping Gemini API call.');
            return 0;
        }

        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            $this->error('GEMINI_API_KEY not set in .env file.');
            return 1;
        }

        $conversation = [['role' => 'user', 'parts' => [['text' => $prompt]]]];
        $response = $this->callGemini($conversation, $apiKey);

        if ($response) {
            $this->line($response);
            $conversation[] = ['role' => 'model', 'parts' => [['text' => $response]]];
        } else {
            $this->error('Failed to get analysis from Gemini AI.');
            return 1;
        }

        while (true) {
            $question = $this->ask('Ask a follow-up question (or type "exit" to quit)');

            if (in_array(strtolower($question), ['exit', 'quit'])) {
                break;
            }

            $conversation[] = ['role' => 'user', 'parts' => [['text' => $question]]];
            $response = $this->callGemini($conversation, $apiKey);

            if ($response) {
                $this->line($response);
                $conversation[] = ['role' => 'model', 'parts' => [['text' => $response]]];
            } else {
                $this->error('Failed to get analysis from Gemini AI.');
            }
        }

        return 0;
    }

    private function buildPrompt($liftLogs, int $lookbackDays)
    {
        $prompt = "Analyze the following workout data for an athlete. The data is from the last {$lookbackDays} days. The most recent workout should be analyzed against the backdrop of the workouts before that. If the same exercise is performed then it should analyze how well the athlete did last time against the rest of the exercise's history for that athlete.\n\n";

        $formattedLiftLogs = $this->presenter->formatForTable($liftLogs)['liftLogs'];

        $exercises = $formattedLiftLogs->groupBy('exercise_title');

        foreach ($exercises as $exerciseTitle => $logs) {
            $prompt .= "Exercise: {$exerciseTitle}\n";
            foreach ($logs as $log) {
                $date = Carbon::parse($log['raw_lift_log']->created_at)->format('F jS, Y');
                $prompt .= "- On {$date}: {$log['formatted_reps_sets']} at {$log['formatted_weight']}";
                if (!empty($log['full_comments'])) {
                    $prompt .= " (Comments: {$log['full_comments']})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }

        return $prompt;
    }

    private function callGemini(array $conversation, string $apiKey): ?string
    {
        $model = 'gemini-2.0-flash-001';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $this->info("Gemini API URL: {$url}");

        $response = Http::post($url, [
            'contents' => $conversation
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
