<?php

namespace Tests\Unit\Services\MobileEntry;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\MobileLiftForm;
use App\Models\LiftLog;
use App\Services\MobileEntry\LiftLogService;
use App\Services\TrainingProgressionService;
use App\Services\MobileEntry\LiftDataCacheService;
use App\Services\ExerciseAliasService;
use App\Services\RecommendationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class LiftLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LiftLogService $service;
    protected $trainingProgressionService;
    protected $cacheService;
    protected $aliasService;
    protected $recommendationEngine;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks for dependencies
        $this->trainingProgressionService = Mockery::mock(TrainingProgressionService::class);
        $this->cacheService = Mockery::mock(LiftDataCacheService::class);
        $this->aliasService = Mockery::mock(ExerciseAliasService::class);
        $this->recommendationEngine = Mockery::mock(RecommendationEngine::class);
        
        $this->service = new LiftLogService(
            $this->trainingProgressionService,
            $this->cacheService,
            $this->aliasService,
            $this->recommendationEngine
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function generate_forms_returns_empty_array_when_no_mobile_lift_forms_exist()
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2024-01-15');
        
        $forms = $this->service->generateForms($user->id, $date);
        
        $this->assertIsArray($forms);
        $this->assertEmpty($forms);
    }

    /** @test */
    public function generate_forms_creates_forms_from_mobile_lift_forms()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $date = Carbon::parse('2024-01-15');
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
        
        // Mock dependencies
        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn('Bench Press');
        
        $this->cacheService->shouldReceive('getAllCachedData')
            ->once()
            ->andReturn(['lastSessionData' => []]);
        
        $forms = $this->service->generateForms($user->id, $date);
        
        $this->assertCount(1, $forms);
        $this->assertEquals('primary', $forms[0]['type']);
        $this->assertEquals('Bench Press', $forms[0]['title']);
    }

    /** @test */
    public function add_exercise_form_creates_mobile_lift_form_successfully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['canonical_name' => 'bench_press']);
        $date = Carbon::parse('2024-01-15');
        
        $result = $this->service->addExerciseForm($user->id, 'bench_press', $date);
        
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('mobile_lift_forms', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function add_exercise_form_returns_error_when_exercise_not_found()
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2024-01-15');
        
        $result = $this->service->addExerciseForm($user->id, 'nonexistent', $date);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    /** @test */
    public function add_exercise_form_returns_error_when_form_already_exists()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['canonical_name' => 'bench_press']);
        $date = Carbon::parse('2024-01-15');
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
        
        $result = $this->service->addExerciseForm($user->id, 'bench_press', $date);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already', $result['message']);
    }

    /** @test */
    public function remove_form_deletes_mobile_lift_form_successfully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $result = $this->service->removeForm($user->id, 'lift-' . $form->id);
        
        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('mobile_lift_forms', ['id' => $form->id]);
    }

    /** @test */
    public function remove_form_returns_error_for_invalid_format()
    {
        $user = User::factory()->create();
        
        $result = $this->service->removeForm($user->id, 'invalid-format');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid', $result['message']);
    }

    /** @test */
    public function remove_form_returns_error_when_form_not_found()
    {
        $user = User::factory()->create();
        
        $result = $this->service->removeForm($user->id, 'lift-999');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    /** @test */
    public function create_exercise_creates_new_exercise_and_mobile_lift_form()
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2024-01-15');
        
        $result = $this->service->createExercise($user->id, 'New Exercise', $date);
        
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $user->id,
        ]);
        
        $exercise = Exercise::where('title', 'New Exercise')->first();
        $this->assertDatabaseHas('mobile_lift_forms', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function create_exercise_returns_error_when_exercise_already_exists()
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2024-01-15');
        Exercise::factory()->create([
            'title' => 'Existing Exercise',
            'user_id' => $user->id,
        ]);
        
        $result = $this->service->createExercise($user->id, 'Existing Exercise', $date);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    /** @test */
    public function generate_item_selection_list_returns_exercises_without_program_logic()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1', 'user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2', 'user_id' => null]);
        $date = Carbon::parse('2024-01-15');
        
        // Mock dependencies
        $this->aliasService->shouldReceive('applyAliasesToExercises')
            ->once()
            ->andReturnUsing(function ($exercises, $user) {
                return $exercises;
            });
        
        $this->recommendationEngine->shouldReceive('getRecommendations')
            ->once()
            ->andReturn([]);
        
        $result = $this->service->generateItemSelectionList($user->id, $date);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
    }

    /** @test */
    public function generate_item_selection_list_excludes_exercises_already_in_forms()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1', 'user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2', 'user_id' => $user->id]);
        $date = Carbon::parse('2024-01-15');
        
        // Add exercise1 to mobile lift forms
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $date,
        ]);
        
        // Mock dependencies
        $this->aliasService->shouldReceive('applyAliasesToExercises')
            ->once()
            ->andReturnUsing(function ($exercises, $user) {
                return $exercises;
            });
        
        $this->recommendationEngine->shouldReceive('getRecommendations')
            ->once()
            ->andReturn([]);
        
        $result = $this->service->generateItemSelectionList($user->id, $date);
        
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Exercise 2', $result['items'][0]['name']);
    }

    /** @test */
    public function generate_forms_calculates_default_sets_and_reps_dynamically()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Squat']);
        $date = Carbon::parse('2024-01-15');
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
        
        // Mock dependencies
        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn('Squat');
        
        $this->cacheService->shouldReceive('getAllCachedData')
            ->once()
            ->andReturn([
                'lastSessionData' => [
                    $exercise->id => [
                        'weight' => 135,
                        'reps' => 8,
                        'sets' => 3,
                        'date' => 'Jan 10',
                    ]
                ]
            ]);
        
        // getSuggestionDetails is called multiple times (once for progression, once for messages)
        $this->trainingProgressionService->shouldReceive('getSuggestionDetails')
            ->andReturn((object)[
                'reps' => 10,
                'sets' => 4,
                'suggestedWeight' => 140,
            ]);
        
        $forms = $this->service->generateForms($user->id, $date);
        
        $this->assertCount(1, $forms);
        
        // Find the reps field
        $repsField = collect($forms[0]['numericFields'])->firstWhere('name', 'reps');
        $this->assertEquals(10, $repsField['defaultValue']);
        
        // Find the rounds/sets field
        $setsField = collect($forms[0]['numericFields'])->firstWhere('name', 'rounds');
        $this->assertEquals(4, $setsField['defaultValue']);
    }

    /** @test */
    public function generate_forms_uses_fallback_values_when_no_last_session()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Deadlift']);
        $date = Carbon::parse('2024-01-15');
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
        
        // Mock dependencies
        $this->aliasService->shouldReceive('getDisplayName')
            ->once()
            ->andReturn('Deadlift');
        
        $this->cacheService->shouldReceive('getAllCachedData')
            ->once()
            ->andReturn(['lastSessionData' => []]);
        
        $forms = $this->service->generateForms($user->id, $date);
        
        $this->assertCount(1, $forms);
        
        // Check default values
        $repsField = collect($forms[0]['numericFields'])->firstWhere('name', 'reps');
        $this->assertEquals(5, $repsField['defaultValue']);
        
        $setsField = collect($forms[0]['numericFields'])->firstWhere('name', 'rounds');
        $this->assertEquals(3, $setsField['defaultValue']);
    }
}
