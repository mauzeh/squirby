<?php

namespace Tests\Unit;

use App\Http\Controllers\ExerciseController;
use App\Models\Exercise;
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
        $chartService = $this->createMock(\App\Services\ChartService::class);
        $tsvImporterService = $this->createMock(\App\Services\TsvImporterService::class);
        
        $this->controller = new ExerciseController($exerciseService, $chartService, $tsvImporterService);
    }

    public function test_promote_selected_validates_required_exercise_ids(): void
    {
        $this->actingAs($this->adminUser);
        
        $request = Request::create('/exercises/promote-selected', 'POST', []);
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_validates_exercise_ids_array(): void
    {
        $this->actingAs($this->adminUser);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => 'not-an-array'
        ]);
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_validates_exercise_ids_exist(): void
    {
        $this->actingAs($this->adminUser);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [999, 1000] // Non-existent IDs
        ]);
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_authorizes_each_exercise(): void
    {
        $this->actingAs($this->regularUser); // Non-admin user
        
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise->id]
        ]);
        
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_rejects_already_global_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$globalExercise->id]
        ]);
        
        // This should throw an authorization exception because the policy 
        // prevents promoting already global exercises
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_successfully_promotes_user_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise1 = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $userExercise2 = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise1->id, $userExercise2->id]
        ]);
        
        $response = $this->controller->promoteSelected($request);
        
        // Check response
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/exercises', $response->getTargetUrl());
        
        // Check database changes
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise1->id,
            'user_id' => null
        ]);
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise2->id,
            'user_id' => null
        ]);
        
        // Check success message
        $this->assertStringContainsString('Successfully promoted 2 exercise(s)', $response->getSession()->get('success'));
    }

    public function test_promote_selected_handles_mixed_valid_and_invalid_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise->id, $globalExercise->id]
        ]);
        
        // Should throw authorization exception when trying to promote global exercise
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        $this->controller->promoteSelected($request);
    }

    public function test_promote_selected_preserves_exercise_data_except_user_id(): void
    {
        $this->actingAs($this->adminUser);
        
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'is_bodyweight' => true
        ]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise->id]
        ]);
        
        $response = $this->controller->promoteSelected($request);
        
        // Check that all data is preserved except user_id
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'is_bodyweight' => true,
            'user_id' => null
        ]);
    }

    public function test_promote_selected_returns_correct_success_message_count(): void
    {
        $this->actingAs($this->adminUser);
        
        // Test with single exercise
        $userExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise->id]
        ]);
        
        $response = $this->controller->promoteSelected($request);
        
        $this->assertStringContainsString('Successfully promoted 1 exercise(s)', $response->getSession()->get('success'));
        
        // Test with multiple exercises
        $userExercise2 = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $userExercise3 = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $request2 = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$userExercise2->id, $userExercise3->id]
        ]);
        
        $response2 = $this->controller->promoteSelected($request2);
        
        $this->assertStringContainsString('Successfully promoted 2 exercise(s)', $response2->getSession()->get('success'));
    }

    public function test_promote_selected_only_updates_selected_exercises(): void
    {
        $this->actingAs($this->adminUser);
        
        $selectedExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $unselectedExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        
        $request = Request::create('/exercises/promote-selected', 'POST', [
            'exercise_ids' => [$selectedExercise->id]
        ]);
        
        $response = $this->controller->promoteSelected($request);
        
        // Selected exercise should be promoted
        $this->assertDatabaseHas('exercises', [
            'id' => $selectedExercise->id,
            'user_id' => null
        ]);
        
        // Unselected exercise should remain unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $unselectedExercise->id,
            'user_id' => $this->regularUser->id
        ]);
    }


}