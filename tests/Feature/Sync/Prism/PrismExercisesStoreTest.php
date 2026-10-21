<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismExerciseOverlayRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrismExercisesStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
    }

    public function test_upserts_creates_overlay_rows_with_deleted_at_null(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'upserts' => [
                [
                    'exercise_id' => 'back-squat',
                    'record' => ['custom_notes' => 'Low bar squat form cues', 'category' => 'squat'],
                ],
            ],
            'removals' => [],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/exercises', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure(['status', 'updated_at']);

        $this->assertDatabaseCount('prism_exercise_overlay', 1);

        $record = PrismExerciseOverlayRecord::first();
        $this->assertEquals('the-cove', $record->gym_id);
        $this->assertEquals('back-squat', $record->exercise_id);
        $this->assertEquals(['custom_notes' => 'Low bar squat form cues', 'category' => 'squat'], $record->record);
        $this->assertEquals('dev-uuid-1', $record->device_id);
        $this->assertNull($record->deleted_at);
    }

    public function test_removals_writes_tombstone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Pre-create an exercise overlay
        PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'back-squat',
            'record' => ['notes' => 'existing'],
            'device_id' => 'dev-uuid-old',
            'deleted_at' => null,
        ]);

        $payload = [
            'upserts' => [],
            'removals' => [
                ['exercise_id' => 'back-squat'],
            ],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/exercises', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('prism_exercise_overlay', 1);

        $record = PrismExerciseOverlayRecord::first();
        $this->assertNotNull($record->deleted_at);
        $this->assertNull($record->record);
        $this->assertEquals('dev-uuid-2', $record->device_id);
    }

    public function test_re_upserting_tombstoned_exercise_clears_deleted_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // First remove (tombstone)
        $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/exercises', [
                'upserts' => [],
                'removals' => [
                    ['exercise_id' => 'back-squat'],
                ],
            ])->assertStatus(200);

        $record = PrismExerciseOverlayRecord::first();
        $this->assertNotNull($record->deleted_at);

        // Re-upsert
        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/exercises', [
                'upserts' => [
                    [
                        'exercise_id' => 'back-squat',
                        'record' => ['restored' => true],
                    ],
                ],
                'removals' => [],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('prism_exercise_overlay', 1);

        $record = PrismExerciseOverlayRecord::first();
        $this->assertNull($record->deleted_at);
        $this->assertEquals(['restored' => true], $record->record);
        $this->assertEquals('dev-uuid-2', $record->device_id);
    }

    public function test_unknown_gym_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/nonexistent-gym/exercises', [
                'upserts' => [],
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
            ->postJson('/api/sync/prism/the-cove/exercises', [
                'upserts' => [],
                'removals' => [],
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_missing_upserts_and_removals_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/exercises', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
