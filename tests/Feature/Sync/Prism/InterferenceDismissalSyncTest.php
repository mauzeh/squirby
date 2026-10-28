<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismInterferenceDismissal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterferenceDismissalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
        Gym::create(['id' => 'flagship', 'name' => 'Flagship']);
    }

    public function test_upsert_creates_dismissal_rows_with_deleted_at_null(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'dismissals' => [
                [
                    'resolution_key' => 'prog-uuid-1|w1d1~w1d2|hinge,squat',
                ],
            ],
            'removals' => [],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure(['status', 'updated_at']);

        $this->assertDatabaseCount('prism_interference_dismissals', 1);

        $record = PrismInterferenceDismissal::first();
        $this->assertEquals('the-cove', $record->gym_id);
        $this->assertEquals('prog-uuid-1|w1d1~w1d2|hinge,squat', $record->resolution_key);
        $this->assertEquals('dev-uuid-1', $record->device_id);
        $this->assertNull($record->deleted_at);
    }

    public function test_removals_writes_tombstone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'prog-uuid-1|w1d1~w1d2|hinge,squat',
            'device_id' => 'dev-uuid-old',
            'deleted_at' => null,
        ]);

        $payload = [
            'dismissals' => [],
            'removals' => [
                ['resolution_key' => 'prog-uuid-1|w1d1~w1d2|hinge,squat'],
            ],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure(['status', 'updated_at']);

        $this->assertDatabaseCount('prism_interference_dismissals', 1);

        $record = PrismInterferenceDismissal::first();
        $this->assertNotNull($record->deleted_at);
        $this->assertEquals('dev-uuid-2', $record->device_id);
    }

    public function test_batched_removals_tombstones_all_n_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            PrismInterferenceDismissal::create([
                'gym_id' => 'the-cove',
                'resolution_key' => "key-{$i}",
                'device_id' => 'dev-uuid-old',
                'deleted_at' => null,
            ]);
        }

        $removals = array_map(fn ($i) => ['resolution_key' => "key-{$i}"], range(1, 5));

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-reset'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [],
                'removals' => $removals,
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('prism_interference_dismissals', 5);
        $tombstonedCount = PrismInterferenceDismissal::where('gym_id', 'the-cove')
            ->whereNotNull('deleted_at')
            ->count();
        $this->assertEquals(5, $tombstonedCount);
    }

    public function test_re_upserting_tombstoned_dismissal_clears_deleted_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [],
                'removals' => [
                    ['resolution_key' => 'key-1'],
                ],
            ])->assertStatus(200);

        $record = PrismInterferenceDismissal::first();
        $this->assertNotNull($record->deleted_at);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [
                    ['resolution_key' => 'key-1'],
                ],
                'removals' => [],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('prism_interference_dismissals', 1);
        $record = PrismInterferenceDismissal::first();
        $this->assertNull($record->deleted_at);
        $this->assertEquals('dev-uuid-2', $record->device_id);
    }

    public function test_upsert_and_removal_in_single_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [
                    ['resolution_key' => 'key-active'],
                ],
                'removals' => [
                    ['resolution_key' => 'key-removed'],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('prism_interference_dismissals', 2);

        $active = PrismInterferenceDismissal::where('resolution_key', 'key-active')->first();
        $this->assertNotNull($active);
        $this->assertNull($active->deleted_at);

        $removed = PrismInterferenceDismissal::where('resolution_key', 'key-removed')->first();
        $this->assertNotNull($removed);
        $this->assertNotNull($removed->deleted_at);
    }

    public function test_long_resolution_key_round_trips_intact(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Construct a realistic long resolution key (approx 350-400 chars)
        $longKey = 'prog-550e8400-e29b-41d4-a716-446655440000|w12d4~w12d5|' . str_repeat('movement-pattern-variation-long-name,', 8) . 'final-pattern';
        $this->assertGreaterThan(255, strlen($longKey));
        $this->assertLessThanOrEqual(512, strlen($longKey));

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [
                    ['resolution_key' => $longKey],
                ],
                'removals' => [],
            ]);

        $response->assertStatus(200);

        $record = PrismInterferenceDismissal::where('gym_id', 'the-cove')->first();
        $this->assertEquals($longKey, $record->resolution_key);
    }

    public function test_unknown_gym_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/nonexistent-gym/interference-dismissals', [
                'dismissals' => [],
                'removals' => [],
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Gym not found.',
            ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [],
                'removals' => [],
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_missing_dismissals_and_removals_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
