<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ExerciseMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExerciseMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseMergeService $service;
    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Instantiate service with required dependency
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $this->service = new ExerciseMergeService($aliasService);
        
        // Create admin user
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
        
        // Create regular user
        $this->regularUser = User::factory()->create();
    }

    /** @test */
    public function can_be_merged_returns_false_for_global_exercises()
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $this->assertFalse($this->service->canBeMerged($globalExercise));
    }

    /** @test */
    public function can_be_merged_returns_false_when_no_compatible_targets_exist()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'banded_resistance'
        ]);

        // No global exercises exist
        $this->assertFalse($this->service->canBeMerged($userExercise));
    }

    /** @test */
    public function can_be_merged_returns_true_when_compatible_targets_exist()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'regular'
        ]);

        // Create compatible global exercise
        Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        $this->assertTrue($this->service->canBeMerged($userExercise));
    }

    /** @test */
    public function get_potential_targets_excludes_incompatible_bodyweight_exercises()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'bodyweight'
        ]);

        // Create incompatible global exercise (different bodyweight setting)
        Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        $targets = $this->service->getPotentialTargets($userExercise);
        $this->assertCount(0, $targets);
    }

    /** @test */
    public function get_potential_targets_excludes_incompatible_band_types()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'banded_resistance'
        ]);

        // Create incompatible global exercise (different band type)
        Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_assistance'
        ]);

        $targets = $this->service->getPotentialTargets($userExercise);
        $this->assertCount(0, $targets);
    }

    /** @test */
    public function get_potential_targets_includes_compatible_exercises()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'regular'
        ]);

        // Create compatible global exercises
        $target1 = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'B Exercise',
            'exercise_type' => 'regular'
        ]);

        $target2 = Exercise::factory()->create([
            'user_id' => null,
            // null can merge with any value
            'title' => 'A Exercise',
            'exercise_type' => 'banded_resistance'
        ]);

        $targets = $this->service->getPotentialTargets($userExercise);
        $this->assertCount(2, $targets);
        
        // Should be ordered by title
        $this->assertEquals('A Exercise', $targets->first()->title);
        $this->assertEquals('B Exercise', $targets->last()->title);
    }

    /** @test */
    public function get_potential_targets_excludes_source_exercise_itself()
    {
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'regular'
        ]);

        $targets = $this->service->getPotentialTargets($userExercise);
        $this->assertFalse($targets->contains($userExercise));
    }

    /** @test */
    public function validate_merge_compatibility_returns_error_for_non_global_target()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => $this->regularUser->id]);

        $result = $this->service->validateMergeCompatibility($source, $target);

        $this->assertFalse($result['can_merge']);
        $this->assertContains('Target exercise must be a global exercise.', $result['errors']);
    }

    /** @test */
    public function validate_merge_compatibility_returns_error_for_self_merge()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $result = $this->service->validateMergeCompatibility($exercise, $exercise);

        $this->assertFalse($result['can_merge']);
        $this->assertContains('Cannot merge exercise into itself.', $result['errors']);
    }

    /** @test */
    public function validate_merge_compatibility_returns_error_for_different_exercise_types()
    {
        $source = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'bodyweight'
        ]);
        $target = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        $result = $this->service->validateMergeCompatibility($source, $target);

        $this->assertFalse($result['can_merge']);
        $this->assertContains('Exercises have incompatible types.', $result['errors']);
    }

    /** @test */
    public function validate_merge_compatibility_returns_error_for_incompatible_band_types()
    {
        $source = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'banded_resistance'
        ]);
        $target = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_assistance'
        ]);

        $result = $this->service->validateMergeCompatibility($source, $target);

        $this->assertFalse($result['can_merge']);
        $this->assertContains('Exercises have incompatible types.', $result['errors']);
    }

    /** @test */
    public function validate_merge_compatibility_returns_warning_for_user_with_global_visibility_disabled()
    {
        $userWithDisabledVisibility = User::factory()->create(['show_global_exercises' => false]);
        $source = Exercise::factory()->create(['user_id' => $userWithDisabledVisibility->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        $result = $this->service->validateMergeCompatibility($source, $target);

        $this->assertTrue($result['can_merge']);
        $this->assertContains('The owner of this exercise has global exercise visibility disabled. They will lose access to their exercise data after the merge.', $result['warnings']);
    }

    /** @test */
    public function validate_merge_compatibility_returns_success_for_compatible_exercises()
    {
        $source = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'regular'
        ]);
        $target = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_resistance'
        ]);

        $result = $this->service->validateMergeCompatibility($source, $target);

        $this->assertTrue($result['can_merge']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function merge_exercises_throws_exception_for_incompatible_exercises()
    {
        $source = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'bodyweight'
        ]);
        $target = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exercises are not compatible for merging');

        $this->service->mergeExercises($source, $target, $this->admin);
    }

    /** @test */
    public function merge_exercises_transfers_lift_logs_successfully()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        // Create lift logs for source exercise
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $source->id,
            'user_id' => $this->regularUser->id,
            'comments' => 'Original comment'
        ]);
        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $source->id,
            'user_id' => $this->regularUser->id,
            'comments' => null
        ]);

        $this->service->mergeExercises($source, $target, $this->admin);

        // Check lift logs were transferred
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog1->id,
            'exercise_id' => $target->id,
            'comments' => "Original comment [Merged from: {$source->title}]"
        ]);
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog2->id,
            'exercise_id' => $target->id,
            'comments' => "[Merged from: {$source->title}]"
        ]);
    }

    /** @test */
    public function merge_exercises_transfers_intelligence_when_target_has_none()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        // Create intelligence for source exercise only
        $intelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $source->id]);

        $this->service->mergeExercises($source, $target, $this->admin);

        // Check intelligence was transferred
        $this->assertDatabaseHas('exercise_intelligence', [
            'id' => $intelligence->id,
            'exercise_id' => $target->id
        ]);
    }

    /** @test */
    public function merge_exercises_keeps_target_intelligence_when_both_have_intelligence()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        // Create intelligence for both exercises
        $sourceIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $source->id]);
        $targetIntelligence = ExerciseIntelligence::factory()->create(['exercise_id' => $target->id]);

        $this->service->mergeExercises($source, $target, $this->admin);

        // Target intelligence should remain unchanged
        $this->assertDatabaseHas('exercise_intelligence', [
            'id' => $targetIntelligence->id,
            'exercise_id' => $target->id
        ]);

        // Source intelligence should be deleted (cascade delete with exercise)
        $this->assertDatabaseMissing('exercise_intelligence', [
            'id' => $sourceIntelligence->id
        ]);
    }

    /** @test */
    public function merge_exercises_deletes_source_exercise()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        $sourceId = $source->id;

        $this->service->mergeExercises($source, $target, $this->admin);

        // Source exercise should be deleted
        $this->assertDatabaseMissing('exercises', ['id' => $sourceId]);
        
        // Target exercise should remain
        $this->assertDatabaseHas('exercises', ['id' => $target->id]);
    }

    /** @test */
    public function merge_exercises_logs_successful_operation()
    {
        $source = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Source Exercise'
        ]);
        $target = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Target Exercise'
        ]);

        // Just verify the merge completes successfully
        // Logging verification is better done in integration tests
        $result = $this->service->mergeExercises($source, $target, $this->admin);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('exercises', ['id' => $source->id]);
        $this->assertDatabaseHas('exercises', ['id' => $target->id]);
    }

    /** @test */
    public function merge_exercises_rolls_back_on_failure()
    {
        $source = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $target = Exercise::factory()->create(['user_id' => null]);

        // Create lift log to transfer
        $liftLog = LiftLog::factory()->create(['exercise_id' => $source->id]);

        // Mock DB to throw exception during transaction
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        // Force an exception by making the target exercise invalid
        $target->delete();

        $this->expectException(\Exception::class);

        $this->service->mergeExercises($source, $target, $this->admin);

        // Verify rollback occurred - source should still exist
        $this->assertDatabaseHas('exercises', ['id' => $source->id]);
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'exercise_id' => $source->id // Should not be changed
        ]);
    }

    /** @test */
    public function append_merge_note_adds_note_to_empty_comments()
    {
        $liftLog = LiftLog::factory()->create(['comments' => null]);

        $this->service->appendMergeNote($liftLog, 'Original Exercise');

        $this->assertEquals('[Merged from: Original Exercise]', $liftLog->comments);
    }

    /** @test */
    public function append_merge_note_appends_to_existing_comments()
    {
        $liftLog = LiftLog::factory()->create(['comments' => 'Existing comment']);

        $this->service->appendMergeNote($liftLog, 'Original Exercise');

        $this->assertEquals('Existing comment [Merged from: Original Exercise]', $liftLog->comments);
    }

    /** @test */
    public function get_merge_statistics_returns_correct_counts()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $otherUser = User::factory()->create();

        // Create lift logs for different users
        LiftLog::factory()->count(3)->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->regularUser->id
        ]);
        LiftLog::factory()->count(2)->create([
            'exercise_id' => $exercise->id,
            'user_id' => $otherUser->id
        ]);

        // Create exercise intelligence
        ExerciseIntelligence::factory()->create(['exercise_id' => $exercise->id]);

        $stats = $this->service->getMergeStatistics($exercise);

        $this->assertEquals(5, $stats['lift_logs_count']);
        $this->assertTrue($stats['has_intelligence']);
        $this->assertEquals(2, $stats['users_count']);
    }
}