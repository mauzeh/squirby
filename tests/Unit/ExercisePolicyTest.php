<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use App\Policies\ExercisePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExercisePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ExercisePolicy $policy;
    private User $adminUser;
    private User $regularUser;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new ExercisePolicy();
        
        // Create admin role
        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
        
        // Create users
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($this->adminRole);
        
        $this->regularUser = User::factory()->create();
    }

    public function test_admin_can_create_global_exercises(): void
    {
        $result = $this->policy->createGlobalExercise($this->adminUser);
        
        $this->assertTrue($result);
    }

    public function test_regular_user_cannot_create_global_exercises(): void
    {
        $result = $this->policy->createGlobalExercise($this->regularUser);
        
        $this->assertFalse($result);
    }

    public function test_admin_can_update_global_exercises(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->update($this->adminUser, $globalExercise);
        
        $this->assertTrue($result);
    }

    public function test_regular_user_cannot_update_global_exercises(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->update($this->regularUser, $globalExercise);
        
        $this->assertFalse($result);
    }

    public function test_user_can_update_their_own_exercises(): void
    {
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $result = $this->policy->update($this->regularUser, $userExercise);
        
        $this->assertTrue($result);
    }

    public function test_user_cannot_update_other_users_exercises(): void
    {
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        
        $result = $this->policy->update($this->regularUser, $otherUserExercise);
        
        $this->assertFalse($result);
    }

    public function test_admin_can_delete_global_exercises_without_lift_logs(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->delete($this->adminUser, $globalExercise);
        
        $this->assertTrue($result);
    }

    public function test_admin_can_delete_global_exercises_with_lift_logs(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        LiftLog::factory()->create(['exercise_id' => $globalExercise->id]);
        
        // Policy only checks permissions, not business logic about lift logs
        $result = $this->policy->delete($this->adminUser, $globalExercise);
        
        $this->assertTrue($result);
    }

    public function test_user_can_delete_their_own_exercises_without_lift_logs(): void
    {
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $result = $this->policy->delete($this->regularUser, $userExercise);
        
        $this->assertTrue($result);
    }

    public function test_user_can_delete_their_own_exercises_with_lift_logs(): void
    {
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        LiftLog::factory()->create(['exercise_id' => $userExercise->id, 'user_id' => $this->regularUser->id]);
        
        // Policy only checks permissions, not business logic about lift logs
        $result = $this->policy->delete($this->regularUser, $userExercise);
        
        $this->assertTrue($result);
    }

    public function test_user_cannot_delete_other_users_exercises(): void
    {
        $otherUser = User::factory()->create();
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        
        $result = $this->policy->delete($this->regularUser, $otherUserExercise);
        
        $this->assertFalse($result);
    }

    public function test_regular_user_cannot_delete_global_exercises(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->delete($this->regularUser, $globalExercise);
        
        $this->assertFalse($result);
    }

    public function test_admin_can_promote_user_exercises_to_global(): void
    {
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $result = $this->policy->promoteToGlobal($this->adminUser, $userExercise);
        
        $this->assertTrue($result);
    }

    public function test_regular_user_cannot_promote_exercises_to_global(): void
    {
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $result = $this->policy->promoteToGlobal($this->regularUser, $userExercise);
        
        $this->assertFalse($result);
    }

    public function test_admin_cannot_promote_already_global_exercises(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->promoteToGlobal($this->adminUser, $globalExercise);
        
        $this->assertFalse($result);
    }

    public function test_regular_user_cannot_promote_already_global_exercises(): void
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $result = $this->policy->promoteToGlobal($this->regularUser, $globalExercise);
        
        $this->assertFalse($result);
    }
}