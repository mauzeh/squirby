<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_exercise_can_be_created_with_is_bodyweight_attribute()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'description' => 'A bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertTrue($exercise->is_bodyweight);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function an_exercise_defaults_to_not_bodyweight()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'description' => 'A weighted exercise',
        ]);

        $this->assertFalse($exercise->is_bodyweight);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => false,
        ]);
    }

    /** @test */
    public function scope_global_returns_only_exercises_with_null_user_id()
    {
        // Create global exercises (user_id = null)
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        
        // Create user-specific exercises
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $globalExercises = Exercise::global()->get();

        $this->assertCount(2, $globalExercises);
        $this->assertTrue($globalExercises->contains($globalExercise1));
        $this->assertTrue($globalExercises->contains($globalExercise2));
        $this->assertFalse($globalExercises->contains($userExercise));
    }

    /** @test */
    public function scope_user_specific_returns_only_exercises_for_given_user()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises for different users
        $user1Exercise1 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise 1']);
        $user1Exercise2 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise 2']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);

        $user1Exercises = Exercise::userSpecific($user1->id)->get();

        $this->assertCount(2, $user1Exercises);
        $this->assertTrue($user1Exercises->contains($user1Exercise1));
        $this->assertTrue($user1Exercises->contains($user1Exercise2));
        $this->assertFalse($user1Exercises->contains($user2Exercise));
        $this->assertFalse($user1Exercises->contains($globalExercise));
    }

    /** @test */
    public function scope_available_to_user_returns_global_and_user_exercises()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);

        $availableToUser1 = Exercise::availableToUser($user1->id)->get();

        $this->assertCount(3, $availableToUser1);
        $this->assertTrue($availableToUser1->contains($globalExercise1));
        $this->assertTrue($availableToUser1->contains($globalExercise2));
        $this->assertTrue($availableToUser1->contains($user1Exercise));
        $this->assertFalse($availableToUser1->contains($user2Exercise));
    }

    /** @test */
    public function is_global_returns_true_when_user_id_is_null()
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $userExercise = Exercise::factory()->create(['user_id' => \App\Models\User::factory()->create()->id]);

        $this->assertTrue($globalExercise->isGlobal());
        $this->assertFalse($userExercise->isGlobal());
    }

    /** @test */
    public function can_be_edited_by_returns_true_for_admin_on_global_exercise()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularUser = \App\Models\User::factory()->create();
        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        $this->assertTrue($globalExercise->canBeEditedBy($admin));
        $this->assertFalse($globalExercise->canBeEditedBy($regularUser));
    }

    /** @test */
    public function can_be_edited_by_returns_true_for_owner_on_user_exercise()
    {
        $owner = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($userExercise->canBeEditedBy($owner));
        $this->assertFalse($userExercise->canBeEditedBy($otherUser));
    }

    /** @test */
    public function can_be_deleted_by_returns_false_when_exercise_has_lift_logs()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Create a lift log associated with the exercise
        \App\Models\LiftLog::factory()->create(['exercise_id' => $exercise->id, 'user_id' => $user->id]);

        $this->assertFalse($exercise->canBeDeletedBy($user));
    }

    /** @test */
    public function can_be_deleted_by_returns_true_when_exercise_has_no_lift_logs_and_user_can_edit()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $exercise->liftLogs()->count());

        $this->assertTrue($exercise->canBeDeletedBy($user));
    }

    /** @test */
    public function can_be_deleted_by_returns_false_when_user_cannot_edit_exercise()
    {
        $owner = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $owner->id]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $exercise->liftLogs()->count());

        $this->assertFalse($exercise->canBeDeletedBy($otherUser));
    }

    /** @test */
    public function admin_can_delete_global_exercise_without_lift_logs()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $globalExercise->liftLogs()->count());

        $this->assertTrue($globalExercise->canBeDeletedBy($admin));
    }

    /** @test */
    public function admin_cannot_delete_global_exercise_with_lift_logs()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // Create a lift log associated with the exercise
        \App\Models\LiftLog::factory()->create(['exercise_id' => $globalExercise->id, 'user_id' => $admin->id]);

        $this->assertFalse($globalExercise->canBeDeletedBy($admin));
    }

    /** @test */
    public function is_banded_resistance_returns_true_for_resistance_band_type()
    {
        $exercise = Exercise::factory()->create(['band_type' => 'resistance']);
        $this->assertTrue($exercise->isBandedResistance());
        $this->assertFalse($exercise->isBandedAssistance());
    }

    /** @test */
    public function is_banded_assistance_returns_true_for_assistance_band_type()
    {
        $exercise = Exercise::factory()->create(['band_type' => 'assistance']);
        $this->assertTrue($exercise->isBandedAssistance());
        $this->assertFalse($exercise->isBandedResistance());
    }

    /** @test */
    public function is_banded_resistance_and_assistance_return_false_for_null_band_type()
    {
        $exercise = Exercise::factory()->create(['band_type' => null]);
        $this->assertFalse($exercise->isBandedResistance());
        $this->assertFalse($exercise->isBandedAssistance());
    }
}
