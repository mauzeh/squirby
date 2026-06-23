<?php

namespace Tests\Feature\Sync;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Sync\Models\AthleteBlueprint;
use App\Sync\Models\AthletePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_write_and_restore_cycle(): void
    {
        // 1. Register a user
        $regResponse = $this->postJson('/api/sync/register', [
            'email' => 'john@doe.com',
            'password' => 'secret123',
            'name' => 'john_doe',
            'device_id' => 'device-abc',
        ]);

        $regResponse->assertStatus(200)
            ->assertJsonStructure(['status', 'token', 'athlete', 'email'])
            ->assertJson(['status' => 'ok', 'athlete' => 'john_doe', 'email' => 'john@doe.com']);

        $token = $regResponse->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];

        // Verify user was created in DB
        $user = User::where('email', 'john@doe.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('john_doe', $user->name);

        // 2. Store a log (with idempotency key)
        $idempotencyKey = 'some-uuid-key-123';
        $logPayload = [
            'exercise_name' => 'Bench Press',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'note' => 'Strong sets',
            'track' => 'hypertrophy',
            'block_index' => 2,
            'movement_index' => 1,
            'sets' => [
                ['weight' => 135, 'reps' => 8],
                ['weight' => 155, 'reps' => 6],
            ],
        ];

        $logResponse = $this->withHeaders($headers)
            ->postJson('/api/sync/logs', $logPayload + ['idempotency_key' => $idempotencyKey]);

        $logResponse->assertStatus(200)
            ->assertJsonStructure(['status', 'log_id'])
            ->assertJson(['status' => 'ok']);

        $logId = $logResponse->json('log_id');

        // Resend same log with same key -> should return the same log_id and not create another
        $dupResponse = $this->withHeaders($headers)
            ->postJson('/api/sync/logs', $logPayload + ['idempotency_key' => $idempotencyKey]);
        $dupResponse->assertStatus(200)
            ->assertJson(['status' => 'ok', 'log_id' => $logId]);

        $this->assertEquals(1, LiftLog::where('user_id', $user->id)->count());

        // 3. Store blueprint
        $blueprintPayload = [
            'custom_routines' => ['Routine A', 'Routine B'],
            'device_id' => 'should-be-omitted-from-blueprint-data',
        ];

        $bpResponse = $this->withHeaders($headers)
            ->withHeaders(['X-Device-Id' => 'device-abc'])
            ->postJson('/api/sync/blueprint', $blueprintPayload);

        $bpResponse->assertStatus(200)->assertJson(['status' => 'ok']);

        // Verify stored blueprint in DB
        $blueprint = AthleteBlueprint::where('user_id', $user->id)->first();
        $this->assertNotNull($blueprint);
        $this->assertEquals('device-abc', $blueprint->device_id);
        $this->assertEquals(['custom_routines' => ['Routine A', 'Routine B']], $blueprint->blueprint_data);

        // 4. Store preferences
        $prefPayload = [
            'dark_mode' => true,
            'sound_enabled' => false,
            'device_id' => 'should-be-omitted-from-pref-data',
        ];

        $prefResponse = $this->withHeaders($headers)
            ->withHeaders(['X-Device-Id' => 'device-abc'])
            ->postJson('/api/sync/preferences', $prefPayload);

        $prefResponse->assertStatus(200)->assertJson(['status' => 'ok']);

        // Verify stored preference in DB
        $preference = AthletePreference::where('user_id', $user->id)->first();
        $this->assertNotNull($preference);
        $this->assertEquals('device-abc', $preference->device_id);
        $this->assertEquals(['dark_mode' => true, 'sound_enabled' => false], $preference->preferences_data);

        // Seed a PR to verify restore prHistory works
        $exercise = Exercise::where('canonical_name', 'bench_press')->first();
        PersonalRecord::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $logId,
            'pr_type' => 'one_rm',
            'rep_count' => 6,
            'weight' => 155.0,
            'value' => 186.0,
            'achieved_at' => now(),
        ]);

        // 5. Restore user state
        $restoreResponse = $this->withHeaders($headers)->getJson('/api/sync/restore');

        $restoreResponse->assertStatus(200)
            ->assertJsonStructure(['status', 'blueprint', 'preferences', 'logs', 'prHistory'])
            ->assertJson([
                'status' => 'ok',
                'blueprint' => ['custom_routines' => ['Routine A', 'Routine B']],
                'preferences' => ['dark_mode' => true, 'sound_enabled' => false],
            ]);

        $logs = $restoreResponse->json('logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('bench_press', $logs[0]['exerciseId']);
        $this->assertEquals('Bench Press', $logs[0]['exerciseName']);
        $this->assertEquals('barbell', $logs[0]['logType']);
        $this->assertEquals('hypertrophy', $logs[0]['track']);
        $this->assertEquals(2, $logs[0]['blockIndex']);
        $this->assertEquals(1, $logs[0]['movementIndex']);
        $this->assertCount(2, $logs[0]['sets']);
        $this->assertEquals(135, $logs[0]['sets'][0]['weight']);
        $this->assertEquals(8, $logs[0]['sets'][0]['reps']);

        $prHistory = $restoreResponse->json('prHistory');
        $this->assertArrayHasKey('bench_press', $prHistory);
        $this->assertNotEmpty($prHistory['bench_press']);
        $oneRmPr = collect($prHistory['bench_press'])->firstWhere('pr_type', 'one_rm');
        $this->assertNotNull($oneRmPr);
        $this->assertEqualsWithDelta(186.0, $oneRmPr['value'], 0.1);
    }

    public function test_auth_and_error_responses(): void
    {
        // 1. Missing token returns 401
        $this->getJson('/api/sync/restore')->assertStatus(401)->assertJson([
            'status' => 'error',
            'message' => 'Unauthenticated.',
        ]);

        // Create a user and token
        $user = User::factory()->create(['name' => 'billy', 'password' => bcrypt('password123')]);
        $token = $user->createToken('test-device')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        // 2. Validation failure returns 422
        $this->withHeaders($headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Squat',
            // missing other fields
        ])->assertStatus(422)->assertJson([
            'status' => 'error',
        ]);

        // 3. Sets > 100 returns 422
        $hugeSets = array_fill(0, 101, ['weight' => 135, 'reps' => 5]);
        $this->withHeaders($headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Squat',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'sets' => $hugeSets,
        ])->assertStatus(422)->assertJson([
            'status' => 'error',
            'message' => 'The sets field must not have more than 100 items.',
        ]);

        // 4. Deleting a log belonging to another user returns 404
        $otherUser = User::factory()->create();
        $exercise = Exercise::create(['title' => 'Squat', 'canonical_name' => 'squat']);
        $otherLog = LiftLog::create([
            'user_id' => $otherUser->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        $this->withHeaders($headers)->deleteJson("/api/sync/logs/{$otherLog->id}")
            ->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Log not found.',
            ]);

        // 5. Rate limiting returns 429
        // Reset rate limiter cache
        cache()->flush();

        // Hit route 10 times (sync-per-user limit)
        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($headers)->getJson('/api/sync/restore')->assertStatus(200);
        }
        $this->withHeaders($headers)->getJson('/api/sync/restore')
            ->assertStatus(429);
    }

    public function test_delete_own_log_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Device-Id' => 'device-123'];

        // Create a log
        $response = $this->withHeaders($headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Pull Up',
            'date' => '2026-06-15',
            'log_type' => 'bodyweight',
            'weight_unit' => 'lbs',
            'sets' => [
                ['reps' => 10],
                ['reps' => 8],
            ],
        ]);

        $response->assertStatus(200);
        $logId = $response->json('log_id');

        // Delete own log via route model binding
        $this->withHeaders($headers)
            ->deleteJson("/api/sync/logs/{$logId}")
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify soft deleted
        $this->assertSoftDeleted('lift_logs', ['id' => $logId]);
    }
}
