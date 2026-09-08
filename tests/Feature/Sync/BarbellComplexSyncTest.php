<?php

namespace Tests\Feature\Sync;

use App\Models\Exercise;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarbellComplexSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_barbell_complex_weight_only_persists_and_restores(): void
    {
        $user = User::factory()->create(['name' => 'complex_tester']);
        $token = $user->createToken('test-device')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Device-Id' => 'device-complex',
        ];

        $payload = [
            'exercise_name' => 'Bear Complex',
            'canonical_name' => 'bear_complex',
            'date' => '2026-09-07',
            'log_type' => 'barbell-complex',
            'weight_unit' => 'lbs',
            'sets' => [
                ['weight' => 135], // weight-only, reps absent
            ],
        ];

        $response = $this->withHeaders($headers)->postJson('/api/sync/logs', $payload);
        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // Assert auto-created exercise has exercise_type = regular
        $exercise = Exercise::where('canonical_name', 'bear_complex')->first();
        $this->assertNotNull($exercise);
        $this->assertEquals('regular', $exercise->exercise_type);
        $this->assertEquals('barbell-complex', $exercise->log_type);

        // Assert lift set persisted weight = 135 and reps = null (not weight:0 or dropped)
        $liftSet = LiftSet::whereHas('liftLog', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();
        $this->assertNotNull($liftSet);
        $this->assertEquals(135, $liftSet->weight);
        $this->assertNull($liftSet->reps);

        // GET /api/sync/restore returns weight with reps null
        $restoreRes = $this->withHeaders($headers)->getJson('/api/sync/restore');
        $restoreRes->assertStatus(200)->assertJson(['status' => 'ok']);

        $logs = $restoreRes->json('logs');
        $restoredLog = collect($logs)->firstWhere('exerciseId', 'bear_complex');
        $this->assertNotNull($restoredLog);
        $this->assertEquals('barbell-complex', $restoredLog['logType']);
        $this->assertEquals(135, $restoredLog['sets'][0]['weight']);
        $this->assertNull($restoredLog['sets'][0]['reps']);
    }

    public function test_sync_barbell_complex_with_reps_round_trips(): void
    {
        $user = User::factory()->create(['name' => 'complex_reps_tester']);
        $token = $user->createToken('test-device')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Device-Id' => 'device-complex-reps',
        ];

        $payload = [
            'exercise_name' => 'Hero Complex',
            'canonical_name' => 'hero_complex',
            'date' => '2026-09-07',
            'log_type' => 'barbell-complex',
            'weight_unit' => 'lbs',
            'sets' => [
                ['weight' => 185, 'reps' => 3], // weight + reps
            ],
        ];

        $response = $this->withHeaders($headers)->postJson('/api/sync/logs', $payload);
        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        $liftSet = LiftSet::whereHas('liftLog', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();
        $this->assertNotNull($liftSet);
        $this->assertEquals(185, $liftSet->weight);
        $this->assertEquals(3, $liftSet->reps);

        $restoreRes = $this->withHeaders($headers)->getJson('/api/sync/restore');
        $restoreRes->assertStatus(200)->assertJson(['status' => 'ok']);

        $logs = $restoreRes->json('logs');
        $restoredLog = collect($logs)->firstWhere('exerciseId', 'hero_complex');
        $this->assertNotNull($restoredLog);
        $this->assertEquals('barbell-complex', $restoredLog['logType']);
        $this->assertEquals(185, $restoredLog['sets'][0]['weight']);
        $this->assertEquals(3, $restoredLog['sets'][0]['reps']);
    }
}
