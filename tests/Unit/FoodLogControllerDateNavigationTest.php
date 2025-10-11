<?php

namespace Tests\Unit;

use App\Http\Controllers\FoodLogController;
use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\NutritionService;
use App\Services\TsvImporterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Database\Factories\FoodLogFactory;
use Database\Factories\IngredientFactory;

class FoodLogControllerDateNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $user;
    protected $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $nutritionService = $this->createMock(NutritionService::class);
        $tsvImporterService = $this->createMock(TsvImporterService::class);
        
        $this->controller = new FoodLogController($nutritionService, $tsvImporterService);
        $this->user = User::factory()->create();
        $this->ingredient = IngredientFactory::new()->create(['user_id' => $this->user->id]);
        
        $this->actingAs($this->user);
    }

    /** @test */
    public function index_returns_null_last_record_date_when_no_food_logs_exist()
    {
        $request = new Request();
        
        $response = $this->controller->index($request);
        
        $this->assertNull($response->getData()['lastRecordDate']);
    }

    /** @test */
    public function index_returns_correct_last_record_date_when_food_logs_exist()
    {
        // Create food logs on different dates
        $olderDate = Carbon::parse('2025-01-10');
        $newerDate = Carbon::parse('2025-01-15');

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $olderDate,
        ]);

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $newerDate,
        ]);

        $request = new Request();
        
        $response = $this->controller->index($request);
        
        $this->assertEquals($newerDate->toDateString(), $response->getData()['lastRecordDate']);
    }

    /** @test */
    public function index_returns_last_record_date_only_for_authenticated_user()
    {
        $otherUser = User::factory()->create();
        $otherIngredient = IngredientFactory::new()->create(['user_id' => $otherUser->id]);

        // Create food logs for both users
        $userDate = Carbon::parse('2025-01-10');
        $otherUserDate = Carbon::parse('2025-01-15'); // More recent

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $userDate,
        ]);

        FoodLogFactory::new()->create([
            'user_id' => $otherUser->id,
            'ingredient_id' => $otherIngredient->id,
            'logged_at' => $otherUserDate,
        ]);

        $request = new Request();
        
        $response = $this->controller->index($request);
        
        // Should return the authenticated user's last record date, not the other user's
        $this->assertEquals($userDate->toDateString(), $response->getData()['lastRecordDate']);
    }

    /** @test */
    public function index_uses_provided_date_parameter()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        $request = new Request(['date' => $selectedDate->toDateString()]);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals($selectedDate->toDateString(), $response->getData()['selectedDate']->toDateString());
    }

    /** @test */
    public function index_defaults_to_today_when_no_date_parameter()
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15'));
        
        $request = new Request();
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(Carbon::today()->toDateString(), $response->getData()['selectedDate']->toDateString());
        
        Carbon::setTestNow(); // Reset
    }

    /** @test */
    public function index_filters_food_logs_by_selected_date()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        $otherDate = Carbon::parse('2025-01-16');

        // Create food logs on different dates
        $logOnSelectedDate = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $selectedDate->setTime(10, 0),
        ]);

        $logOnOtherDate = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $otherDate->setTime(10, 0),
        ]);

        $request = new Request(['date' => $selectedDate->toDateString()]);
        
        $response = $this->controller->index($request);
        $foodLogs = $response->getData()['foodLogs'];
        
        $this->assertCount(1, $foodLogs);
        $this->assertEquals($logOnSelectedDate->id, $foodLogs->first()->id);
    }

    /** @test */
    public function index_only_returns_food_logs_for_authenticated_user()
    {
        $otherUser = User::factory()->create();
        $otherIngredient = IngredientFactory::new()->create(['user_id' => $otherUser->id]);
        $selectedDate = Carbon::parse('2025-01-15');

        // Create food logs for both users on the same date
        $userLog = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $selectedDate->setTime(10, 0),
        ]);

        $otherUserLog = FoodLogFactory::new()->create([
            'user_id' => $otherUser->id,
            'ingredient_id' => $otherIngredient->id,
            'logged_at' => $selectedDate->setTime(11, 0),
        ]);

        $request = new Request(['date' => $selectedDate->toDateString()]);
        
        $response = $this->controller->index($request);
        $foodLogs = $response->getData()['foodLogs'];
        
        $this->assertCount(1, $foodLogs);
        $this->assertEquals($userLog->id, $foodLogs->first()->id);
    }

    /** @test */
    public function index_passes_last_record_date_to_view()
    {
        $lastRecordDate = Carbon::parse('2025-01-10');
        
        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $lastRecordDate,
        ]);

        $request = new Request();
        
        $response = $this->controller->index($request);
        $viewData = $response->getData();
        
        $this->assertArrayHasKey('lastRecordDate', $viewData);
        $this->assertEquals($lastRecordDate->toDateString(), $viewData['lastRecordDate']);
    }

    /** @test */
    public function index_handles_multiple_food_logs_on_same_day()
    {
        $selectedDate = Carbon::parse('2025-01-15');

        // Create multiple food logs on the same date
        $log1 = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $selectedDate->copy()->setTime(8, 0),
        ]);

        $log2 = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $selectedDate->copy()->setTime(12, 0),
        ]);

        $log3 = FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $selectedDate->copy()->setTime(18, 0),
        ]);

        $request = new Request(['date' => $selectedDate->toDateString()]);
        
        $response = $this->controller->index($request);
        $foodLogs = $response->getData()['foodLogs'];
        
        $this->assertCount(3, $foodLogs);
        
        // Should be ordered by logged_at desc, then by ingredient name asc
        $this->assertEquals($log3->id, $foodLogs->get(0)->id); // 18:00
        $this->assertEquals($log2->id, $foodLogs->get(1)->id); // 12:00
        $this->assertEquals($log1->id, $foodLogs->get(2)->id); // 08:00
    }
}