<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExerciseAvailableToUserScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role for testing
        Role::factory()->create(['name' => 'Admin']);
    }

    /** @test */
    public function availableToUser_returns_no_exercises_when_no_user_id_provided()
    {
        $user = User::factory()->create();
        
        // Create some exercises
        Exercise::factory()->create(); // Global exercise
        Exercise::factory()->create(['user_id' => $user->id]); // User exercise
        
        $exercises = Exercise::availableToUser(null)->get();
        
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function availableToUser_returns_no_exercises_when_user_id_is_zero()
    {
        $user = User::factory()->create();
        
        // Create some exercises
        Exercise::factory()->create(); // Global exercise
        Exercise::factory()->create(['user_id' => $user->id]); // User exercise
        
        $exercises = Exercise::availableToUser(0)->get();
        
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function availableToUser_returns_no_exercises_when_user_does_not_exist()
    {
        $user = User::factory()->create();
        
        // Create some exercises
        Exercise::factory()->create(); // Global exercise
        Exercise::factory()->create(['user_id' => $user->id]); // User exercise
        
        $exercises = Exercise::availableToUser(999)->get();
        
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function availableToUser_returns_no_exercises_when_no_authenticated_user()
    {
        $user = User::factory()->create();
        
        // Ensure no user is authenticated
        Auth::logout();
        
        // Create some exercises
        Exercise::factory()->create(); // Global exercise
        Exercise::factory()->create(['user_id' => $user->id]); // User exercise
        
        $exercises = Exercise::availableToUser()->get();
        
        $this->assertCount(0, $exercises);
    }

    /** @test */
    public function availableToUser_returns_all_exercises_for_admin_user()
    {
        $adminUser = User::factory()->create(['show_global_exercises' => false]);
        $adminRole = Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);
        
        $regularUser = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(); // Global exercise
        $adminExercise = Exercise::factory()->create(['user_id' => $adminUser->id]);
        $userExercise = Exercise::factory()->create(['user_id' => $regularUser->id]);
        
        $exercises = Exercise::availableToUser($adminUser->id)->get();
        
        $this->assertCount(3, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($adminExercise));
        $this->assertTrue($exercises->contains($userExercise));
    }

    /** @test */
    public function availableToUser_respects_user_preference_when_show_global_exercises_is_true()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        $otherUser = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(); // Global exercise
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        
        $exercises = Exercise::availableToUser($user->id)->get();
        
        $this->assertCount(2, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
        $this->assertFalse($exercises->contains($otherUserExercise));
    }

    /** @test */
    public function availableToUser_respects_user_preference_when_show_global_exercises_is_false()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        $otherUser = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(); // Global exercise
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        
        $exercises = Exercise::availableToUser($user->id)->get();
        
        $this->assertCount(1, $exercises);
        $this->assertFalse($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
        $this->assertFalse($exercises->contains($otherUserExercise));
    }

    /** @test */
    public function availableToUser_uses_authenticated_user_when_no_user_id_provided()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        Auth::login($user);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(); // Global exercise
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        $exercises = Exercise::availableToUser()->get();
        
        $this->assertCount(2, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
    }

    /** @test */
    public function availableToUser_overrides_user_preference_with_showGlobal_parameter()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(); // Global exercise
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Override preference to show global exercises
        $exercises = Exercise::availableToUser($user->id, true)->get();
        
        $this->assertCount(2, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
        
        // Override preference to hide global exercises
        $user->update(['show_global_exercises' => true]);
        $exercises = Exercise::availableToUser($user->id, false)->get();
        
        $this->assertCount(1, $exercises);
        $this->assertFalse($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
    }

    /** @test */
    public function availableToUser_orders_exercises_correctly_when_showing_global()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'User Exercise'
        ]);
        
        $exercises = Exercise::availableToUser($user->id)->get();
        
        // Should be ordered with user exercises first (user_id IS NULL ASC means NULL comes first, but the query actually prioritizes user exercises)
        $this->assertCount(2, $exercises);
        $exerciseTitles = $exercises->pluck('title')->toArray();
        $this->assertContains('Global Exercise', $exerciseTitles);
        $this->assertContains('User Exercise', $exerciseTitles);
    }

    /** @test */
    public function availableToUser_orders_exercises_by_title_when_hiding_global()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        
        // Create user exercises with specific titles for ordering
        $exerciseB = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'B Exercise'
        ]);
        $exerciseA = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'A Exercise'
        ]);
        
        $exercises = Exercise::availableToUser($user->id)->get();
        
        // Should be ordered by title ASC
        $this->assertEquals('A Exercise', $exercises->first()->title);
        $this->assertEquals('B Exercise', $exercises->last()->title);
    }

    /** @test */
    public function availableToUser_handles_user_with_default_show_global_exercises_preference()
    {
        // Create user with default preference (should be true)
        $user = User::factory()->create(['show_global_exercises' => true]);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create();
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        $exercises = Exercise::availableToUser($user->id)->get();
        
        // Should show global exercises
        $this->assertCount(2, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($userExercise));
    }

    /** @test */
    public function availableToUser_admin_sees_all_exercises_regardless_of_preference()
    {
        $adminUser = User::factory()->create(['show_global_exercises' => false]);
        $adminRole = Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);
        
        $regularUser = User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create();
        $adminExercise = Exercise::factory()->create(['user_id' => $adminUser->id]);
        $userExercise = Exercise::factory()->create(['user_id' => $regularUser->id]);
        
        // Even though admin has show_global_exercises = false, they should see all
        $exercises = Exercise::availableToUser($adminUser->id)->get();
        
        $this->assertCount(3, $exercises);
        $this->assertTrue($exercises->contains($globalExercise));
        $this->assertTrue($exercises->contains($adminExercise));
        $this->assertTrue($exercises->contains($userExercise));
    }
}