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
        $liftLogTablePresenter = $this->createMock(\App\Presenters\LiftLogTablePresenter::class);
        
        $this->controller = new ExerciseController($exerciseService, $chartService, $tsvImporterService, $liftLogTablePresenter);
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
            'is_bodyweight' => true
        ]);
        
        $response = $this->controller->promote($userExercise);
        
        // Check that all data is preserved except user_id
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'is_bodyweight' => true,
            'user_id' => null
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
}