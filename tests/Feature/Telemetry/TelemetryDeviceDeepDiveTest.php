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

        // Second session is sess_old
        $this->assertEquals('sess_old', $res['sessions']['data'][1]['session_id']);

        // Sessionless events bucket
        $this->assertEquals(1, $res['sessionless_events']['total']);
        $this->assertEquals('/old_view?_v=999', $res['sessionless_events']['data'][0]['screen']);
    }

    public function test_device_api_endpoint(): void
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
        $response->assertJsonStructure([
            'device_id',
            'sessions' => ['data', 'current_page', 'last_page', 'per_page', 'total'],
            'sessionless_events' => ['data', 'total'],
        ]);
    }
}
