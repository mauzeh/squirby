<?php

namespace App\Telemetry\Services;

use App\Sync\Models\AthleteEvent;
use Illuminate\Support\Carbon;

class TelemetryReportService
{
    /**
     * Build summary metrics including total distinct devices, bucketed chart series,
     * and paginated device list for the active date floor.
     *
     * @return array{
     *     total: int,
     *     series: array{labels: array<int, string>, new: array<int, int>, cumulative: array<int, int>, active: array<int, int>},
     *     bucket: string,
     *     devices: array{data: array<int, array{device_id: ?string, last_seen: string, event_rows: int}>, current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function summary(Carbon $since, int $page = 1, int $perPage = 20): array
    {
        // 1. Calculate total distinct devices (null device_id counts as 1 group if present)
        $nonNullCount = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('device_id')
            ->distinct('device_id')
            ->count('device_id');

        $hasNullDevice = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNull('device_id')
            ->exists();

        $totalDistinctDevices = $nonNullCount + ($hasNullDevice ? 1 : 0);

        // 2. Paginated device list ordered by MAX(created_at) DESC
        $devicesQuery = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('device_id, MAX(created_at) as last_seen, COUNT(*) as event_rows')
            ->groupBy('device_id')
            ->orderByDesc('last_seen');

        $deviceRows = (clone $devicesQuery)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $devicesData = $deviceRows->map(function ($row) {
            return [
                'device_id' => $row->device_id,
                'last_seen' => Carbon::parse($row->last_seen)->toIso8601String(),
                'event_rows' => (int) $row->event_rows,
            ];
        })->all();

        $lastPage = (int) ceil($totalDistinctDevices / $perPage);
        if ($lastPage < 1) {
            $lastPage = 1;
        }

        $devicesPayload = [
            'data' => $devicesData,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $totalDistinctDevices,
        ];

        // 3. Compute bucketed series for Chart (New, Cumulative, Active)
        [$bucketLabel, $seriesData] = $this->buildChartSeries($since);

        return [
            'total' => $totalDistinctDevices,
            'series' => $seriesData,
            'bucket' => $bucketLabel,
            'devices' => $devicesPayload,
        ];
    }

    /**
     * Unroll single device navigation history from event_data.events, sorted by ts.
     *
     * @return array<int, array{screen: string, ts: string}>
     */
    public function trail(?string $deviceId, Carbon $since): array
    {
        $query = AthleteEvent::query()->where('created_at', '>=', $since);

        if ($deviceId === null || $deviceId === '' || $deviceId === 'null' || $deviceId === 'no value') {
            $query->whereNull('device_id');
        } else {
            $query->where('device_id', $deviceId);
        }

        $rows = $query->get();
        $flattenedEvents = [];

        foreach ($rows as $row) {
            $eventData = $row->event_data;
            if (!is_array($eventData) || !isset($eventData['events']) || !is_array($eventData['events'])) {
                continue;
            }

            foreach ($eventData['events'] as $evt) {
                if (!is_array($evt)) {
                    continue;
                }

                $screen = (string) ($evt['screen'] ?? 'Unknown');
                $rawTs = $evt['ts'] ?? null;
                $tsString = $rawTs ? (string) $rawTs : Carbon::parse($row->created_at)->toIso8601String();

                $flattenedEvents[] = [
                    'screen' => $screen,
                    'ts' => $tsString,
                ];
            }
        }

        // Sort ascending by timestamp
        usort($flattenedEvents, function (array $a, array $b) {
            return strcmp($a['ts'], $b['ts']);
        });

        return array_values($flattenedEvents);
    }

    /**
     * Generate mobile-friendly time buckets and calculate New, Cumulative, and Active metrics.
     *
     * @return array{0: string, 1: array{labels: array<int, string>, new: array<int, int>, cumulative: array<int, int>, active: array<int, int>}}
     */
    protected function buildChartSeries(Carbon $since): array
    {
        $now = Carbon::now();
        $diffHours = (float) $since->diffInHours($now);

        if ($diffHours <= 3) {
            $stepMinutes = 15;
            $stepDays = null;
            $labelFormat = 'H:i';
            $bucketLabel = '15-minute';
        } elseif ($diffHours <= 6) {
            $stepMinutes = 30;
            $stepDays = null;
            $labelFormat = 'H:i';
            $bucketLabel = '30-minute';
        } elseif ($diffHours <= 24) {
            $stepMinutes = 60;
            $stepDays = null;
            $labelFormat = 'H:00';
            $bucketLabel = 'Hourly';
        } elseif ($diffHours <= 24 * 7) {
            $stepMinutes = null;
            $stepDays = 1;
            $labelFormat = 'M j';
            $bucketLabel = 'Daily';
        } elseif ($diffHours <= 24 * 30) {
            $stepMinutes = null;
            $stepDays = 1;
            $labelFormat = 'M j';
            $bucketLabel = 'Daily';
        } else {
            $diffDays = (float) $since->diffInDays($now);
            if ($diffDays > 60) {
                $stepMinutes = null;
                $stepDays = 7;
                $labelFormat = 'M j';
                $bucketLabel = 'Weekly';
            } else {
                $stepMinutes = null;
                $stepDays = 1;
                $labelFormat = 'M j';
                $bucketLabel = 'Daily';
            }
        }

        // Generate time slots
        $buckets = [];
        $cursor = $since->copy();
        while ($cursor < $now) {
            $next = $stepMinutes !== null
                ? $cursor->copy()->addMinutes($stepMinutes)
                : $cursor->copy()->addDays($stepDays ?? 1);

            $buckets[] = [
                'start' => $cursor,
                'end' => $next,
                'label' => $cursor->format($labelFormat),
                'active_devices' => [],
                'new_devices' => [],
            ];
            $cursor = $next;
        }

        if (empty($buckets)) {
            $buckets[] = [
                'start' => $since->copy(),
                'end' => $now->copy(),
                'label' => $since->format($labelFormat),
                'active_devices' => [],
                'new_devices' => [],
            ];
        }

        // First-seen per device in active range
        $firstSeenRows = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('device_id, MIN(created_at) as min_created_at')
            ->groupBy('device_id')
            ->get();

        foreach ($firstSeenRows as $fs) {
            $devKey = $fs->device_id ?? '__NULL__';
            $fsTime = Carbon::parse($fs->min_created_at);

            foreach ($buckets as &$b) {
                if ($fsTime >= $b['start'] && $fsTime < $b['end']) {
                    $b['new_devices'][$devKey] = true;
                    break;
                }
            }
            unset($b);
        }

        // Active devices per bucket
        $activeRows = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->select('device_id', 'created_at')
            ->get();

        foreach ($activeRows as $ar) {
            $devKey = $ar->device_id ?? '__NULL__';
            $arTime = Carbon::parse($ar->created_at);

            foreach ($buckets as &$b) {
                if ($arTime >= $b['start'] && $arTime < $b['end']) {
                    $b['active_devices'][$devKey] = true;
                    break;
                }
            }
            unset($b);
        }

        $labels = [];
        $newSeries = [];
        $cumSeries = [];
        $activeSeries = [];
        $runningCumulative = 0;

        foreach ($buckets as $b) {
            $newCount = count($b['new_devices']);
            $activeCount = count($b['active_devices']);
            $runningCumulative += $newCount;

            $labels[] = $b['label'];
            $newSeries[] = $newCount;
            $cumSeries[] = $runningCumulative;
            $activeSeries[] = $activeCount;
        }

        return [
            $bucketLabel,
            [
                'labels' => $labels,
                'new' => $newSeries,
                'cumulative' => $cumSeries,
                'active' => $activeSeries,
            ],
        ];
    }
}
