<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismInterferenceDismissal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterferenceDismissalChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
        Gym::create(['id' => 'flagship', 'name' => 'Flagship']);
    }

    public function test_changes_includes_dismissals_section_on_cold_start(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t = Carbon::parse('2026-09-01 12:00:00', $tz);

        $dismissal = PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'prog-1|w1d1~w1d2|hinge,squat',
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismInterferenceDismissal::where('id', $dismissal->id)->update(['updated_at' => $t]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'cursor', 'programs', 'cells', 'exercises', 'preferences', 'dismissals']);

        $dismissals = $response->json('dismissals');
        $this->assertCount(1, $dismissals);
        $this->assertEquals([
            'resolution_key' => 'prog-1|w1d1~w1d2|hinge,squat',
            'deleted_at' => null,
            'updated_at' => $t->toIso8601String(),
        ], $dismissals[0]);
    }

    public function test_changes_since_filters_dismissals(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $tBefore = Carbon::parse('2026-09-01 10:00:00', $tz);
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $tAfter = Carbon::parse('2026-09-01 14:00:00', $tz);

        $d1 = PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'key-before',
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismInterferenceDismissal::where('id', $d1->id)->update(['updated_at' => $tBefore]);

        $d2 = PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'key-after',
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismInterferenceDismissal::where('id', $d2->id)->update(['updated_at' => $tAfter]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));

        $response->assertStatus(200);
        $dismissals = $response->json('dismissals');
        $this->assertCount(1, $dismissals);
        $this->assertEquals('key-after', $dismissals[0]['resolution_key']);
    }

    public function test_dismissal_only_change_advances_changes_cursor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t1 = Carbon::parse('2026-09-01 10:00:00', $tz);

        // Cold start with initial dismissal
        $d1 = PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'key-initial',
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismInterferenceDismissal::where('id', $d1->id)->update(['updated_at' => $t1]);

        $res1 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $cursor1 = $res1->json('cursor');
        $this->assertEquals($t1->toIso8601String(), $cursor1);

        // Write a new dismissal stamped at a later time
        $t2 = Carbon::parse('2026-09-01 14:00:00', $tz);
        Carbon::setTestNow($t2);

        $resWrite = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [
                    ['resolution_key' => 'key-second'],
                ],
                'removals' => [],
            ]);
        $resWrite->assertStatus(200);

        // Fetch /changes with since at or after cursor1 (t2 > t1)
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $res2 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));
        $cursor2 = $res2->json('cursor');

        $this->assertTrue(Carbon::parse($cursor2)->gt(Carbon::parse($cursor1)));
        $dismissals = $res2->json('dismissals');
        $this->assertCount(1, $dismissals);
        $this->assertEquals('key-second', $dismissals[0]['resolution_key']);

        Carbon::setTestNow();
    }

    public function test_tombstone_only_change_advances_changes_cursor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t1 = Carbon::parse('2026-09-01 10:00:00', $tz);

        $d1 = PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'key-to-tombstone',
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismInterferenceDismissal::where('id', $d1->id)->update(['updated_at' => $t1]);

        $res1 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $cursor1 = $res1->json('cursor');

        // Tombstone via removals write endpoint at later time
        $t2 = Carbon::parse('2026-09-01 14:00:00', $tz);
        Carbon::setTestNow($t2);

        $resWrite = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->postJson('/api/sync/prism/the-cove/interference-dismissals', [
                'dismissals' => [],
                'removals' => [
                    ['resolution_key' => 'key-to-tombstone'],
                ],
            ]);
        $resWrite->assertStatus(200);

        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $res2 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));
        $cursor2 = $res2->json('cursor');

        $this->assertTrue(Carbon::parse($cursor2)->gt(Carbon::parse($cursor1)));
        $dismissals = $res2->json('dismissals');
        $this->assertCount(1, $dismissals);
        $this->assertEquals('key-to-tombstone', $dismissals[0]['resolution_key']);
        $this->assertNotNull($dismissals[0]['deleted_at']);

        Carbon::setTestNow();
    }

    public function test_cross_gym_isolation_cove_dismissals_never_appear_in_flagship(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Cove has a dismissal
        PrismInterferenceDismissal::create([
            'gym_id' => 'the-cove',
            'resolution_key' => 'cove-secret-key',
            'device_id' => 'dev-cove',
            'deleted_at' => null,
        ]);

        // Flagship has a different dismissal
        PrismInterferenceDismissal::create([
            'gym_id' => 'flagship',
            'resolution_key' => 'flagship-key',
            'device_id' => 'dev-flagship',
            'deleted_at' => null,
        ]);

        // Fetch Cove
        $responseCove = $this->withHeaders(['X-Device-Id' => 'dev-cove'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $responseCove->assertStatus(200);
        $coveDismissals = collect($responseCove->json('dismissals'))->pluck('resolution_key')->all();
        $this->assertContains('cove-secret-key', $coveDismissals);
        $this->assertNotContains('flagship-key', $coveDismissals);

        // Fetch Flagship
        $responseFlagship = $this->withHeaders(['X-Device-Id' => 'dev-flagship'])
            ->getJson('/api/sync/prism/flagship/changes');
        $responseFlagship->assertStatus(200);
        $flagshipDismissals = collect($responseFlagship->json('dismissals'))->pluck('resolution_key')->all();
        $this->assertContains('flagship-key', $flagshipDismissals);
        $this->assertNotContains('cove-secret-key', $flagshipDismissals);
    }
}
