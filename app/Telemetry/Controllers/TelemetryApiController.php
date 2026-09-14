<?php

namespace App\Telemetry\Controllers;

use App\Http\Controllers\Controller;
use App\Telemetry\Services\TelemetryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TelemetryApiController extends Controller
{
    public function __construct(
        protected TelemetryReportService $reportService
    ) {}

    /**
     * Return headline count, 3-metric chart series, and paginated device list as JSON.
     */
    public function summary(Request $request): JsonResponse
    {
        $sinceParam = $request->query('since', 'since_launch');
        $rangeKey = $this->normalizeRangeKey($sinceParam);
        $page = max(1, (int) $request->query('page', 1));

        $rollup = $this->getOrGenerateRollup($rangeKey);

        $summary = $rollup['summary'];

        // Handle page offset if page > 1 (re-paginate devices array if requested)
        if ($page > 1) {
            $since = $this->parseSince($sinceParam);
            $summary = $this->reportService->summary($since, $page);
        }

        return response()->json(array_merge($summary, [
            'dwell' => $rollup['dwell'],
            'generated_at' => $rollup['generated_at'],
        ]));
    }

    /**
     * Return ordered navigation trail for a single device as JSON.
     */
    public function trail(Request $request): JsonResponse
    {
        $since = $this->parseSince($request->query('since'));
        $deviceId = $request->query('device_id');

        if ($deviceId === 'null' || $deviceId === 'no value' || $deviceId === '') {
            $deviceId = null;
        }

        $data = $this->reportService->trail($deviceId, $since);

        return response()->json($data);
    }

    /**
     * Return live per-device session-paginated deep dive report as JSON.
     */
    public function device(Request $request): JsonResponse
    {
        $since = $this->parseSince($request->query('since'));
        $deviceId = $request->query('device_id');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));

        if ($deviceId === 'null' || $deviceId === 'no value' || $deviceId === '') {
            $deviceId = null;
        }

        $data = $this->reportService->deviceDeepDive($deviceId, $since, $page, $perPage);

        return response()->json($data);
    }

    /**
     * Return blueprint state value distribution and latest per-device selections as JSON.
     */
    public function blueprint(Request $request): JsonResponse
    {
        $sinceParam = $request->query('since', 'since_launch');
        $rangeKey = $this->normalizeRangeKey($sinceParam);

        $rollup = $this->getOrGenerateRollup($rangeKey);

        return response()->json(array_merge($rollup['blueprint'], [
            'generated_at' => $rollup['generated_at'],
        ]));
    }

    /**
     * Get cached rollup or generate cold-start fallback payload.
     */
    protected function getOrGenerateRollup(string $rangeKey): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get("telemetry:rollup:{$rangeKey}");
        if ($cached && is_array($cached) && isset($cached['generated_at'])) {
            return $cached;
        }

        $since = $this->parseSince($rangeKey);
        $generatedAt = Carbon::now()->toIso8601String();

        $payload = [
            'generated_at' => $generatedAt,
            'range' => $rangeKey,
            'summary' => $this->reportService->summary($since),
            'blueprint' => $this->reportService->blueprint($since),
            'dwell' => $this->reportService->dwell($since),
        ];

        \Illuminate\Support\Facades\Cache::put("telemetry:rollup:{$rangeKey}", $payload, now()->addHours(24));

        return $payload;
    }

    protected function normalizeRangeKey(?string $since): string
    {
        if (empty($since) || $since === 'since_launch') {
            return 'since_launch';
        }

        return in_array($since, ['30d', '7d', '24h', '12h', '6h', '3h', '1h', 'all']) ? $since : 'custom';
    }

    /**
     * Parse date floor from request input, defaulting to 2026-09-09 (since launch).
     */
    protected function parseSince(?string $since): Carbon
    {
        if (empty($since) || $since === 'since_launch') {
            return Carbon::parse('2026-09-09 00:00:00');
        }

        return match ($since) {
            '30d' => Carbon::now()->subDays(30),
            '7d' => Carbon::now()->subDays(7),
            '24h' => Carbon::now()->subHours(24),
            '12h' => Carbon::now()->subHours(12),
            '6h' => Carbon::now()->subHours(6),
            '3h' => Carbon::now()->subHours(3),
            '1h' => Carbon::now()->subHours(1),
            'all' => Carbon::parse('2020-01-01 00:00:00'),
            default => rescue(
                fn () => Carbon::parse($since)->startOfDay(),
                Carbon::parse('2026-09-09 00:00:00'),
                false
            ),
        };
    }

}
