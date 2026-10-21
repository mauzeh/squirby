<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrismProgramStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
    }

    public function test_program_upsert_creates_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Hypertrophy Block A',
            'week_count' => 6,
            'metadata' => ['author' => 'Coach Dan', 'cycle' => 1],
        ];

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/programs/prog-uuid-123', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure(['status', 'updated_at']);

        $this->assertDatabaseCount('prism_programs', 1);

        $program = PrismProgram::first();
        $this->assertEquals('the-cove', $program->gym_id);
        $this->assertEquals('prog-uuid-123', $program->program_uuid);
        $this->assertEquals('Hypertrophy Block A', $program->name);
        $this->assertEquals(6, $program->week_count);
        $this->assertEquals(['author' => 'Coach Dan', 'cycle' => 1], $program->metadata);
        $this->assertEquals('dev-uuid-1', $program->device_id);
        $this->assertEquals($program->updated_at->toIso8601String(), $response->json('updated_at'));
    }

    public function test_reposting_same_program_uuid_updates_in_place(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/programs/prog-uuid-123', [
                'name' => 'Hypertrophy Block A',
                'week_count' => 6,
                'metadata' => ['author' => 'Coach Dan'],
            ])->assertStatus(200);

        $this->assertDatabaseCount('prism_programs', 1);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-2'])
            ->postJson('/api/sync/prism/the-cove/programs/prog-uuid-123', [
                'name' => 'Hypertrophy Block A - Revised',
                'week_count' => 8,
                'metadata' => ['author' => 'Coach Dan', 'cycle' => 2],
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('prism_programs', 1);

        $program = PrismProgram::first();
        $this->assertEquals('Hypertrophy Block A - Revised', $program->name);
        $this->assertEquals(8, $program->week_count);
        $this->assertEquals('dev-uuid-2', $program->device_id);
    }

    public function test_unknown_gym_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/nonexistent-gym/programs/prog-uuid-123', [
                'name' => 'Hypertrophy Block A',
                'week_count' => 6,
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
            ->postJson('/api/sync/prism/the-cove/programs/prog-uuid-123', [
                'name' => 'Hypertrophy Block A',
                'week_count' => 6,
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_invalid_body_missing_name_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-uuid-1'])
            ->postJson('/api/sync/prism/the-cove/programs/prog-uuid-123', [
                'week_count' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
