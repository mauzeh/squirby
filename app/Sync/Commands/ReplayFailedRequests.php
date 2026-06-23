<?php

namespace App\Sync\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\PersonalAccessToken;

class ReplayFailedRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:replay-failed 
                            {--days=1 : Number of days back to read logs} 
                            {--endpoint= : Filter by endpoint substring} 
                            {--user= : Filter by user ID or username} 
                            {--dry-run : Preview matching requests without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replay logged sync requests that failed or match filters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $endpointFilter = $this->option('endpoint');
        $userFilter = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        $dir = storage_path('logs/sync');
        if (!File::isDirectory($dir)) {
            $this->error("Sync logs directory does not exist: {$dir}");
            return Command::FAILURE;
        }

        $files = File::files($dir);
        $logFiles = [];
        $cutoffDate = now()->subDays($days)->startOfDay();

        foreach ($files as $file) {
            if ($file->getExtension() === 'log') {
                if (now()->setTimestamp($file->getMTime())->gte($cutoffDate)) {
                    $logFiles[] = $file;
                }
            }
        }

        if (empty($logFiles)) {
            $this->info("No log files found from the last {$days} days.");
            return Command::SUCCESS;
        }

        $matchingEntries = [];

        foreach ($logFiles as $logFile) {
            $content = File::get($logFile->getPathname());
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $jsonStart = strpos($line, '{');
                if ($jsonStart === false) {
                    continue;
                }

                $jsonStr = substr($line, $jsonStart);
                $entry = json_decode($jsonStr, true);

                if (!$entry || empty($entry['ts'])) {
                    continue;
                }

                $entryTime = \Carbon\Carbon::parse($entry['ts']);
                if ($entryTime->lt($cutoffDate)) {
                    continue;
                }

                if ($endpointFilter && strpos($entry['path'], $endpointFilter) === false) {
                    continue;
                }

                if ($userFilter) {
                    $userMatches = $this->entryMatchesUser($entry, $userFilter);
                    if (!$userMatches) {
                        continue;
                    }
                }

                $matchingEntries[] = $entry;
            }
        }

        if (empty($matchingEntries)) {
            $this->info("No matching logged requests found.");
            return Command::SUCCESS;
        }

        $this->info("Found " . count($matchingEntries) . " matching requests.");

        if ($dryRun) {
            $this->info("Dry run enabled. Previewing requests:");
            foreach ($matchingEntries as $index => $entry) {
                $userDesc = $this->getUserDescription($entry);
                $this->line(sprintf(
                    "[%d] %s | %s %s | User: %s",
                    $index + 1,
                    $entry['ts'],
                    $entry['method'],
                    $entry['path'],
                    $userDesc
                ));
            }
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($matchingEntries as $index => $entry) {
            $userDesc = $this->getUserDescription($entry);
            $this->line(sprintf(
                "Replaying [%d/%d]: %s %s (User: %s)...",
                $index + 1,
                count($matchingEntries),
                $entry['method'],
                $entry['path'],
                $userDesc
            ));

            $serverVars = $this->transformHeadersToServerVars($entry['headers'] ?? []);
            
            $request = Request::create(
                $entry['path'],
                $entry['method'],
                $entry['body'] ?? [],
                [],
                [],
                $serverVars,
                $entry['body'] ? json_encode($entry['body']) : null
            );

            $deviceId = $request->header('X-Device-Id');
            if ($deviceId) {
                $request->attributes->set('device_id', $deviceId);
            }

            try {
                $response = app()->handle($request);
                $statusCode = $response->getStatusCode();
                $this->line("  Response code: {$statusCode}");

                if ($statusCode >= 200 && $statusCode < 300) {
                    $this->info("  Success!");
                    $successCount++;
                } else {
                    $this->error("  Failed with response: " . $response->getContent());
                    $failedCount++;
                }
            } catch (\Throwable $e) {
                $this->error("  Exception: " . $e->getMessage());
                $failedCount++;
            }
        }

        $this->info("Replay completed: {$successCount} succeeded, {$failedCount} failed.");

        return Command::SUCCESS;
    }

    /**
     * Determine if a logged request matches a user ID or username.
     */
    private function entryMatchesUser(array $entry, string $userFilter): bool
    {
        if (isset($entry['body']['username']) && strcasecmp($entry['body']['username'], $userFilter) === 0) {
            return true;
        }

        $authHeader = $entry['headers']['authorization'][0] ?? '';
        if (preg_match('/Bearer\s+(.*)/i', $authHeader, $matches)) {
            $token = $matches[1];
            $accessToken = PersonalAccessToken::findToken($token);
            $user = $accessToken?->tokenable;

            if ($user) {
                return $user->id == $userFilter || strcasecmp($user->name, $userFilter) === 0;
            }
        }

        return false;
    }

    /**
     * Get a human-readable user description for logged request.
     */
    private function getUserDescription(array $entry): string
    {
        if (isset($entry['body']['username'])) {
            return $entry['body']['username'] . ' (from body)';
        }

        $authHeader = $entry['headers']['authorization'][0] ?? '';
        if (preg_match('/Bearer\s+(.*)/i', $authHeader, $matches)) {
            $token = $matches[1];
            $accessToken = PersonalAccessToken::findToken($token);
            $user = $accessToken?->tokenable;

            if ($user) {
                return sprintf("%s (ID: %d)", $user->name, $user->id);
            }
        }

        return 'Guest';
    }

    /**
     * Map request headers to PHP $_SERVER keys.
     */
    private function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $values) {
            $name = str_replace('-', '_', strtoupper($key));
            if ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
                $server[$name] = $values[0];
            } else {
                $server['HTTP_' . $name] = $values[0];
            }
        }
        return $server;
    }
}
