<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\UnifiedExerciseListService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedExerciseListServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UnifiedExerciseListService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnifiedExerciseListService::class);
        $this->user = User::factory()->create();
    }

    public function test_generates_list_with_all_exercises_by_default()
    {
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Squat', 'user_id' => $this->user->id]);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
    }

    public function test_filters_to_logged_exercises_only_when_configured()
    {
        $loggedExercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $unloggedExercise = Exercise::factory()->create(['title' => 'Squat']);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $loggedExercise->id,
        ]);

        $result = $this->service->generate($this->user->id, [
            'filter_exercises' => 'logged-only',
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEquals('exercise-' . $loggedExercise->id, $result['items'][0]['id']);
    }

    public function test_categorizes_recent_exercises_correctly()
    {
        $recentExercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $oldExercise = Exercise::factory()->create(['title' => 'Squat']);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $recentExercise->id,
            'logged_at' => Carbon::now()->subDays(7),
        ]);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $oldExercise->id,
            'logged_at' => Carbon::now()->subDays(60),
        ]);

        $result = $this->service->generate($this->user->id, [
            'recent_days' => 28,
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        // Recent exercise should have 'recent' CSS class
        $recentItem = collect($result['items'])->firstWhere('id', 'exercise-' . $recentExercise->id);
        $this->assertEquals('recent', $recentItem['type']['cssClass']);
        $this->assertEquals(1, $recentItem['type']['priority']);

        // Old exercise should have 'exercise-history' CSS class
        $oldItem = collect($result['items'])->firstWhere('id', 'exercise-' . $oldExercise->id);
        $this->assertEquals('exercise-history', $oldItem['type']['cssClass']);
        $this->assertEquals(2, $oldItem['type']['priority']);
    }

    public function test_shows_popular_exercises_for_new_users()
    {
        // Create another user with lots of logs to establish "popular" exercises
        $experiencedUser = User::factory()->create();
        $popularExercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $unpopularExercise = Exercise::factory()->create(['title' => 'Squat']);

        // Create 50 logs for popular exercise
        LiftLog::factory()->count(50)->create([
            'user_id' => $experiencedUser->id,
            'exercise_id' => $popularExercise->id,
        ]);

        // Create only 2 logs for unpopular exercise
        LiftLog::factory()->count(2)->create([
            'user_id' => $experiencedUser->id,
            'exercise_id' => $unpopularExercise->id,
        ]);

        // New user has < 5 logs
        $newUser = User::factory()->create();
        LiftLog::factory()->count(3)->create(['user_id' => $newUser->id]);

        $result = $this->service->generate($newUser->id, [
            'show_popular' => true,
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        // Popular exercise should have 'in-program' CSS class
        $popularItem = collect($result['items'])->firstWhere('id', 'exercise-' . $popularExercise->id);
        $this->assertEquals('in-program', $popularItem['type']['cssClass']);
        $this->assertEquals('Popular', $popularItem['type']['label']);
    }

    public function test_sorts_recent_exercises_alphabetically()
    {
        $exerciseZ = Exercise::factory()->create(['title' => 'Zercher Squat']);
        $exerciseA = Exercise::factory()->create(['title' => 'Arnold Press']);
        $exerciseM = Exercise::factory()->create(['title' => 'Military Press']);

        // All logged within last 28 days
        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseZ->id,
            'logged_at' => Carbon::now()->subDays(5),
        ]);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseA->id,
            'logged_at' => Carbon::now()->subDays(10),
        ]);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseM->id,
            'logged_at' => Carbon::now()->subDays(15),
        ]);

        $result = $this->service->generate($this->user->id, [
            'recent_days' => 28,
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        // All should be recent
        $recentItems = collect($result['items'])->filter(fn($item) => $item['type']['cssClass'] === 'recent');
        $this->assertCount(3, $recentItems);

        // Should be sorted alphabetically: Arnold, Military, Zercher
        $this->assertEquals('Arnold Press', $recentItems->values()[0]['name']);
        $this->assertEquals('Military Press', $recentItems->values()[1]['name']);
        $this->assertEquals('Zercher Squat', $recentItems->values()[2]['name']);
    }

    public function test_generates_time_labels_correctly()
    {
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);

        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(60),
        ]);

        $result = $this->service->generate($this->user->id, [
            'recent_days' => 28,
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $item = collect($result['items'])->firstWhere('id', 'exercise-' . $exercise->id);
        
        // Should have a time label like "2mo ago"
        $this->assertNotEmpty($item['type']['label']);
        $this->assertStringContainsString('ago', $item['type']['label']);
    }

    public function test_respects_date_parameter_for_recency_calculation()
    {
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);

        // Logged 20 days ago
        LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(20),
        ]);

        // When viewing from 10 days ago, the exercise should be recent (within 28 days)
        $viewDate = Carbon::now()->subDays(10);
        
        $result = $this->service->generate($this->user->id, [
            'date' => $viewDate,
            'recent_days' => 28,
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $item = collect($result['items'])->firstWhere('id', 'exercise-' . $exercise->id);
        $this->assertEquals('recent', $item['type']['cssClass']);
    }

    public function test_uses_custom_filter_placeholder()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'filter_placeholder' => 'Custom search text',
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $this->assertEquals('Custom search text', $result['filterPlaceholder']);
    }

    public function test_uses_custom_aria_labels()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
            'aria_labels' => [
                'section' => 'Custom section label',
                'selectItem' => 'Custom select label',
            ],
        ]);

        $this->assertEquals('Custom section label', $result['ariaLabels']['section']);
        $this->assertEquals('Custom select label', $result['ariaLabels']['selectItem']);
    }

    public function test_includes_create_form_when_provided()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
            'create_form' => [
                'action' => '/create',
                'method' => 'POST',
                'inputName' => 'exercise_name',
            ],
        ]);

        $this->assertNotNull($result['createForm']);
        $this->assertEquals('/create', $result['createForm']['action']);
    }

    public function test_create_form_is_null_when_not_provided()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
        ]);

        $this->assertNull($result['createForm']);
    }

    public function test_respects_initial_state_configuration()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
            'initial_state' => 'collapsed',
        ]);

        $this->assertEquals('collapsed', $result['initialState']);
    }

    public function test_respects_show_cancel_button_configuration()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $result = $this->service->generate($this->user->id, [
            'url_generator' => fn($ex) => '/exercise/' . $ex->id,
            'show_cancel_button' => true,
        ]);

        $this->assertTrue($result['showCancelButton']);
    }

    public function test_throws_exception_when_url_generator_not_provided()
    {
        Exercise::factory()->create(['title' => 'Bench Press']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('url_generator is required');

        $this->service->generate($this->user->id, []);
    }

    public function test_url_generator_receives_exercise_and_config()
    {
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        $capturedExercise = null;
        $capturedConfig = null;

        $this->service->generate($this->user->id, [
            'url_generator' => function($ex, $cfg) use (&$capturedExercise, &$capturedConfig) {
                $capturedExercise = $ex;
                $capturedConfig = $cfg;
                return '/test';
            },
        ]);

        $this->assertNotNull($capturedExercise);
        $this->assertEquals($exercise->id, $capturedExercise->id);
        $this->assertIsArray($capturedConfig);
        $this->assertArrayHasKey('context', $capturedConfig);
    }
}
