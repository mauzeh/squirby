<?php

namespace Tests\Unit\Sync\Prism;

use App\Sync\Models\Gym;
use App\Sync\Models\PrismProgram;
use App\Sync\Models\PrismProgramCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrismProgramCellTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;
    private PrismProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::create([
            'id' => 'the-cove',
            'name' => 'The Cove',
        ]);

        $this->program = PrismProgram::create([
            'gym_id' => $this->gym->id,
            'program_uuid' => 'prog-uuid-1',
            'name' => 'Program 1',
            'week_count' => 4,
        ]);
    }

    public function test_program_cell_create_read_update_and_upsert(): void
    {
        // 1. Create persists
        $cell = PrismProgramCell::create([
            'gym_id' => $this->gym->id,
            'program_uuid' => $this->program->program_uuid,
            'cell_id' => 'w1d1',
            'text' => 'Back Squat 3x5 @ 75%',
            'device_id' => 'dev-uuid-123',
        ]);

        $this->assertDatabaseHas('prism_program_cells', [
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-uuid-1',
            'cell_id' => 'w1d1',
            'text' => 'Back Squat 3x5 @ 75%',
            'device_id' => 'dev-uuid-123',
        ]);

        // 2. Read returns row with correct attributes
        $found = PrismProgramCell::where('gym_id', 'the-cove')
            ->where('program_uuid', 'prog-uuid-1')
            ->where('cell_id', 'w1d1')
            ->first();

        $this->assertNotNull($found);
        $this->assertSame('w1d1', $found->cell_id);
        $this->assertSame('Back Squat 3x5 @ 75%', $found->text);

        // 3. Update mutates in place
        $found->update([
            'text' => 'Back Squat 3x5 @ 80%',
        ]);

        $this->assertSame(
            1,
            PrismProgramCell::where('gym_id', 'the-cove')
                ->where('program_uuid', 'prog-uuid-1')
                ->where('cell_id', 'w1d1')
                ->count()
        );
        $this->assertSame('Back Squat 3x5 @ 80%', $found->fresh()->text);

        // 4. Upsert on composite unique key [gym_id, program_uuid, cell_id] updates in place rather than inserting duplicate
        PrismProgramCell::upsert(
            [
                [
                    'gym_id' => 'the-cove',
                    'program_uuid' => 'prog-uuid-1',
                    'cell_id' => 'w1d1',
                    'text' => 'Back Squat 3x5 @ 85%',
                    'device_id' => 'dev-uuid-456',
                ],
            ],
            ['gym_id', 'program_uuid', 'cell_id'],
            ['text', 'device_id']
        );

        $this->assertSame(
            1,
            PrismProgramCell::where('gym_id', 'the-cove')
                ->where('program_uuid', 'prog-uuid-1')
                ->where('cell_id', 'w1d1')
                ->count()
        );

        $upserted = PrismProgramCell::where('gym_id', 'the-cove')
            ->where('program_uuid', 'prog-uuid-1')
            ->where('cell_id', 'w1d1')
            ->first();

        $this->assertSame('Back Squat 3x5 @ 85%', $upserted->text);
        $this->assertSame('dev-uuid-456', $upserted->device_id);
    }
}
