<?php

namespace Tests\Unit\Sync;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Sync\Actions\DeleteSyncLogAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteSyncLogActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteSyncLogAction $action;
    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteSyncLogAction();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::create([
            'title' => 'Squat',
            'canonical_name' => 'squat',
            'exercise_type' => 'regular',
        ]);
    }

    public function test_cascading_soft_delete(): void
    {
        $log = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now(),
        ]);

        $set = $log->liftSets()->create([
            'reps' => 5,
            'weight' => 135,
            'unit' => 'lbs',
        ]);

        $this->action->execute($this->user, $log);

        // Verify log is soft deleted
        $this->assertSoftDeleted('lift_logs', ['id' => $log->id]);

        // Verify set is soft deleted
        $this->assertSoftDeleted('lift_sets', ['id' => $set->id]);
    }

    public function test_ownership_check_prevents_deletion(): void
    {
        $otherUser = User::factory()->create();
        $log = LiftLog::create([
            'user_id' => $otherUser->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Log not found.');

        try {
            $this->action->execute($this->user, $log);
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            throw $e;
        }
    }
}
