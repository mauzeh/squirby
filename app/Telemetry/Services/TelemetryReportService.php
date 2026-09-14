<?php

namespace App\Telemetry\Services;

use App\Sync\Models\AthleteEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TelemetryReportService
{
    /**
     * Normalize screen key: strip _v query param, keep all other query params.
     */
    public function normalizeScreen(string $screen): string
    {
        $parts = explode('?', $screen, 2);
        if (count($parts) < 2) {
            return $screen;
        }

        $path = $parts[0];
        parse_str($parts[1], $queryParams);
        unset($queryParams['_v']);

        if (empty($queryParams)) {
            return $path;
        }

        return $path . '?' . http_build_query($queryParams);
    }

    /**
     * Compute median dwell metrics per screen and overall median across screen-opens.
     *
     * @return array{
     *     overall_median_ms: ?int,
     *     screens: array<int, array{screen: string, median_ms: int, count: int}>
     * }
     */
    public function dwell(Carbon $since): array
    {
        $rows = AthleteEvent::query()
            ->where('created_at', '>=', $since)
            ->get();

        // 1. Group events by (device_id, session_id)
        $eventsBySession = [];

        foreach ($rows as $row) {
            $eventData = $row->event_data;
            if (!is_array($eventData) || !isset($eventData['events']) || !is_array($eventData['events'])) {
                continue;
            }

            $deviceId = $row->device_id ?? '__NULL__';

            foreach ($eventData['events'] as $evt) {
                if (!is_array($evt)) {
                    continue;
                }

                $sessionId = $evt['session_id'] ?? null;
                if (!$sessionId || !is_string($sessionId)) {
                    continue;
                }

                $type = $evt['type'] ?? null;
                if ($type === 'blueprint_state') {
                    continue;
                }

                $rawTs = $evt['ts'] ?? null;
                $tsString = $rawTs ? (string) $rawTs : Carbon::parse($row->created_at)->toIso8601String();

                $sessKey = $deviceId . '|' . $sessionId;
                $eventsBySession[$sessKey][] = [
                    'device_id' => $deviceId,
                    'session_id' => $sessionId,
                    'screen' => (string) ($evt['screen'] ?? 'Unknown'),
                    'event' => (string) ($evt['event'] ?? 'view'),
                    'duration_ms' => isset($evt['duration_ms']) ? (int) $evt['duration_ms'] : null,
                    'ts' => $tsString,
                ];
            }
        }

        $allDwells = []; // list of all resolved dwell_ms integers across opens
        $screenDwells = []; // normalized_screen => list of dwell_ms integers

        // 2. Process each session chronologically
        foreach ($eventsBySession as $events) {
            usort($events, fn ($a, $b) => strcmp($a['ts'], $b['ts']));

            $currentOpen = null;

            foreach ($events as $evt) {
                $rawScreen = $evt['screen'];
                $normalizedScreen = $this->normalizeScreen($rawScreen);
                $eventType = $evt['event'];
                $durationMs = $evt['duration_ms'];

                if ($eventType === 'view') {
                    if ($currentOpen !== null) {
                        // Close previous open if view comes without leave
                        $resolved = $this->resolveOpenDwell($currentOpen);
                        if ($resolved !== null) {
                            $allDwells[] = $resolved;
                            $screenDwells[$currentOpen['screen']][] = $resolved;
                        }
                    }
                    $currentOpen = [
                        'screen' => $normalizedScreen,
                        'leave_duration' => null,
                        'max_heartbeat' => null,
                    ];
                } elseif ($currentOpen !== null && $evt['event'] === 'heartbeat') {
                    if ($durationMs !== null) {
                        if ($currentOpen['max_heartbeat'] === null || $durationMs > $currentOpen['max_heartbeat']) {
                            $currentOpen['max_heartbeat'] = $durationMs;
                        }
                    }
                } elseif ($currentOpen !== null && $evt['event'] === 'leave') {
                    if ($durationMs !== null) {
                        $currentOpen['leave_duration'] = $durationMs;
                    }
                    $resolved = $this->resolveOpenDwell($currentOpen);
                    if ($resolved !== null) {
                        $allDwells[] = $resolved;
                        $screenDwells[$currentOpen['screen']][] = $resolved;
                    }
                    $currentOpen = null;
                }
            }

            if ($currentOpen !== null) {
                $resolved = $this->resolveOpenDwell($currentOpen);
                if ($resolved !== null) {
                    $allDwells[] = $resolved;
                    $screenDwells[$currentOpen['screen']][] = $resolved;
                }
            }
        }

        // 3. Calculate medians
        $overallMedian = $this->calculateMedian($allDwells);

        $screensResult = [];
        foreach ($screenDwells as $screenKey => $dwellList) {
            $median = $this->calculateMedian($dwellList);
            if ($median !== null) {
                $screensResult[] = [
                    'screen' => $screenKey,
                    'median_ms' => $median,
                    'count' => count($dwellList),
                ];
            }
        }

        usort($screensResult, fn ($a, $b) => $b['median_ms'] <=> $a['median_ms']);

        return [
            'overall_median_ms' => $overallMedian,
            'screens' => $screensResult,
        ];
    }

    protected function resolveOpenDwell(array $open): ?int
    {
        if ($open['leave_duration'] !== null) {
            return $open['leave_duration'];
        }
        if ($open['max_heartbeat'] !== null) {
            return $open['max_heartbeat'];
        }
        return null;
    }

    protected function calculateMedian(array $numbers): ?int
    {
        if (empty($numbers)) {
            return null;
        }

        sort($numbers, SORT_NUMERIC);
        $count = count($numbers);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (int) round($numbers[$middle]);
        } else {
            return (int) round(($numbers[$middle - 1] + $numbers[$middle]) / 2);
        }
    }
    /**
     * Unroll latest blueprint_state snapshots per device and compute per-field value distribution.
     *
     * @return array{
     *     total: int,
     *     distribution: array<string, array<string, int>>,
     *     devices: array<int, array{device_id: ?string, selections: array<string, mixed>, ts: string}>
     * }
     */
    public function blueprint(Carbon $since): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = AthleteEvent::query()
                ->where('created_at', '>=', $since)
                ->get();

            $latestPerDevice = [];
            foreach ($rows as $row) {
                $eventData = $row->event_data;
                if (!is_array($eventData) || !isset($eventData['events']) || !is_array($eventData['events'])) {
                    continue;
                }

                foreach ($eventData['events'] as $evt) {
                    if (!is_array($evt) || ($evt['type'] ?? null) !== 'blueprint_state') {
                        continue;
                    }

                    $devId = $row->device_id;
                    $ts = (string) ($evt['ts'] ?? Carbon::parse($row->created_at)->toIso8601String());
                    $selections = $evt['selections'] ?? [];
                    if (!is_array($selections)) {
                        $selections = [];
                    }

                    $devKey = $devId ?? '__NULL__';
                    if (!isset($latestPerDevice[$devKey]) || strcmp($ts, $latestPerDevice[$devKey]['ts']) > 0) {
                        $latestPerDevice[$devKey] = [
                            'device_id' => $devId,
                            'ts' => $ts,
                            'selections' => $selections,
                        ];
                    }
                }
            }

            $deviceSnapshots = array_values($latestPerDevice);
        } else {
            $sql = <<<'SQL'
                SELECT device_id, ts, selections
                FROM (
                    SELECT 
                        ae.device_id,
                        ev.ts,
                        ev.selections,
                        ROW_NUMBER() OVER (PARTITION BY ae.device_id ORDER BY ev.ts DESC) as rn
                    FROM athlete_events ae
                    JOIN JSON_TABLE(
                        ae.event_data, '$.events[*]'
                        COLUMNS (
                            etype VARCHAR(32) PATH '$.type',
                            ts VARCHAR(40) PATH '$.ts',
                            selections JSON PATH '$.selections'
                        )
                    ) ev
                    WHERE ae.created_at >= ?
                      AND ev.etype = 'blueprint_state'
                ) sub
                WHERE rn = 1
            SQL;

            $rawResults = DB::select($sql, [$since->toDateTimeString()]);

            $deviceSnapshots = [];
            foreach ($rawResults as $row) {
                $selections = is_string($row->selections)
                    ? json_decode($row->selections, true)
                    : (array) $row->selections;

                $deviceSnapshots[] = [
                    'device_id' => $row->device_id,
                    'ts' => (string) $row->ts,
                    'selections' => is_array($selections) ? $selections : [],
                ];
            }
        }

        // Bounded PHP reduce over latest-per-device snapshots
        $distribution = [];

        foreach ($deviceSnapshots as $snap) {
            $selections = $snap['selections'];

            foreach ($selections as $field => $val) {
                if ($val === null) {
                    continue;
                }

                if (!isset($distribution[$field])) {
                    $distribution[$field] = [];
                }

                if (is_array($val)) {
                    if (array_is_list($val)) {
                        foreach ($val as $item) {
                            if ($item === null) {
                                continue;
                            }
                            $itemKey = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                            $distribution[$field][$itemKey] = ($distribution[$field][$itemKey] ?? 0) + 1;
                        }
                    } else {
                        foreach ($val as $k => $v) {
                            if ($v === true || $v === 1 || $v === 'true' || $v === '1') {
                                $kStr = (string) $k;
                                $distribution[$field][$kStr] = ($distribution[$field][$kStr] ?? 0) + 1;
                            } elseif (is_string($v) && $v !== '') {
                                $kStr = "$k: $v";
                                $distribution[$field][$kStr] = ($distribution[$field][$kStr] ?? 0) + 1;
                            }
                        }
                    }
                } else {
                    $valStr = is_bool($val) ? ($val ? 'true' : 'false') : (string) $val;
                    $distribution[$field][$valStr] = ($distribution[$field][$valStr] ?? 0) + 1;
                }
            }
        }

        return [
            'total' => count($deviceSnapshots),
            'distribution' => $distribution,
            'devices' => $deviceSnapshots,
        ];
    }
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
     * Live per-device session-paginated deep dive report.
     *
     * @return array{
     *     device_id: ?string,
     *     sessions: array{
     *         data: array<int, array{
     *             session_id: string,
     *             started_at: string,
     *             events: array<int, array{screen: string, event: string, session_id: string, duration_ms: ?int, ts: string}>
     *         }>,
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int
     *     },
     *     sessionless_events: array{
     *         data: array<int, array{screen: string, event: string, session_id: null, duration_ms: ?int, ts: string}>,
     *         total: int
     *     }
     * }
     */
    public function deviceDeepDive(?string $deviceId, Carbon $since, int $page = 1, int $perPage = 10, int $sessionlessLimit = 50): array
    {
        $query = AthleteEvent::query()->where('created_at', '>=', $since);

        if ($deviceId === null || $deviceId === '' || $deviceId === 'null' || $deviceId === 'no value') {
            $query->whereNull('device_id');
        } else {
            $query->where('device_id', $deviceId);
        }

        $rows = $query->get();

        $sessionsMap = []; // session_id => array of events
        $sessionStartTs = []; // session_id => earliest ts
        $sessionlessEvents = [];

        foreach ($rows as $row) {
            $eventData = $row->event_data;
            if (!is_array($eventData) || !isset($eventData['events']) || !is_array($eventData['events'])) {
                continue;
            }

            foreach ($eventData['events'] as $evt) {
                if (!is_array($evt)) {
                    continue;
                }

                if (($evt['type'] ?? null) === 'blueprint_state') {
                    continue;
                }

                $screen = (string) ($evt['screen'] ?? 'Unknown');
                $rawTs = $evt['ts'] ?? null;
                $tsString = $rawTs ? (string) $rawTs : Carbon::parse($row->created_at)->toIso8601String();
                $sessionId = $evt['session_id'] ?? null;
                $eventType = (string) ($evt['event'] ?? 'view');
                $durationMs = isset($evt['duration_ms']) ? (int) $evt['duration_ms'] : null;

                $eventObj = [
                    'screen' => $screen, // RAW screen — keep _v
                    'event' => $eventType,
                    'session_id' => $sessionId,
                    'duration_ms' => $durationMs,
                    'ts' => $tsString,
                ];

                if ($sessionId !== null && is_string($sessionId) && $sessionId !== '') {
                    if (!isset($sessionsMap[$sessionId])) {
                        $sessionsMap[$sessionId] = [];
                        $sessionStartTs[$sessionId] = $tsString;
                    } elseif (strcmp($tsString, $sessionStartTs[$sessionId]) < 0) {
                        $sessionStartTs[$sessionId] = $tsString;
                    }

                    $sessionsMap[$sessionId][] = $eventObj;
                } else {
                    $sessionlessEvents[] = $eventObj;
                }
            }
        }

        // Sort events within each session by ts asc
        foreach ($sessionsMap as $sessId => &$eventsList) {
            usort($eventsList, fn ($a, $b) => strcmp($a['ts'], $b['ts']));
        }
        unset($eventsList);

        // Build list of sessions ordered by most-recent event / start ts desc
        $sessionKeys = array_keys($sessionsMap);
        usort($sessionKeys, fn ($a, $b) => strcmp($sessionStartTs[$b], $sessionStartTs[$a]));

        $totalSessions = count($sessionKeys);
        $lastPage = max(1, (int) ceil($totalSessions / $perPage));
        $page = min(max(1, $page), $lastPage);

        $pagedKeys = array_slice($sessionKeys, ($page - 1) * $perPage, $perPage);

        $sessionsData = [];
        foreach ($pagedKeys as $sessId) {
            $sessionsData[] = [
                'session_id' => $sessId,
                'started_at' => $sessionStartTs[$sessId],
                'events' => $sessionsMap[$sessId],
            ];
        }

        // Sort sessionless events desc (most recent first) and cap
        usort($sessionlessEvents, fn ($a, $b) => strcmp($b['ts'], $a['ts']));
        $cappedSessionless = array_slice($sessionlessEvents, 0, $sessionlessLimit);

        return [
            'device_id' => $deviceId,
            'sessions' => [
                'data' => $sessionsData,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalSessions,
            ],
            'sessionless_events' => [
                'data' => $cappedSessionless,
                'total' => count($sessionlessEvents),
            ],
        ];
    }

    /**
     * Unroll single device navigation history from event_data.events, sorted by ts.
     *
     * @return array<int, array{screen: string, event: string, session_id: ?string, duration_ms: ?int, ts: string}>
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

                if (($evt['type'] ?? null) === 'blueprint_state') {
                    continue;
                }

                $screen = (string) ($evt['screen'] ?? 'Unknown');
                $rawTs = $evt['ts'] ?? null;
                $tsString = $rawTs ? (string) $rawTs : Carbon::parse($row->created_at)->toIso8601String();

                $flattenedEvents[] = [
                    'screen' => $screen,
                    'event' => (string) ($evt['event'] ?? 'view'),
                    'session_id' => $evt['session_id'] ?? null,
                    'duration_ms' => isset($evt['duration_ms']) ? (int) $evt['duration_ms'] : null,
                    'ts' => $tsString,
                ];
            }
        }

        // Sort descending by timestamp (most recent first)
        usort($flattenedEvents, function (array $a, array $b) {
            return strcmp($b['ts'], $a['ts']);
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
