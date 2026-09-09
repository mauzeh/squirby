<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use App\Sync\Models\AthleteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_telemetry_post_stores_event(): void
    {
        $payload = [
            'events' => [
                ['screen' => 'welcome', 'ts' => '2026-09-09T09:00:00Z'],
            ],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'device-uuid-123'])
            ->postJson('/api/sync/telemetry', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('athlete_events', 1);

        $event = AthleteEvent::first();
        $this::assertNull($event->user_id);
        $this::assertEquals('device-uuid-123', $event->device_id);
        $this::assertEquals($payload, $event->event_data);
    }

    public function test_authenticated_telemetry_post_stores_user_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'events' => [
                ['screen' => 'dashboard', 'ts' => '2026-09-09T09:05:00Z'],
            ],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'device-uuid-456'])
            ->postJson('/api/sync/telemetry', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('athlete_events', 1);

        $event = AthleteEvent::first();
        $this::assertEquals($user->id, $event->user_id);
        $this::assertEquals('device-uuid-456', $event->device_id);
        $this::assertEquals($payload, $event->event_data);
    }

    public function test_telemetry_post_is_append_only(): void
    {
        $payload = [
            'events' => [
                ['screen' => 'settings', 'ts' => '2026-09-09T09:10:00Z'],
            ],
        ];

        $headers = ['X-Device-Id' => 'device-uuid-789'];

        $this->withHeaders($headers)->postJson('/api/sync/telemetry', $payload)->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/sync/telemetry', $payload)->assertStatus(200);

        $this->assertDatabaseCount('athlete_events', 2);
    }

    public function test_telemetry_post_rejects_over_cap_events(): void
    {
        $events = array_fill(0, 201, ['screen' => 'welcome', 'ts' => '2026-09-09T09:00:00Z']);

        $response = $this->withHeaders(['X-Device-Id' => 'device-uuid-999'])
            ->postJson('/api/sync/telemetry', ['events' => $events]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('athlete_events', 0);
    }
}
