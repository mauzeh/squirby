<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCascadeSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_soft_delete_cascades_to_associated_data()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create associated data
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $exerciseAlias = ExerciseAlias::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        // Create a global exercise that should NOT be deleted
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // Verify data exists before deletion
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $exerciseAlias->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_logs', ['id' => $liftLog->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);
        
        // Soft delete the user
        $user->delete();
        
        // Verify user and associated data are soft deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
        $this->assertSoftDeleted('exercise_aliases', ['id' => $exerciseAlias->id]);
        $this->assertSoftDeleted('lift_logs', ['id' => $liftLog->id]);
        
        // Verify global exercise is NOT deleted
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);
    }

    public function test_user_restore_cascades_to_associated_data()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create associated data
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $exerciseAlias = ExerciseAlias::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        // Soft delete the user (which cascades to associated data)
        $user->delete();
        
        // Verify everything is soft deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
        $this->assertSoftDeleted('exercise_aliases', ['id' => $exerciseAlias->id]);
        $this->assertSoftDeleted('lift_logs', ['id' => $liftLog->id]);
        
        // Restore the user
        $user->restore();
        
        // Verify user and associated data are restored
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $exerciseAlias->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_logs', ['id' => $liftLog->id, 'deleted_at' => null]);
    }

    public function test_user_soft_delete_only_affects_user_exercises_not_global()
    {
        // Create two users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create exercises for each user and a global exercise
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id]);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // Soft delete user1
        $user1->delete();
        
        // Verify only user1's exercise is deleted
        $this->assertSoftDeleted('exercises', ['id' => $user1Exercise->id]);
        $this->assertDatabaseHas('exercises', ['id' => $user2Exercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);
    }

    public function test_user_soft_delete_handles_empty_relationships()
    {
        // Create a user with no associated data
        $user = User::factory()->create();
        
        // Soft delete should not fail
        $user->delete();
        
        // Verify user is soft deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_restore_handles_partially_deleted_data()
    {
        // Create a user with associated data
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        // Manually soft delete only some associated data (simulating partial deletion)
        $user->delete(); // This will cascade delete everything
        
        // Manually restore the exercise (simulating partial restoration)
        $exercise->restore();
        
        // Now restore the user
        $user->restore();
        
        // Verify everything is restored
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_logs', ['id' => $liftLog->id, 'deleted_at' => null]);
    }

    public function test_user_soft_delete_preserves_other_users_data()
    {
        // Create two users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create a global exercise
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // Both users have lift logs for the global exercise
        $user1LiftLog = LiftLog::factory()->create(['user_id' => $user1->id, 'exercise_id' => $globalExercise->id]);
        $user2LiftLog = LiftLog::factory()->create(['user_id' => $user2->id, 'exercise_id' => $globalExercise->id]);
        
        // Soft delete user1
        $user1->delete();
        
        // Verify user1's lift log is deleted but user2's remains
        $this->assertSoftDeleted('lift_logs', ['id' => $user1LiftLog->id]);
        $this->assertDatabaseHas('lift_logs', ['id' => $user2LiftLog->id, 'deleted_at' => null]);
        
        // Verify global exercise remains
        $this->assertDatabaseHas('exercises', ['id' => $globalExercise->id, 'deleted_at' => null]);
    }
}