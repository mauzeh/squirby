<?php

namespace Tests\Unit\Sync\Prism;

use App\Sync\Models\Gym;
use App\Sync\Models\PrismProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrismProgramTest extends TestCase
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

    public function test_program_create_read_update_and_upsert(): void
    {
        // 1. Create persists
        $program = PrismProgram::create([
            'gym_id' => $this->gym->id,
            'program_uuid' => 'prog-uuid-1',
            'name' => 'Hypertrophy Block A',
            'week_count' => 6,
            'metadata' => ['tags' => ['hypertrophy', 'upper-lower'], 'coach' => 'Alex'],
            'device_id' => 'dev-uuid-123',
        ]);

        $this->assertDatabaseHas('prism_programs', [
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-uuid-1',
            'name' => 'Hypertrophy Block A',
            'week_count' => 6,
            'device_id' => 'dev-uuid-123',
        ]);

        // 2. Read returns row with array cast hydrated
        $found = PrismProgram::where('gym_id', 'the-cove')
            ->where('program_uuid', 'prog-uuid-1')
            ->first();

        $this->assertNotNull($found);
        $this->assertSame('Hypertrophy Block A', $found->name);
        $this->assertSame(6, $found->week_count);
        $this->assertIsArray($found->metadata);
        $this->assertSame('Alex', $found->metadata['coach']);
        $this->assertSame(['hypertrophy', 'upper-lower'], $found->metadata['tags']);

        // 3. Update mutates in place
        $found->update([
            'name' => 'Hypertrophy Block B',
            'week_count' => 8,
            'metadata' => ['tags' => ['full-body']],
        ]);

        $this->assertSame(1, PrismProgram::where('gym_id', 'the-cove')->where('program_uuid', 'prog-uuid-1')->count());
        $fresh = $found->fresh();
        $this->assertSame('Hypertrophy Block B', $fresh->name);
        $this->assertSame(8, $fresh->week_count);
        $this->assertSame(['tags' => ['full-body']], $fresh->metadata);

        // 4. Upsert on composite unique key [gym_id, program_uuid] updates in place rather than inserting duplicate
        PrismProgram::upsert(
            [
                [
                    'gym_id' => 'the-cove',
                    'program_uuid' => 'prog-uuid-1',
                    'name' => 'Hypertrophy Block Final',
                    'week_count' => 10,
                    'metadata' => json_encode(['version' => 2]),
                    'device_id' => 'dev-uuid-456',
                ],
            ],
            ['gym_id', 'program_uuid'],
            ['name', 'week_count', 'metadata', 'device_id']
        );

        $this->assertSame(1, PrismProgram::where('gym_id', 'the-cove')->where('program_uuid', 'prog-uuid-1')->count());
        $upserted = PrismProgram::where('gym_id', 'the-cove')->where('program_uuid', 'prog-uuid-1')->first();
        $this->assertSame('Hypertrophy Block Final', $upserted->name);
        $this->assertSame(10, $upserted->week_count);
        $this->assertSame(['version' => 2], $upserted->metadata);
        $this->assertSame('dev-uuid-456', $upserted->device_id);
    }
}
