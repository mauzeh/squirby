<?php

namespace Tests\Feature\Telemetry;

use App\Sync\Models\AthleteEvent;
use App\Telemetry\Services\TelemetryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TelemetryDwellTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_normalization_helper(): void
    {
        $service = new TelemetryReportService();

        // Strips _v param only
        $this->assertEquals('/plan', $service->normalizeScreen('/plan?_v=a1b2'));
        $this->assertEquals('/plan', $service->normalizeScreen('/plan?_v=a1b2&_v=c3d4'));
        $this->assertEquals('/express?step=1', $service->normalizeScreen('/express?step=1&_v=xyz'));
        $this->assertEquals('/express?step=summary', $service->normalizeScreen('/express?step=summary&_v=xyz'));
        $this->assertEquals('/home', $service->normalizeScreen('/home'));
    }

    public function test_dwell_prefer_leave_duration_over_heartbeats(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/dashboard?_v=1',
                        'session_id' => 'sess1',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'event' => 'heartbeat',
                        'screen' => '/dashboard?_v=1',
                        'session_id' => 'sess1',
                        'duration_ms' => 15000,
                        'ts' => '2026-09-10T10:00:15Z',
                    ],
                    [
                        'event' => 'leave',
                        'screen' => '/dashboard?_v=1',
                        'session_id' => 'sess1',
                        'duration_ms' => 25000,
                        'ts' => '2026-09-10T10:00:25Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $result = $service->dwell($since);

        $this->assertEquals(25000, $result['overall_median_ms']);
        $this->assertCount(1, $result['screens']);
        $this->assertEquals('/dashboard', $result['screens'][0]['screen']);
        $this->assertEquals(25000, $result['screens'][0]['median_ms']);
        $this->assertEquals(1, $result['screens'][0]['count']);
    }

    public function test_dwell_fallback_to_last_heartbeat_max(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/profile',
                        'session_id' => 'sess1',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'event' => 'heartbeat',
                        'screen' => '/profile',
                        'session_id' => 'sess1',
                        'duration_ms' => 15000,
                        'ts' => '2026-09-10T10:00:15Z',
                    ],
                    [
                        'event' => 'heartbeat',
                        'screen' => '/profile',
                        'session_id' => 'sess1',
                        'duration_ms' => 30000,
                        'ts' => '2026-09-10T10:00:30Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $result = $service->dwell($since);

        $this->assertEquals(30000, $result['overall_median_ms']);
        $this->assertEquals(30000, $result['screens'][0]['median_ms']);
    }

    public function test_dwell_excluded_when_no_leave_and_no_heartbeat(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/abandoned',
                        'session_id' => 'sess1',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $result = $service->dwell($since);

        $this->assertNull($result['overall_median_ms']);
        $this->assertEmpty($result['screens']);
    }

    public function test_sessionless_events_excluded_from_dwell(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/historic',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'event' => 'leave',
                        'screen' => '/historic',
                        'duration_ms' => 50000,
                        'ts' => '2026-09-10T10:00:50Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $result = $service->dwell($since);

        $this->assertNull($result['overall_median_ms']);
        $this->assertEmpty($result['screens']);
    }

    public function test_median_math_odd_and_even(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        // Session 1: 10000ms
        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    ['event' => 'view', 'screen' => '/page', 'session_id' => 's1', 'ts' => '2026-09-10T10:00:00Z'],
                    ['event' => 'leave', 'screen' => '/page', 'session_id' => 's1', 'duration_ms' => 10000, 'ts' => '2026-09-10T10:00:10Z'],
                ],
            ],
        ]);

        // Session 2: 20000ms
        AthleteEvent::query()->create([
            'device_id' => 'dev2',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    ['event' => 'view', 'screen' => '/page', 'session_id' => 's2', 'ts' => '2026-09-10T10:00:00Z'],
                    ['event' => 'leave', 'screen' => '/page', 'session_id' => 's2', 'duration_ms' => 20000, 'ts' => '2026-09-10T10:00:20Z'],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $resultEven = $service->dwell($since);
        // Even count (10000, 20000) -> mean of middle two = 15000
        $this->assertEquals(15000, $resultEven['overall_median_ms']);

        // Session 3: 30000ms
        AthleteEvent::query()->create([
            'device_id' => 'dev3',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    ['event' => 'view', 'screen' => '/page', 'session_id' => 's3', 'ts' => '2026-09-10T10:00:00Z'],
                    ['event' => 'leave', 'screen' => '/page', 'session_id' => 's3', 'duration_ms' => 30000, 'ts' => '2026-09-10T10:00:30Z'],
                ],
            ],
        ]);

        $resultOdd = $service->dwell($since);
        // Odd count (10000, 20000, 30000) -> middle = 20000
        $this->assertEquals(20000, $resultOdd['overall_median_ms']);
    }
}
