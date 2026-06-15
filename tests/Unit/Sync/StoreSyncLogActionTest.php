<?php

namespace Tests\Unit\Sync;

use App\Events\LiftLogCompleted;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Sync\Actions\StoreSyncLogAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StoreSyncLogActionTest extends TestCase
{
    use RefreshDatabase;

    private StoreSyncLogAction $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(StoreSyncLogAction::class);
        $this->user = User::factory()->create();
    }

    public function test_log_and_sets_creation_with_event_dispatch(): void
    {
        Event::fake();

        $payload = [
            'exercise_name' => 'Bench Press',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'note' => 'Felt good',
            'track' => 'strength',
            'block_index' => 1,
            'movement_index' => 2,
            'sets' => [
                ['weight' => 135, 'reps' => 5],
                ['weight' => 185, 'reps' => 5],
            ],
        ];

        $log = $this->action->execute($this->user, $payload, 'device-123');

        $this->assertInstanceOf(LiftLog::class, $log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('Felt good', $log->comments);
        $this->assertEquals('barbell', $log->log_type);
        $this->assertEquals('device-123', $log->device_id);
        $this->assertEquals('sync', $log->source);
        $this->assertEquals('strength', $log->track);
        $this->assertEquals(1, $log->block_index);
        $this->assertEquals(2, $log->movement_index);

        // Verify sets
        $this->assertCount(2, $log->liftSets);
        $this->assertEquals(135, $log->liftSets[0]->weight);
        $this->assertEquals(5, $log->liftSets[0]->reps);
        $this->assertEquals('lbs', $log->liftSets[0]->unit);

        // Verify exercise auto-created or resolved
        $this->assertEquals('bench_press', $log->exercise->canonical_name);

        Event::assertDispatched(LiftLogCompleted::class, function ($event) use ($log) {
            return $event->liftLog->id === $log->id;
        });
    }

    public function test_idempotency_key_prevents_duplicate_creation(): void
    {
        Event::fake();

        $key = 'unique-idempotency-key';
        $payload = [
            'exercise_name' => 'Deadlift',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'idempotency_key' => $key,
            'sets' => [
                ['weight' => 225, 'reps' => 5],
            ],
        ];

        // First execution creates
        $log1 = $this->action->execute($this->user, $payload, 'device-123');
        $this->assertEquals($key, $log1->idempotency_key);

        // Second execution with same key returns the same log
        $log2 = $this->action->execute($this->user, $payload, 'device-123');
        $this->assertEquals($log1->id, $log2->id);

        // Verify only 1 log exists in database
        $this->assertEquals(1, LiftLog::where('user_id', $this->user->id)->count());
    }

    public function test_missing_idempotency_key_always_creates(): void
    {
        $payload = [
            'exercise_name' => 'Squat',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'sets' => [
                ['weight' => 225, 'reps' => 5],
            ],
        ];

        $log1 = $this->action->execute($this->user, $payload, 'device-123');
        $log2 = $this->action->execute($this->user, $payload, 'device-123');

        $this->assertNotEquals($log1->id, $log2->id);
        $this->assertEquals(2, LiftLog::where('user_id', $this->user->id)->count());
    }
}
