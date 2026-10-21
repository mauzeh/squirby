<?php

namespace Tests\Unit\Sync\Prism;

use App\Sync\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_create_read_update_and_upsert(): void
    {
        // 1. Create persists
        $gym = Gym::create([
            'id' => 'the-cove',
            'name' => 'The Cove',
        ]);

        $this->assertDatabaseHas('gyms', [
            'id' => 'the-cove',
            'name' => 'The Cove',
        ]);

        // 2. Read returns row with correct attributes
        $found = Gym::find('the-cove');
        $this->assertNotNull($found);
        $this->assertSame('the-cove', $found->id);
        $this->assertSame('The Cove', $found->name);

        // 3. Update mutates in place
        $found->update(['name' => 'The Cove Updated']);
        $this->assertDatabaseHas('gyms', [
            'id' => 'the-cove',
            'name' => 'The Cove Updated',
        ]);
        $this->assertSame(1, Gym::where('id', 'the-cove')->count());

        // 4. Upsert updates in place rather than inserting duplicate
        Gym::upsert(
            [
                ['id' => 'the-cove', 'name' => 'The Cove Upserted'],
            ],
            ['id'],
            ['name']
        );

        $this->assertSame(1, Gym::where('id', 'the-cove')->count());
        $this->assertSame('The Cove Upserted', Gym::find('the-cove')?->name);
    }
}
