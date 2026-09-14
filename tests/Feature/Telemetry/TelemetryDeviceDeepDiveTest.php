<?php

namespace Tests\Feature\Telemetry;

use App\Models\Role;
use App\Models\User;
use App\Sync\Models\AthleteEvent;
use App\Telemetry\Services\TelemetryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TelemetryDeviceDeepDiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);
    }

    public function test_device_deep_dive_returns_session_paginated_events_newest_first_preserving_raw_screen(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        // Session 1 (older)
        AthleteEvent::query()->create([
            'device_id' => 'dev123',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/express?step=1&_v=123',
                        'session_id' => 'sess_old',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'event' => 'leave',
                        'screen' => '/express?step=1&_v=123',
                        'session_id' => 'sess_old',
                        'duration_ms' => 15000,
                        'ts' => '2026-09-10T10:00:15Z',
                    ],
                ],
            ],
        ]);

        // Session 2 (newer)
        AthleteEvent::query()->create([
            'device_id' => 'dev123',
            'created_at' => Carbon::parse('2026-09-10 12:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/home?_v=456',
                        'session_id' => 'sess_new',
                        'ts' => '2026-09-10T12:00:00Z',
                    ],
                ],
            ],
        ]);

        // Historic sessionless event
        AthleteEvent::query()->create([
            'device_id' => 'dev123',
            'created_at' => Carbon::parse('2026-09-05 08:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/old_view?_v=999',
                        'ts' => '2026-09-05T08:00:00Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $res = $service->deviceDeepDive('dev123', $since, 1, 10);

        $this->assertEquals(2, $res['sessions']['total']);
        $this->assertCount(2, $res['sessions']['data']);

        // First session returned is the newest session (sess_new)
        $this->assertEquals('sess_new', $res['sessions']['data'][0]['session_id']);
        $this->assertEquals('/home?_v=456', $res['sessions']['data'][0]['events'][0]['screen']); // Raw screen preserved with _v

        // Session carries the UI-consumed keys: start_time + formatted span
        $this->assertArrayHasKey('start_time', $res['sessions']['data'][0]);
        $this->assertArrayHasKey('duration_formatted', $res['sessions']['data'][0]);

        // Second session is sess_old; its 15s leave formats as "15s"
        $this->assertEquals('sess_old', $res['sessions']['data'][1]['session_id']);
        $leaveEvent = collect($res['sessions']['data'][1]['events'])->firstWhere('event', 'leave');
        $this->assertEquals('15s', $leaveEvent['duration_formatted']);

        // Sessionless events bucket (renamed to `sessionless`)
        $this->assertEquals(1, $res['sessionless']['total']);
        $this->assertEquals('/old_view?_v=999', $res['sessionless']['data'][0]['screen']);
    }

    public function test_device_deep_dive_returns_stats_blueprint_and_dwell_for_the_ui(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'dev_full',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    ['event' => 'view', 'screen' => '/plan?_v=1', 'session_id' => 's1', 'ts' => '2026-09-10T10:00:00Z'],
                    ['event' => 'leave', 'screen' => '/plan?_v=1', 'session_id' => 's1', 'duration_ms' => 60000, 'ts' => '2026-09-10T10:01:00Z'],
                    ['type' => 'blueprint_state', 'selections' => ['intensity' => 'high'], 'session_id' => 's1', 'ts' => '2026-09-10T10:00:05Z'],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $res = $service->deviceDeepDive('dev_full', $since, 1, 10);

        // stats block (UI reads session_count/event_count/engaged_formatted/first_seen/last_seen)
        $this->assertEquals(1, $res['stats']['session_count']);
        $this->assertEquals('1m 0s', $res['stats']['engaged_formatted']);
        $this->assertArrayHasKey('first_seen', $res['stats']);
        $this->assertArrayHasKey('last_seen', $res['stats']);

        // blueprint block (UI reads selections)
        $this->assertEquals(['intensity' => 'high'], $res['blueprint']['selections']);

        // per-device dwell (UI reads screen/median_ms/median_formatted); normalized (_v stripped)
        $this->assertEquals('/plan', $res['dwell'][0]['screen']);
        $this->assertEquals(60000, $res['dwell'][0]['median_ms']);
        $this->assertEquals('1m 0s', $res['dwell'][0]['median_formatted']);
    }

    public function test_device_api_endpoint_returns_the_full_ui_shape(): void
    {
        AthleteEvent::query()->create([
            'device_id' => 'dev_api',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/dashboard',
                        'session_id' => 'sess_api',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('telemetry.device', ['device_id' => 'dev_api']));
        $response->assertOk();
        // Assert the EXACT shape the dashboard JS/Blade consumes (guards against slice drift).
        $response->assertJsonStructure([
            'device_id',
            'stats' => ['session_count', 'event_count', 'engaged_ms', 'engaged_formatted', 'first_seen', 'last_seen'],
            'blueprint' => ['selections', 'ts'],
            'dwell',
            'sessions' => ['data', 'current_page', 'last_page', 'per_page', 'total'],
            'sessionless' => ['data', 'total'],
        ]);
    }

    public function test_summary_api_exposes_formatted_dwell_the_ui_reads(): void
    {
        AthleteEvent::query()->create([
            'device_id' => 'dev_sum',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    ['event' => 'view', 'screen' => '/plan?_v=1', 'session_id' => 's1', 'ts' => '2026-09-10T10:00:00Z'],
                    ['event' => 'leave', 'screen' => '/plan?_v=1', 'session_id' => 's1', 'duration_ms' => 45000, 'ts' => '2026-09-10T10:00:45Z'],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('telemetry.summary', ['since' => 'all']));
        $response->assertOk();
        $response->assertJsonStructure([
            'total', 'series', 'bucket', 'devices',
            'generated_at',
            'dwell' => ['overall_median_ms', 'overall_median_formatted', 'screens'],
        ]);
        // Per-screen entries carry median_formatted + normalized screen key
        $json = $response->json();
        $this->assertEquals('/plan', $json['dwell']['screens'][0]['screen']);
        $this->assertEquals('45s', $json['dwell']['screens'][0]['median_formatted']);
    }
}
