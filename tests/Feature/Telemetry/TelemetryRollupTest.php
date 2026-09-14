<?php

namespace Tests\Feature\Telemetry;

use App\Models\Role;
use App\Models\User;
use App\Sync\Models\AthleteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TelemetryRollupTest extends TestCase
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

    public function test_rollup_command_populates_cache_with_generated_at(): void
    {
        AthleteEvent::query()->create([
            'device_id' => 'dev1',
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'event_data' => [
                'events' => [
                    [
                        'event' => 'view',
                        'screen' => '/dashboard',
                        'session_id' => 's1',
                        'ts' => '2026-09-10T10:00:00Z',
                    ],
                    [
                        'event' => 'leave',
                        'screen' => '/dashboard',
                        'session_id' => 's1',
                        'duration_ms' => 12000,
                        'ts' => '2026-09-10T10:00:12Z',
                    ],
                ],
            ],
        ]);

        Cache::flush();
        $this->assertFalse(Cache::has('telemetry:rollup:since_launch'));

        Artisan::call('telemetry:rollup');

        $this->assertTrue(Cache::has('telemetry:rollup:since_launch'));
        $cached = Cache::get('telemetry:rollup:since_launch');
        $this->assertArrayHasKey('generated_at', $cached);
        $this->assertArrayHasKey('summary', $cached);
        $this->assertArrayHasKey('blueprint', $cached);
        $this->assertArrayHasKey('dwell', $cached);
        $this->assertEquals(12000, $cached['dwell']['overall_median_ms']);
    }

    public function test_api_summary_endpoint_serves_cached_rollup_with_generated_at(): void
    {
        Cache::flush();
        $fakeGeneratedAt = '2026-09-13T12:00:00+00:00';
        Cache::put('telemetry:rollup:since_launch', [
            'generated_at' => $fakeGeneratedAt,
            'summary' => [
                'total' => 42,
                'series' => ['labels' => [], 'new' => [], 'cumulative' => [], 'active' => []],
                'bucket' => 'Daily',
                'devices' => ['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 42],
            ],
            'blueprint' => ['total' => 0, 'distribution' => [], 'devices' => []],
            'dwell' => ['overall_median_ms' => 5000, 'screens' => []],
        ], 3600);

        $response = $this->actingAs($this->admin)->getJson(route('telemetry.summary'));
        $response->assertOk();
        $response->assertJson([
            'total' => 42,
            'generated_at' => $fakeGeneratedAt,
            'dwell' => ['overall_median_ms' => 5000],
        ]);
    }

    public function test_cold_start_fallback_computes_and_caches_when_cache_empty(): void
    {
        Cache::flush();
        $this->assertFalse(Cache::has('telemetry:rollup:since_launch'));

        $response = $this->actingAs($this->admin)->getJson(route('telemetry.summary'));
        $response->assertOk();
        $response->assertJsonStructure(['total', 'generated_at', 'dwell']);
        $this->assertTrue(Cache::has('telemetry:rollup:since_launch'));
    }
}
