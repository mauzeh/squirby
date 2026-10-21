<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrismWriteThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
    }

    public function test_prism_poll_limiter_headroom_is_configured_correctly(): void
    {
        $perMinute = config('rate_limits.prism_poll.per_minute');
        $this->assertNotNull($perMinute);
        $this->assertGreaterThanOrEqual(60, $perMinute);
    }

    public function test_prism_write_endpoints_allow_burst_above_athlete_sync_ceiling(): void
    {
        // Athlete sync-per-user limit is 10/min. Prism write limit is 120/min.
        // We assert that driving 20 writes within the same minute does NOT return 429.
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 20; $i++) {
            $this->travel(1)->seconds();
            $response = $this->withHeaders(['X-Device-Id' => 'dev-throttle-test'])
                ->postJson('/api/sync/prism/the-cove/programs/prog-throttle/cells', [
                    'cells' => [
                        ['cell_id' => 'w1d1', 'text' => "Write number {$i}"],
                    ],
                ]);

            $response->assertStatus(200);
        }
    }
}
