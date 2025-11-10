<?php

namespace Tests\Unit\Services;

use App\Models\FoodLog;
use App\Models\MobileLiftForm;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Exercise;
use App\Services\DateNavigationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateNavigationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DateNavigationService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DateNavigationService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function parseSelectedDate_returns_provided_date_when_given()
    {
        $inputDate = '2025-01-15';
        $result = $this->service->parseSelectedDate($inputDate);
        
        $this->assertEquals('2025-01-15', $result->toDateString());
    }

    /** @test */
    public function parseSelectedDate_returns_today_when_null_provided()
    {
        Carbon::setTestNow('2025-01-20');
        
        $result = $this->service->parseSelectedDate(null);
        
        $this->assertEquals('2025-01-20', $result->toDateString());
        
        Carbon::setTestNow();
    }

    /** @test */
    public function getNavigationData_generates_correct_three_day_window()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertCount(3, $result['navigationDates']);
        
        // Check the three dates
        $this->assertEquals('2025-01-14', $result['navigationDates'][0]['dateString']);
        $this->assertEquals('2025-01-15', $result['navigationDates'][1]['dateString']);
        $this->assertEquals('2025-01-16', $result['navigationDates'][2]['dateString']);
        
        // Check selected date is marked as active
        $this->assertFalse($result['navigationDates'][0]['isSelected']);
        $this->assertTrue($result['navigationDates'][1]['isSelected']);
        $this->assertFalse($result['navigationDates'][2]['isSelected']);
    }

    /** @test */
    public function getNavigationData_marks_today_correctly_when_in_range()
    {
        Carbon::setTestNow('2025-01-15');
        $selectedDate = Carbon::parse('2025-01-14'); // Yesterday
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        // Today (2025-01-15) should be in the range and marked as today
        $this->assertTrue($result['todayInRange']);
        $this->assertFalse($result['showTodayButton']);
        
        // Check that today is marked correctly in navigation dates
        $todayNavDate = collect($result['navigationDates'])->firstWhere('isToday', true);
        $this->assertNotNull($todayNavDate);
        $this->assertEquals('Today', $todayNavDate['label']);
        
        Carbon::setTestNow();
    }

    /** @test */
    public function getNavigationData_shows_today_button_when_not_in_range()
    {
        Carbon::setTestNow('2025-01-15');
        $selectedDate = Carbon::parse('2025-01-10'); // 5 days ago
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertFalse($result['todayInRange']);
        $this->assertTrue($result['showTodayButton']);
        $this->assertEquals(route('food-logs.index', ['date' => '2025-01-15']), $result['todayUrl']);
        
        Carbon::setTestNow();
    }

    /** @test */
    public function getNavigationData_finds_last_record_date_for_foodlog_model()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);
        
        // Create food logs on different dates
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'logged_at' => Carbon::parse('2025-01-10 10:00:00'),
        ]);
        
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'logged_at' => Carbon::parse('2025-01-15 15:00:00'), // Most recent
        ]);
        
        $selectedDate = Carbon::parse('2025-01-12');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertEquals('2025-01-15', $result['lastRecordDate']);
        $this->assertTrue($result['showLastRecordButton']);
        $this->assertEquals(route('food-logs.index', ['date' => '2025-01-15']), $result['lastRecordUrl']);
    }

    /** @test */
    public function getNavigationData_finds_last_record_date_for_mobile_lift_form_model()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Create mobile lift forms on different dates
        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse('2025-01-10'),
        ]);
        
        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse('2025-01-15'), // Most recent
        ]);
        
        $selectedDate = Carbon::parse('2025-01-12');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            MobileLiftForm::class,
            $this->user->id,
            'mobile-entry.lifts'
        );
        
        $this->assertEquals('2025-01-15', $result['lastRecordDate']);
        $this->assertTrue($result['showLastRecordButton']);
        $this->assertEquals(route('mobile-entry.lifts', ['date' => '2025-01-15']), $result['lastRecordUrl']);
    }

    /** @test */
    public function getNavigationData_hides_last_record_button_when_on_last_record_date()
    {
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);
        
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'logged_at' => Carbon::parse('2025-01-15 10:00:00'),
        ]);
        
        $selectedDate = Carbon::parse('2025-01-15'); // Same as last record date
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertEquals('2025-01-15', $result['lastRecordDate']);
        $this->assertFalse($result['showLastRecordButton']);
    }

    /** @test */
    public function getNavigationData_returns_null_last_record_when_no_records_exist()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertNull($result['lastRecordDate']);
        $this->assertFalse($result['showLastRecordButton']);
        $this->assertNull($result['lastRecordUrl']);
    }

    /** @test */
    public function getNavigationData_only_considers_records_for_specified_user()
    {
        $otherUser = User::factory()->create();
        $ingredient = Ingredient::factory()->create(['user_id' => $this->user->id]);
        $otherIngredient = Ingredient::factory()->create(['user_id' => $otherUser->id]);
        
        // Create food log for current user
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'logged_at' => Carbon::parse('2025-01-10 10:00:00'),
        ]);
        
        // Create more recent food log for other user
        FoodLog::factory()->create([
            'user_id' => $otherUser->id,
            'ingredient_id' => $otherIngredient->id,
            'logged_at' => Carbon::parse('2025-01-15 10:00:00'),
        ]);
        
        $selectedDate = Carbon::parse('2025-01-12');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        // Should only see current user's last record date
        $this->assertEquals('2025-01-10', $result['lastRecordDate']);
    }

    /** @test */
    public function getNavigationData_generates_correct_urls()
    {
        Carbon::setTestNow('2025-01-15');
        $selectedDate = Carbon::parse('2025-01-15');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertEquals('food-logs.index', $result['routeName']);
        $this->assertEquals(route('food-logs.index', ['date' => '2025-01-15']), $result['todayUrl']);
        
        // Check navigation date URLs
        foreach ($result['navigationDates'] as $navDate) {
            $expectedUrl = route('food-logs.index', ['date' => $navDate['dateString']]);
            $this->assertEquals($expectedUrl, $navDate['url']);
        }
        
        Carbon::setTestNow();
    }

    /** @test */
    public function getNavigationData_formats_labels_correctly()
    {
        Carbon::setTestNow('2025-01-15');
        $selectedDate = Carbon::parse('2025-01-14'); // Yesterday
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            FoodLog::class,
            $this->user->id,
            'food-logs.index'
        );
        
        // Check labels - the navigation dates are relative to selected date, not today
        $this->assertEquals('Mon Jan 13', $result['navigationDates'][0]['label']); // Selected date -1
        $this->assertEquals('Tue Jan 14', $result['navigationDates'][1]['label']); // Selected date
        $this->assertEquals('Today', $result['navigationDates'][2]['label']); // Selected date +1 (which is today)
        
        Carbon::setTestNow();
    }

    /** @test */
    public function getNavigationData_handles_nonexistent_model_class()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $result = $this->service->getNavigationData(
            $selectedDate,
            'NonExistentModel',
            $this->user->id,
            'food-logs.index'
        );
        
        $this->assertNull($result['lastRecordDate']);
        $this->assertFalse($result['showLastRecordButton']);
        $this->assertNull($result['lastRecordUrl']);
    }
}