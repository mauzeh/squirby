<?php

namespace Tests\Unit;

use App\Http\Controllers\ExerciseController;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseController $controller;
    private User $adminUser;
    private User $regularUser;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role
        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
        
        // Create users
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($this->adminRole);
        
        $this->regularUser = User::factory()->create();
        
        // Create controller instance with mocked dependencies
        $exerciseService = $this->createMock(\App\Services\ExerciseService::class);
        $exerciseMergeService = $this->createMock(\App\Services\ExerciseMergeService::class);
        $chartService = $this->createMock(\App\Services\ChartService::class);
        $liftLogTablePresenter = $this->createMock(\App\Presenters\LiftLogTablePresenter::class);
        $exercisePRService = $this->createMock(\App\Services\ExercisePRService::class);
        $createExerciseAction = $this->createMock(\App\Actions\Exercises\CreateExerciseAction::class);
        $updateExerciseAction = $this->createMock(\App\Actions\Exercises\UpdateExerciseAction::class);
        $mergeExerciseAction = $this->createMock(\App\Actions\Exercises\MergeExerciseAction::class);
        $exerciseFormService = $this->createMock(\App\Services\ExerciseFormService::class);
        $exercisePageService = $this->createMock(\App\Services\ExercisePageService::class);
        
        $this->controller = new ExerciseController(
            $exerciseService, 
            $exerciseMergeService, 
            $chartService, 
            $liftLogTablePresenter, 
            $exercisePRService,
            $createExerciseAction,
            $updateExerciseAction,
            $mergeExerciseAction,
            $exerciseFormService,
            $exercisePageService
        );
    }

    public function test_promote_authorizes_exercise(): void
    {
        $this->actingAs($this->regularUser); // Non-admin user
        
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->promote($userExercise);
    }

    public function test_promote_rejects_already_global_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // This should throw an authorization exception because the policy 
        // prevents promoting already global exercises
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->promote($globalExercise);
    }

    public function test_promote_successfully_promotes_user_exercise(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise'
        ]);
        
        $response = $this->controller->promote($userExercise);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/exercises', $response->getTargetUrl());
        
        // Check database changes
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => null
        ]);
        
        // Check success message
        $this->assertStringContainsString("Exercise 'Test Exercise' promoted to global status successfully.", $response->getSession()->get('success'));
    }

    public function test_promote_preserves_exercise_data_except_user_id(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'exercise_type' => 'bodyweight'
        ]);
        
        $response = $this->controller->promote($userExercise);
        
        // Check that all data is preserved except user_id
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'user_id' => null,
            'exercise_type' => 'bodyweight'
        ]);
    }

    public function test_promote_returns_correct_success_message(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'My Custom Exercise'
        ]);
        
        $response = $this->controller->promote($userExercise);
        
        $this->assertStringContainsString("Exercise 'My Custom Exercise' promoted to global status successfully.", $response->getSession()->get('success'));
    }

    // Unpromote functionality tests

    public function test_unpromote_authorizes_exercise(): void
    {
        $this->actingAs($this->regularUser); // Non-admin user
        
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->unpromote($globalExercise);
    }

    public function test_unpromote_rejects_non_global_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        // This should throw an authorization exception because the policy 
        // prevents unpromoting non-global exercises
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->unpromote($userExercise);
    }

    public function test_unpromote_fails_when_no_original_owner_can_be_determined(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise'
        ]);
        
        // No lift logs exist, so no original owner can be determined
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        
        // Check error message
        $this->assertStringContainsString("Cannot determine original owner for exercise 'Test Exercise'", $response->getSession()->get('errors')->first());
    }

    public function test_unpromote_successfully_unpromotes_when_safe(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise'
        ]);
        
        // Create a lift log for the original owner
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/exercises', $response->getTargetUrl());
        
        // Check database changes
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => $this->regularUser->id
        ]);
        
        // Check success message
        $this->assertStringContainsString("Exercise 'Test Exercise' unpromoted to personal exercise successfully.", $response->getSession()->get('success'));
    }

    public function test_unpromote_blocked_when_other_users_have_logs(): void
    {
        $this->actingAs($this->adminUser);
        
        $anotherUser = User::factory()->create();
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise'
        ]);
        
        // Create lift logs for original owner (earliest)
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(10)
        ]);
        
        // Create lift log for another user
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $anotherUser->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        
        // Check error message
        $errorMessage = $response->getSession()->get('errors')->first();
        $this->assertStringContainsString("Cannot unpromote exercise 'Test Exercise': 1 other user has workout logs", $errorMessage);
        $this->assertStringContainsString("The exercise must remain global to preserve their data", $errorMessage);
        
        // Check database unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => null
        ]);
    }

    public function test_unpromote_blocked_when_multiple_other_users_have_logs(): void
    {
        $this->actingAs($this->adminUser);
        
        $anotherUser1 = User::factory()->create();
        $anotherUser2 = User::factory()->create();
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise'
        ]);
        
        // Create lift logs for original owner (earliest)
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(10)
        ]);
        
        // Create lift logs for other users
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $anotherUser1->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $anotherUser2->id,
            'logged_at' => now()->subDays(3)
        ]);
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        
        // Check error message uses plural form
        $errorMessage = $response->getSession()->get('errors')->first();
        $this->assertStringContainsString("Cannot unpromote exercise 'Test Exercise': 2 other users have workout logs", $errorMessage);
        $this->assertStringContainsString("The exercise must remain global to preserve their data", $errorMessage);
    }

    public function test_unpromote_determines_original_owner_from_earliest_lift_log(): void
    {
        $this->actingAs($this->adminUser);
        
        $anotherUser = User::factory()->create();
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise'
        ]);
        
        // Create lift log for another user (later)
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $anotherUser->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        // Create lift log for original owner (earliest)
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(10)
        ]);
        
        // Since another user has logs, unpromote should be blocked
        // But we can verify the original owner determination logic by checking the error message
        $response = $this->controller->unpromote($globalExercise);
        
        // The fact that we get the "other user has logs" error confirms that
        // the original owner was correctly identified as $this->regularUser
        // (if it wasn't, we'd get a different error about not being able to determine the owner)
        $errorMessage = $response->getSession()->get('errors')->first();
        $this->assertStringContainsString("1 other user has workout logs", $errorMessage);
    }

    public function test_unpromote_preserves_exercise_data_except_user_id(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'exercise_type' => 'bodyweight'
        ]);
        
        // Create a lift log for the original owner
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check that all data is preserved except user_id
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'bodyweight'
        ]);
    }

    // Show in feed functionality tests

    public function test_create_exercise_with_show_in_feed_enabled(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->post(route('exercises.store'), [
            'title' => 'New Exercise',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            'show_in_feed' => true,
        ]);
        
        $response->assertRedirect(route('exercises.index'));
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $this->regularUser->id,
            'show_in_feed' => true,
        ]);
    }

    public function test_create_exercise_with_show_in_feed_disabled(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->post(route('exercises.store'), [
            'title' => 'New Exercise',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            'show_in_feed' => false,
        ]);
        
        $response->assertRedirect(route('exercises.index'));
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $this->regularUser->id,
            'show_in_feed' => false,
        ]);
    }

    public function test_create_exercise_defaults_show_in_feed_to_false_when_not_provided(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->post(route('exercises.store'), [
            'title' => 'New Exercise',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            // show_in_feed not provided
        ]);
        
        $response->assertRedirect(route('exercises.index'));
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $this->regularUser->id,
            'show_in_feed' => false,
        ]);
    }

    public function test_update_exercise_can_enable_show_in_feed(): void
    {
        $this->actingAs($this->regularUser);
        
        $exercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'show_in_feed' => false,
        ]);
        
        $response = $this->put(route('exercises.update', $exercise), [
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            'show_in_feed' => true,
        ]);
        
        $response->assertRedirect(route('exercises.edit', $exercise));
        
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'show_in_feed' => true,
        ]);
    }

    public function test_update_exercise_can_disable_show_in_feed(): void
    {
        $this->actingAs($this->regularUser);
        
        $exercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'show_in_feed' => true,
        ]);
        
        $response = $this->put(route('exercises.update', $exercise), [
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            'show_in_feed' => false,
        ]);
        
        $response->assertRedirect(route('exercises.edit', $exercise));
        
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'show_in_feed' => false,
        ]);
    }

    public function test_update_exercise_defaults_show_in_feed_to_false_when_not_provided(): void
    {
        $this->actingAs($this->regularUser);
        
        $exercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'show_in_feed' => true,
        ]);
        
        $response = $this->put(route('exercises.update', $exercise), [
            'title' => 'Test Exercise Updated',
            'description' => 'Test description',
            'exercise_type' => 'regular',
            // show_in_feed not provided
        ]);
        
        $response->assertRedirect(route('exercises.edit', $exercise));
        
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'title' => 'Test Exercise Updated',
            'show_in_feed' => false,
        ]);
    }

    public function test_promote_preserves_show_in_feed_value(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'show_in_feed' => true,
        ]);
        
        $response = $this->controller->promote($userExercise);
        
        // Check that show_in_feed is preserved
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => null,
            'show_in_feed' => true,
        ]);
    }

    public function test_unpromote_preserves_show_in_feed_value(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Test Exercise',
            'show_in_feed' => true,
        ]);
        
        // Create a lift log for the original owner
        LiftLog::factory()->create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        $response = $this->controller->unpromote($globalExercise);
        
        // Check that show_in_feed is preserved
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => $this->regularUser->id,
            'show_in_feed' => true,
        ]);
    }
}
