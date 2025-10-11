<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Factories\FoodLogFactory;
use Database\Factories\IngredientFactory;

class FoodLogDateNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->ingredient = IngredientFactory::new()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function food_log_index_shows_last_record_button_when_not_on_last_record_date()
    {
        // Create a food log entry 5 days ago
        $lastRecordDate = Carbon::now()->subDays(5);
        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $lastRecordDate,
        ]);

        // Visit today's date (not the last record date)
        $response = $this->actingAs($this->user)->get(route('food-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('food-logs.index', ['date' => $lastRecordDate->toDateString()]) . '"', false);
    }

    /** @test */
    public function food_log_index_hides_last_record_button_when_on_last_record_date()
    {
        // Create a food log entry 5 days ago
        $lastRecordDate = Carbon::now()->subDays(5);
        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $lastRecordDate,
        ]);

        // Visit the last record date
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $lastRecordDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertDontSee('Last Record');
    }

    /** @test */
    public function food_log_index_hides_last_record_button_when_no_records_exist()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Last Record');
    }

    /** @test */
    public function food_log_index_shows_today_button_when_today_not_in_date_range()
    {
        // Visit a date that's more than 1 day away from today (outside the -1 to +1 range)
        $selectedDate = Carbon::now()->subDays(5);
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Today');
        $response->assertSee('href="' . route('food-logs.index', ['date' => Carbon::today()->toDateString()]) . '"', false);
    }

    /** @test */
    public function food_log_index_hides_today_button_when_today_is_in_date_range()
    {
        // Visit yesterday (today will be in the -1 to +1 range)
        $selectedDate = Carbon::now()->subDay();
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        // Should not see "Today" as a separate button since today is in the date range
        $response->assertDontSee('<a href="' . route('food-logs.index', ['date' => Carbon::today()->toDateString()]) . '" class="date-link today-date">', false);
    }

    /** @test */
    public function food_log_index_hides_today_button_when_viewing_today()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.index'));

        $response->assertStatus(200);
        // Should not see "Today" as a separate button when viewing today
        $response->assertDontSee('<a href="' . route('food-logs.index', ['date' => Carbon::today()->toDateString()]) . '" class="date-link today-date">', false);
    }

    /** @test */
    public function food_log_index_shows_correct_date_range_buttons()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        
        // Should show -1, 0, +1 days relative to selected date
        $response->assertSee('Tue Jan 14'); // -1 day
        $response->assertSee('Wed Jan 15'); // selected date
        $response->assertSee('Thu Jan 16'); // +1 day
    }

    /** @test */
    public function food_log_index_marks_selected_date_as_active()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        // Check for active class in the date link
        $response->assertSee('date-link active', false);
    }

    /** @test */
    public function food_log_index_marks_today_with_special_class_when_in_range()
    {
        // Visit yesterday so today appears in the date range
        $selectedDate = Carbon::today()->subDay();
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('today-date', false);
    }

    /** @test */
    public function last_record_button_navigates_to_correct_date()
    {
        // Create a food log entry 3 days ago
        $lastRecordDate = Carbon::now()->subDays(3);
        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $lastRecordDate,
        ]);

        // Click the last record button (simulate by visiting the URL it would link to)
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $lastRecordDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Food Log Entries for ' . $lastRecordDate->format('M d, Y'));
    }

    /** @test */
    public function today_button_navigates_to_today()
    {
        // Visit a date far from today
        $selectedDate = Carbon::now()->subDays(10);
        
        // First verify Today button appears
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));
        $response->assertSee('Today');

        // Click the Today button (simulate by visiting today's URL)
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => Carbon::today()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Food Log Entries for ' . Carbon::today()->format('M d, Y'));
    }

    /** @test */
    public function date_navigation_works_with_multiple_food_logs()
    {
        // Create food logs on different dates
        $date1 = Carbon::parse('2025-01-10');
        $date2 = Carbon::parse('2025-01-15');
        $date3 = Carbon::parse('2025-01-20');

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $date1,
        ]);

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $date2,
        ]);

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $date3,
        ]);

        // Visit the middle date
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $date2->toDateString()]));

        $response->assertStatus(200);
        
        // Should show the most recent record (date3) as last record
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('food-logs.index', ['date' => $date3->toDateString()]) . '"', false);
    }

    /** @test */
    public function last_record_date_is_calculated_correctly_for_user()
    {
        $otherUser = User::factory()->create();
        $otherIngredient = IngredientFactory::new()->create(['user_id' => $otherUser->id]);

        // Create food logs for both users
        $userLastDate = Carbon::parse('2025-01-15');
        $otherUserLastDate = Carbon::parse('2025-01-20'); // More recent

        FoodLogFactory::new()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $this->ingredient->id,
            'logged_at' => $userLastDate,
        ]);

        FoodLogFactory::new()->create([
            'user_id' => $otherUser->id,
            'ingredient_id' => $otherIngredient->id,
            'logged_at' => $otherUserLastDate,
        ]);

        // Visit as first user - should only see their own last record date
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => Carbon::parse('2025-01-10')->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('food-logs.index', ['date' => $userLastDate->toDateString()]) . '"', false);
        $response->assertDontSee('href="' . route('food-logs.index', ['date' => $otherUserLastDate->toDateString()]) . '"', false);
    }

    /** @test */
    public function date_picker_shows_selected_date()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('food-logs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('value="' . $selectedDate->format('Y-m-d') . '"', false);
    }

    /** @test */
    public function food_log_index_defaults_to_today_when_no_date_provided()
    {
        $response = $this->actingAs($this->user)->get(route('food-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Food Log Entries for ' . Carbon::today()->format('M d, Y'));
        $response->assertSee('value="' . Carbon::today()->format('Y-m-d') . '"', false);
    }
}