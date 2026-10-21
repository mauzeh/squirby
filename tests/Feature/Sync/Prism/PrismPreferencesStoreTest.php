<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrismPreferencesStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
    }

    public function test_first_post_creates_preferences_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'data' => [
                'theme' => 'dark',
                'default_units' => 'lbs',
                'custom_tags' => ['powerlifting', 'conditioning'],
            ],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/preferences', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure(['status', 'updated_at']);

        $this->assertDatabaseCount('prism_preferences', 1);

        $pref = PrismPreference::first();
        $this->assertEquals('the-cove', $pref->gym_id);
        $this->assertEquals($payload['data'], $pref->data);
        $this->assertEquals('dev-uuid-1', $pref->device_id);
        $this->assertEquals($pref->updated_at->toIso8601String(), $response->json('updated_at'));
    }

    public function test_second_post_overwrites_data_in_place(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/preferences', [
                'data' => ['theme' => 'dark'],
            ])->assertStatus(200);

        $this->assertDatabaseCount('prism_preferences', 1);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/preferences', [
                'data' => ['theme' => 'light', 'compact_mode' => true],
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('prism_preferences', 1);

        $pref = PrismPreference::first();
        $this->assertEquals(['theme' => 'light', 'compact_mode' => true], $pref->data);
        $this->assertEquals('dev-uuid-2', $pref->device_id);
    }

    public function test_unknown_gym_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/nonexistent-gym/preferences', [
                'data' => ['theme' => 'dark'],
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
            ->postJson('/api/sync/prism/the-cove/preferences', [
                'data' => ['theme' => 'dark'],
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_missing_data_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/preferences', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
