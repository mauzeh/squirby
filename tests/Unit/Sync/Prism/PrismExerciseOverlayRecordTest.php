<?php

namespace Tests\Unit\Sync\Prism;

use App\Sync\Models\Gym;
use App\Sync\Models\PrismExerciseOverlayRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrismExerciseOverlayRecordTest extends TestCase
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

    public function test_exercise_overlay_create_read_update_and_upsert(): void
    {
        // 1. Create persists
        $overlay = PrismExerciseOverlayRecord::create([
            'gym_id' => $this->gym->id,
            'exercise_id' => 'ex-squat',
            'record' => ['alias' => 'Squat (Barbell)', 'category' => 'Barbell'],
            'device_id' => 'dev-uuid-123',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('prism_exercise_overlay', [
            'gym_id' => 'the-cove',
            'exercise_id' => 'ex-squat',
            'device_id' => 'dev-uuid-123',
            'deleted_at' => null,
        ]);

        // 2. Read returns row with array cast hydrated
        $found = PrismExerciseOverlayRecord::where('gym_id', 'the-cove')
            ->where('exercise_id', 'ex-squat')
            ->first();

        $this->assertNotNull($found);
        $this->assertSame('ex-squat', $found->exercise_id);
        $this->assertIsArray($found->record);
        $this->assertSame('Squat (Barbell)', $found->record['alias']);
        $this->assertNull($found->deleted_at);

        // 3. Update mutates in place (including setting deleted_at tombstone without SoftDeletes trait)
        $tombstoneTime = now();
        $found->update([
            'record' => ['alias' => 'Squat (Low Bar)'],
            'deleted_at' => $tombstoneTime,
        ]);

        $this->assertSame(
            1,
            PrismExerciseOverlayRecord::where('gym_id', 'the-cove')
                ->where('exercise_id', 'ex-squat')
                ->count()
        );

        $fresh = $found->fresh();
        $this->assertSame('Squat (Low Bar)', $fresh->record['alias']);
        $this->assertInstanceOf(Carbon::class, $fresh->deleted_at);

        // Verify plain deleted_at column is NOT scoped out by SoftDeletes (plain query sees it)
        $this->assertNotNull(PrismExerciseOverlayRecord::where('gym_id', 'the-cove')->where('exercise_id', 'ex-squat')->first());

        // 4. Upsert on composite unique key [gym_id, exercise_id] updates in place rather than inserting duplicate
        PrismExerciseOverlayRecord::upsert(
            [
                [
                    'gym_id' => 'the-cove',
                    'exercise_id' => 'ex-squat',
                    'record' => json_encode(['alias' => 'Squat (Restored)']),
                    'device_id' => 'dev-uuid-456',
                    'deleted_at' => null,
                ],
            ],
            ['gym_id', 'exercise_id'],
            ['record', 'device_id', 'deleted_at']
        );

        $this->assertSame(
            1,
            PrismExerciseOverlayRecord::where('gym_id', 'the-cove')
                ->where('exercise_id', 'ex-squat')
                ->count()
        );

        $upserted = PrismExerciseOverlayRecord::where('gym_id', 'the-cove')
            ->where('exercise_id', 'ex-squat')
            ->first();

        $this->assertSame(['alias' => 'Squat (Restored)'], $upserted->record);
        $this->assertSame('dev-uuid-456', $upserted->device_id);
        $this->assertNull($upserted->deleted_at);
    }
}
