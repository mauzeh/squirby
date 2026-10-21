<?php

namespace Tests\Unit\Sync\Prism;

use App\Sync\Models\Gym;
use App\Sync\Models\PrismPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrismPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::create([
            'id' => 'the-cove',
            'name' => 'The Cove',
        ]);
    }

    public function test_preference_create_read_update_and_upsert(): void
    {
        // 1. Create persists
        $preference = PrismPreference::create([
            'gym_id' => $this->gym->id,
            'data' => ['theme' => 'dark', 'show_warmups' => true],
            'device_id' => 'dev-uuid-123',
        ]);

        $this->assertDatabaseHas('prism_preferences', [
            'gym_id' => 'the-cove',
            'device_id' => 'dev-uuid-123',
        ]);

        // 2. Read returns row with array cast hydrated
        $found = PrismPreference::where('gym_id', 'the-cove')->first();

        $this->assertNotNull($found);
        $this->assertSame('the-cove', $found->gym_id);
        $this->assertIsArray($found->data);
        $this->assertSame('dark', $found->data['theme']);
        $this->assertTrue($found->data['show_warmups']);

        // 3. Update mutates in place
        $found->update([
            'data' => ['theme' => 'light', 'show_warmups' => false],
        ]);

        $this->assertSame(1, PrismPreference::where('gym_id', 'the-cove')->count());
        $fresh = $found->fresh();
        $this->assertSame('light', $fresh->data['theme']);
        $this->assertFalse($fresh->data['show_warmups']);

        // 4. Upsert on unique key [gym_id] updates in place rather than inserting duplicate
        PrismPreference::upsert(
            [
                [
                    'gym_id' => 'the-cove',
                    'data' => json_encode(['theme' => 'system', 'auto_advance' => true]),
                    'device_id' => 'dev-uuid-456',
                ],
            ],
            ['gym_id'],
            ['data', 'device_id']
        );

        $this->assertSame(1, PrismPreference::where('gym_id', 'the-cove')->count());

        $upserted = PrismPreference::where('gym_id', 'the-cove')->first();
        $this->assertSame(['theme' => 'system', 'auto_advance' => true], $upserted->data);
        $this->assertSame('dev-uuid-456', $upserted->device_id);
    }
}
