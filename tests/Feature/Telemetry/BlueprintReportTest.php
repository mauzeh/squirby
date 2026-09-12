<?php

namespace Tests\Feature\Telemetry;

use App\Sync\Models\AthleteEvent;
use App\Telemetry\Services\TelemetryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BlueprintReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_report_reduces_to_latest_snapshot_per_device_and_computes_distribution(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        // Device A: older snapshot (intensity=low)
        AthleteEvent::query()->create([
            'device_id' => 'device_a',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'type' => 'blueprint_state',
                        'ts' => '2026-09-10T10:00:00Z',
                        'selections' => [
                            'intensity' => 'low',
                            'goals' => ['endurance'],
                        ],
                    ],
                ],
            ],
        ]);

        // Device A: newer snapshot (intensity=high, goals=[hypertrophy])
        AthleteEvent::query()->create([
            'device_id' => 'device_a',
            'created_at' => Carbon::parse('2026-09-10 12:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'type' => 'blueprint_state',
                        'ts' => '2026-09-10T12:00:00Z',
                        'selections' => [
                            'intensity' => 'high',
                            'goals' => ['hypertrophy'],
                        ],
                    ],
                ],
            ],
        ]);

        // Device B: single snapshot (intensity=medium, goals=[hypertrophy])
        AthleteEvent::query()->create([
            'device_id' => 'device_b',
            'created_at' => Carbon::parse('2026-09-10 11:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'type' => 'blueprint_state',
                        'ts' => '2026-09-10T11:00:00Z',
                        'selections' => [
                            'intensity' => 'medium',
                            'goals' => ['hypertrophy'],
                        ],
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $report = $service->blueprint($since);

        $this->assertEquals(2, $report['total']);

        // Assert latest wins for device_a (high, not low)
        $this->assertEquals(1, $report['distribution']['intensity']['high'] ?? 0);
        $this->assertEquals(1, $report['distribution']['intensity']['medium'] ?? 0);
        $this->assertArrayNotHasKey('low', $report['distribution']['intensity'] ?? []);

        // Assert array field distribution (hypertrophy = 2 devices)
        $this->assertEquals(2, $report['distribution']['goals']['hypertrophy'] ?? 0);
    }

    public function test_trail_excludes_blueprint_state_events(): void
    {
        $since = Carbon::parse('2026-09-01 00:00:00');

        AthleteEvent::query()->create([
            'device_id' => 'device_a',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'screen' => 'HomeView',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'type' => 'blueprint_state',
                        'ts' => '2026-09-10T10:05:00Z',
                        'selections' => ['intensity' => 'high'],
                    ],
                    [
                        'screen' => 'SettingsView',
                        'ts' => '2026-09-10T10:10:00Z',
                    ],
                ],
            ],
        ]);

        $service = app(TelemetryReportService::class);
        $trail = $service->trail('device_a', $since);

        $screens = array_column($trail, 'screen');
        $this->assertCount(2, $trail);
        $this->assertContains('HomeView', $screens);
        $this->assertContains('SettingsView', $screens);
        $this->assertNotContains('Unknown', $screens);
    }

    public function test_unauthenticated_user_cannot_access_blueprint_api(): void
    {
        $response = $this->getJson(route('telemetry.blueprint'));
        $response->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_blueprint_api(): void
    {
        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->getJson(route('telemetry.blueprint'));
        $response->assertForbidden();
    }

    public function test_authenticated_admin_can_access_blueprint_api(): void
    {
        $admin = \App\Models\User::factory()->create();
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->getJson(route('telemetry.blueprint'));
        $response->assertOk();
        $response->assertJsonStructure([
            'total',
            'distribution',
            'devices',
        ]);
    }
}
