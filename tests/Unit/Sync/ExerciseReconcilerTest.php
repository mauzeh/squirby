<?php

namespace Tests\Unit\Sync;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use App\Models\User;
use App\Sync\Services\ExerciseReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private string $tempFilename = 'test-reconcile-temp.json';
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = database_path("changesets/exercises/{$this->tempFilename}");
        // Create changesets directory if it doesn't exist
        @mkdir(dirname($this->tempPath), 0755, true);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            @unlink($this->tempPath);
        }
        parent::tearDown();
    }

    private function writeChangeset(array $operations): void
    {
        $content = [
            'generated' => now()->toIso8601String(),
            'source' => 'test-source',
            'operationCount' => count($operations),
            'operations' => $operations
        ];
        file_put_contents($this->tempPath, json_encode($content, JSON_PRETTY_PRINT));
    }

    public function test_apply_and_rollback_rename(): void
    {
        $exercise = Exercise::create([
            'canonical_name' => 'test_ex',
            'title' => 'Test Exercise',
            'exercise_type' => 'regular',
            'user_id' => null
        ]);

        $this->writeChangeset([
            [
                'action' => 'rename',
                'canonical_name' => 'test_ex',
                'old_title' => 'Test Exercise',
                'new_title' => 'Test Exercise Renamed',
                'reason' => 'Testing rename'
            ]
        ]);

        // Apply
        ExerciseReconciler::apply($this->tempFilename);
        $this->assertEquals('Test Exercise Renamed', $exercise->refresh()->title);

        // Rollback
        ExerciseReconciler::rollback($this->tempFilename);
        $this->assertEquals('Test Exercise', $exercise->refresh()->title);
    }

    public function test_apply_and_rollback_create_alias(): void
    {
        $exercise = Exercise::create([
            'canonical_name' => 'test_ex',
            'title' => 'Test Exercise',
            'exercise_type' => 'regular',
            'user_id' => null
        ]);

        $this->writeChangeset([
            [
                'action' => 'create_alias',
                'canonical_name' => 'test_ex',
                'alias_name' => 'Test Ex Alias',
                'reason' => 'Testing create_alias'
            ]
        ]);

        // Apply
        ExerciseReconciler::apply($this->tempFilename);
        $aliasExists = ExerciseAlias::where('exercise_id', $exercise->id)
            ->where('alias_name', 'Test Ex Alias')
            ->exists();
        $this->assertTrue($aliasExists);

        // Rollback
        ExerciseReconciler::rollback($this->tempFilename);
        $aliasExists = ExerciseAlias::where('exercise_id', $exercise->id)
            ->where('alias_name', 'Test Ex Alias')
            ->exists();
        $this->assertFalse($aliasExists);
    }

    public function test_apply_and_rollback_rename_canonical(): void
    {
        $exercise = Exercise::create([
            'canonical_name' => 'test_ex_old',
            'title' => 'Test Exercise Old',
            'exercise_type' => 'regular',
            'user_id' => null
        ]);

        $this->writeChangeset([
            [
                'action' => 'rename_canonical',
                'old_canonical' => 'test_ex_old',
                'new_canonical' => 'test_ex_new',
                'old_title' => 'Test Exercise Old',
                'new_title' => 'Test Exercise New',
                'reason' => 'Testing rename_canonical'
            ]
        ]);

        // Apply
        ExerciseReconciler::apply($this->tempFilename);
        $exercise->refresh();
        $this->assertEquals('test_ex_new', $exercise->canonical_name);
        $this->assertEquals('Test Exercise New', $exercise->title);

        // Alias should be created automatically for the old canonical name
        $aliasExists = ExerciseAlias::where('exercise_id', $exercise->id)
            ->where('alias_name', 'test_ex_old')
            ->exists();
        $this->assertTrue($aliasExists);

        // Rollback
        ExerciseReconciler::rollback($this->tempFilename);
        $exercise->refresh();
        $this->assertEquals('test_ex_old', $exercise->canonical_name);
        $this->assertEquals('Test Exercise Old', $exercise->title);

        $aliasExists = ExerciseAlias::where('exercise_id', $exercise->id)
            ->where('alias_name', 'test_ex_old')
            ->exists();
        $this->assertFalse($aliasExists);
    }

    public function test_apply_and_rollback_add_exercise(): void
    {
        $this->writeChangeset([
            [
                'action' => 'add_exercise',
                'canonical_name' => 'test_add',
                'title' => 'Test Add',
                'exercise_type' => 'bodyweight',
                'log_type' => 'bodyweight-reps',
                'reason' => 'Testing add_exercise'
            ]
        ]);

        // Apply
        ExerciseReconciler::apply($this->tempFilename);
        $exercise = Exercise::where('canonical_name', 'test_add')->whereNull('user_id')->first();
        $this->assertNotNull($exercise);
        $this->assertEquals('Test Add', $exercise->title);
        $this->assertEquals('bodyweight', $exercise->exercise_type);
        $this->assertEquals('bodyweight-reps', $exercise->log_type);

        // Rollback
        ExerciseReconciler::rollback($this->tempFilename);
        $exercise = Exercise::where('canonical_name', 'test_add')->whereNull('user_id')->first();
        $this->assertNull($exercise);
    }

    public function test_rollback_add_exercise_safety_with_lift_logs(): void
    {
        $this->writeChangeset([
            [
                'action' => 'add_exercise',
                'canonical_name' => 'test_add',
                'title' => 'Test Add',
                'exercise_type' => 'bodyweight',
                'log_type' => 'bodyweight-reps',
                'reason' => 'Testing add_exercise'
            ]
        ]);

        // Apply
        ExerciseReconciler::apply($this->tempFilename);
        $exercise = Exercise::where('canonical_name', 'test_add')->whereNull('user_id')->first();
        $this->assertNotNull($exercise);

        // Create a lift log for the exercise
        $user = User::factory()->create();
        LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => '2026-06-21',
            'log_type' => 'bodyweight-reps',
            'source' => 'sync',
            'device_id' => 'device-1'
        ]);

        // Rollback should not delete the exercise
        ExerciseReconciler::rollback($this->tempFilename);
        $exercise = Exercise::where('canonical_name', 'test_add')->whereNull('user_id')->first();
        $this->assertNotNull($exercise); // Safe!
    }

    public function test_idempotency(): void
    {
        $exercise = Exercise::create([
            'canonical_name' => 'test_ex',
            'title' => 'Test Exercise',
            'exercise_type' => 'regular',
            'user_id' => null
        ]);

        $this->writeChangeset([
            [
                'action' => 'create_alias',
                'canonical_name' => 'test_ex',
                'alias_name' => 'Test Ex Alias',
                'reason' => 'Testing idempotency'
            ]
        ]);

        // Apply first time
        ExerciseReconciler::apply($this->tempFilename);
        // Apply second time
        ExerciseReconciler::apply($this->tempFilename);

        $aliasesCount = ExerciseAlias::where('exercise_id', $exercise->id)
            ->where('alias_name', 'Test Ex Alias')
            ->count();
        $this->assertEquals(1, $aliasesCount);
    }

    public function test_transaction_integrity_on_error(): void
    {
        $exercise = Exercise::create([
            'canonical_name' => 'test_ex',
            'title' => 'Test Exercise',
            'exercise_type' => 'regular',
            'user_id' => null
        ]);

        $this->writeChangeset([
            [
                'action' => 'rename',
                'canonical_name' => 'test_ex',
                'old_title' => 'Test Exercise',
                'new_title' => 'Test Exercise Renamed',
                'reason' => 'Testing transaction'
            ],
            [
                'action' => 'unknown_invalid_action',
                'reason' => 'Testing transaction fail'
            ]
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            ExerciseReconciler::apply($this->tempFilename);
        } finally {
            // Assert rename was rolled back
            $this->assertEquals('Test Exercise', $exercise->refresh()->title);
        }
    }
}
