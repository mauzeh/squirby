<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WorkoutExerciseListService;
use App\Services\ExerciseAliasService;
use App\Services\ExerciseMatchingService;
use App\Models\Workout;
use App\Models\Exercise;
use App\Models\WorkoutExercise;
use App\Models\User;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class WorkoutExerciseListServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $aliasService;
    protected $matchingService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->aliasService = $this->createMock(ExerciseAliasService::class);
        $this->matchingService = $this->createMock(ExerciseMatchingService::class);
        
        $this->service = new WorkoutExerciseListService(
            $this->aliasService,
            $this->matchingService
        );

        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_generates_exercise_list_table_for_simple_workout()
    {
        // Create workout with exercises
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        $exercise1 = Exercise::factory()->create(['title' => 'Back Squat']);
        $exercise2 = Exercise::factory()->create(['title' => 'Bench Press']);
        
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise1->id, 'order' => 1]);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise2->id, 'order' => 2]);

        // Mock alias service
        $this->aliasService->method('getDisplayName')
            ->willReturnCallback(fn($exercise) => $exercise->title);

        $result = $this->service->generateExerciseListTable($workout);

        $this->assertEquals('table', $result['type']);
        $this->assertCount(2, $result['data']['rows']);
        $this->assertEquals('Back Squat', $result['data']['rows'][0]['line1']);
        $this->assertEquals('Bench Press', $result['data']['rows'][1]['line1']);
    }

    public function test_generates_empty_message_for_workout_with_no_exercises()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);

        $result = $this->service->generateExerciseListTable($workout);

        $this->assertEquals('messages', $result['type']);
        $this->assertEquals('No exercises in this workout yet.', $result['data']['messages'][0]['text']);
    }

    public function test_shows_logged_status_when_enabled()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        $exercise = Exercise::factory()->create(['title' => 'Back Squat']);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order' => 1]);

        // Create a lift log for today
        $today = Carbon::today();
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $today
        ]);

        $this->aliasService->method('getDisplayName')->willReturn('Back Squat');

        $result = $this->service->generateExerciseListTable($workout, [
            'showLoggedStatus' => true
        ]);

        // Should have a row for the exercise
        $this->assertEquals('table', $result['type']);
        $this->assertCount(1, $result['data']['rows']);
        
        $row = $result['data']['rows'][0];
        $this->assertEquals('Back Squat', $row['line1']);
        
        // Should have success message for logged exercise
        $this->assertArrayHasKey('messages', $row);
        $this->assertEquals('success', $row['messages'][0]['type']);
    }

    public function test_generates_advanced_workout_table_with_matched_exercises()
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
            'wod_syntax' => "# Strength\n[[Back Squat]]: 5x5\n[[Bench Press]]: 3x8"
        ]);

        $exercise1 = Exercise::factory()->create(['title' => 'Back Squat']);
        $exercise2 = Exercise::factory()->create(['title' => 'Bench Press']);

        // Mock matching service to return exercises
        $this->matchingService->method('findBestMatch')
            ->willReturnMap([
                ['Back Squat', $this->user->id, $exercise1],
                ['Bench Press', $this->user->id, $exercise2]
            ]);

        $this->aliasService->method('getDisplayName')
            ->willReturnCallback(fn($exercise) => $exercise->title);

        $result = $this->service->generateExerciseListTable($workout, [
            'redirectContext' => 'advanced-workout'
        ]);

        $this->assertEquals('table', $result['type']);
        $this->assertCount(2, $result['data']['rows']);
        $this->assertEquals('Back Squat', $result['data']['rows'][0]['line1']);
        $this->assertEquals('Bench Press', $result['data']['rows'][1]['line1']);
    }

    public function test_generates_advanced_workout_table_with_unmatched_exercises()
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
            'wod_syntax' => "# Strength\n[[Unknown Exercise]]: 5x5"
        ]);

        // Mock matching service to return null (no match)
        $this->matchingService->method('findBestMatch')
            ->willReturn(null);

        $result = $this->service->generateExerciseListTable($workout, [
            'redirectContext' => 'advanced-workout'
        ]);

        $this->assertEquals('table', $result['type']);
        $this->assertCount(1, $result['data']['rows']);
        
        $row = $result['data']['rows'][0];
        $this->assertEquals('Unknown Exercise', $row['line1']);
        $this->assertEquals('Exercise not found - create alias to link it', $row['line2']);
        
        // Should have alias creation button with orange styling
        $this->assertCount(1, $row['actions']);
        $this->assertEquals('fa-link', $row['actions'][0]['icon']);
        $this->assertEquals('Create alias', $row['actions'][0]['ariaLabel']);
        $this->assertEquals('btn-secondary', $row['actions'][0]['cssClass']);
    }

    public function test_shows_alias_mapping_in_advanced_workout()
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
            'wod_syntax' => "# Strength\n[[KB Swings]]: 3x10"
        ]);

        $exercise = Exercise::factory()->create(['title' => 'Kettlebell Swings']);

        // Mock matching service to return the exercise (fuzzy match)
        $this->matchingService->method('findBestMatch')
            ->with('KB Swings', $this->user->id)
            ->willReturn($exercise);

        $this->aliasService->method('getDisplayName')
            ->willReturn('Kettlebell Swings');

        $result = $this->service->generateExerciseListTable($workout, [
            'redirectContext' => 'advanced-workout'
        ]);

        $row = $result['data']['rows'][0];
        // Should show the mapping: WOD name → actual exercise name
        $this->assertEquals('KB Swings → Kettlebell Swings', $row['line1']);
    }

    public function test_generates_exercise_selection_list()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        
        // Create some exercises
        $exercise1 = Exercise::factory()->create(['title' => 'Back Squat']);
        $exercise2 = Exercise::factory()->create(['title' => 'Bench Press']);
        
        // Mock alias service
        $this->aliasService->method('applyAliasesToExercises')
            ->willReturnCallback(fn($exercises) => $exercises);

        $result = $this->service->generateExerciseSelectionList($workout);

        $this->assertEquals('item-list', $result['type']);
        $this->assertArrayHasKey('items', $result['data']);
        $this->assertArrayHasKey('createForm', $result['data']);
    }

    public function test_generates_exercise_selection_list_for_new_workout()
    {
        // Create some exercises
        Exercise::factory()->create(['title' => 'Back Squat']);
        Exercise::factory()->create(['title' => 'Bench Press']);
        
        // Mock alias service
        $this->aliasService->method('applyAliasesToExercises')
            ->willReturnCallback(fn($exercises) => $exercises);

        $result = $this->service->generateExerciseSelectionListForNew($this->user->id);

        $this->assertEquals('item-list', $result['type']);
        $this->assertArrayHasKey('items', $result['data']);
        $this->assertArrayHasKey('createForm', $result['data']);
        $this->assertEquals('expanded', $result['data']['initialState']);
    }

    public function test_excludes_exercises_already_in_workout_from_selection_list()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        $exercise1 = Exercise::factory()->create(['title' => 'Back Squat']);
        $exercise2 = Exercise::factory()->create(['title' => 'Bench Press']);
        
        // Add one exercise to workout
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise1->id, 'order' => 1]);

        $this->aliasService->method('applyAliasesToExercises')
            ->willReturnCallback(fn($exercises) => $exercises);

        $result = $this->service->generateExerciseSelectionList($workout);

        // Should only show Bench Press (Back Squat is already in workout)
        $items = collect($result['data']['items']);
        $this->assertCount(1, $items);
        $this->assertTrue($items->contains('name', 'Bench Press'));
        $this->assertFalse($items->contains('name', 'Back Squat'));
    }

    public function test_handles_compact_mode_option()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        $exercise = Exercise::factory()->create(['title' => 'Back Squat']);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order' => 1]);

        $this->aliasService->method('getDisplayName')->willReturn('Back Squat');

        $result = $this->service->generateExerciseListTable($workout, [
            'compactMode' => true
        ]);

        $row = $result['data']['rows'][0];
        $this->assertTrue($row['compact']);
    }

    public function test_handles_show_buttons_options()
    {
        $workout = Workout::factory()->create(['user_id' => $this->user->id]);
        $exercise = Exercise::factory()->create(['title' => 'Back Squat']);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order' => 1]);

        $this->aliasService->method('getDisplayName')->willReturn('Back Squat');

        // Test with all buttons disabled
        $result = $this->service->generateExerciseListTable($workout, [
            'showPlayButtons' => false,
            'showMoveButtons' => false,
            'showDeleteButtons' => false
        ]);

        $row = $result['data']['rows'][0];
        $this->assertEmpty($row['actions'] ?? []);
    }

    public function test_advanced_workout_with_no_loggable_exercises()
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
            'wod_syntax' => "# Warm-up\n[Dynamic Stretching] 5min\n[Foam Rolling] 3min"
        ]);

        $result = $this->service->generateExerciseListTable($workout, [
            'redirectContext' => 'advanced-workout'
        ]);

        $this->assertEquals('messages', $result['type']);
        $this->assertStringContainsString('No loggable exercises found', $result['data']['messages'][0]['text']);
    }
}