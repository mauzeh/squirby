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
        $since = $this->parseSince($request->query('since'));
        $page = max(1, (int) $request->query('page', 1));

        $data = $this->reportService->summary($since, $page);

        return response()->json($data);
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
     * Parse date floor from request input, defaulting to 2026-09-01 (since launch).
     */
    protected function parseSince(?string $since): Carbon
    {
        if (empty($since) || $since === 'since_launch') {
            return Carbon::parse('2026-09-01 00:00:00');
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
                Carbon::parse('2026-09-01 00:00:00'),
                false
            ),
        };
    }
}
