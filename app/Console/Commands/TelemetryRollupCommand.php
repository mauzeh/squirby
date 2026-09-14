<?php

namespace App\Console\Commands;

use App\Telemetry\Services\TelemetryReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelemetryRollupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:rollup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and cache telemetry Overview aggregates hourly.';

    /**
     * Standard date floor ranges supported by telemetry reports.
     *
     * @var array<string>
     */
    protected array $ranges = [
        'since_launch',
        '30d',
        '7d',
        '24h',
        '12h',
        '6h',
        '3h',
        '1h',
        'all',
    ];

    /**
     * Execute the console command.
     */
    public function handle(TelemetryReportService $reportService): int
    {
        $generatedAt = Carbon::now()->toIso8601String();

        foreach ($this->ranges as $rangeKey) {
            try {
                $since = $this->parseRangeKey($rangeKey);

                $summaryData = $reportService->summary($since);
                $blueprintData = $reportService->blueprint($since);
                $dwellData = $reportService->dwell($since);

                $payload = [
                    'generated_at' => $generatedAt,
                    'range' => $rangeKey,
                    'summary' => $summaryData,
                    'blueprint' => $blueprintData,
                    'dwell' => $dwellData,
                ];

                Cache::put("telemetry:rollup:{$rangeKey}", $payload, now()->addHours(24));
            } catch (Throwable $e) {
                $this->error("Failed rollup for range {$rangeKey}: " . $e->getMessage());
            }
        }

        $this->info('Telemetry rollup completed successfully.');
        return Command::SUCCESS;
    }

    protected function parseRangeKey(string $rangeKey): Carbon
    {
        return match ($rangeKey) {
            '30d' => Carbon::now()->subDays(30),
            '7d' => Carbon::now()->subDays(7),
            '24h' => Carbon::now()->subHours(24),
            '12h' => Carbon::now()->subHours(12),
            '6h' => Carbon::now()->subHours(6),
            '3h' => Carbon::now()->subHours(3),
            '1h' => Carbon::now()->subHours(1),
            'all' => Carbon::parse('2020-01-01 00:00:00'),
            default => Carbon::parse('2026-09-09 00:00:00'),
        };
    }
}
