<?php

namespace Tests\Unit\Services;

use App\Services\DateTitleService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for DateTitleService
 * 
 * Tests the date title generation logic with various scenarios including:
 * - Immediate dates (today, yesterday, tomorrow)
 * - Relative week dates (this week, last week, next week)
 * - Distant dates outside the 3-week window
 * - Edge cases and boundary conditions
 */
class DateTitleServiceTest extends TestCase
{
    private DateTitleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DateTitleService();
    }

    #[Test]
    public function it_generates_title_for_today()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        $result = $this->service->generateDateTitle($today, $today);
        
        $this->assertEquals([
            'main' => 'Today',
            'subtitle' => 'Jan 15, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_yesterday()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        $yesterday = Carbon::parse('2024-01-14'); // Sunday
        $result = $this->service->generateDateTitle($yesterday, $today);
        
        $this->assertEquals([
            'main' => 'Yesterday',
            'subtitle' => 'Jan 14, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_tomorrow()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        $tomorrow = Carbon::parse('2024-01-16'); // Tuesday
        $result = $this->service->generateDateTitle($tomorrow, $today);
        
        $this->assertEquals([
            'main' => 'Tomorrow',
            'subtitle' => 'Jan 16, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_this_week_dates()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        
        // Test Wednesday of the same week (future)
        $thisWednesday = Carbon::parse('2024-01-17');
        $result = $this->service->generateDateTitle($thisWednesday, $today);
        
        $this->assertEquals([
            'main' => 'This Wednesday',
            'subtitle' => 'Jan 17, 2024'
        ], $result);
        
        // Test Friday of the same week (future)
        $thisFriday = Carbon::parse('2024-01-19');
        $result = $this->service->generateDateTitle($thisFriday, $today);
        
        $this->assertEquals([
            'main' => 'This Friday',
            'subtitle' => 'Jan 19, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_past_days_in_current_week()
    {
        $today = Carbon::parse('2024-01-19'); // Friday
        
        // Test Wednesday of the same week (past, but not yesterday)
        $pastWednesday = Carbon::parse('2024-01-17');
        $result = $this->service->generateDateTitle($pastWednesday, $today);
        
        $this->assertEquals([
            'main' => 'Last Wednesday',
            'subtitle' => 'Jan 17, 2024'
        ], $result);
        
        // Test Monday of the same week (past)
        $pastMonday = Carbon::parse('2024-01-15');
        $result = $this->service->generateDateTitle($pastMonday, $today);
        
        $this->assertEquals([
            'main' => 'Last Monday',
            'subtitle' => 'Jan 15, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_last_week_dates()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        
        // Test Monday of last week
        $lastMonday = Carbon::parse('2024-01-08');
        $result = $this->service->generateDateTitle($lastMonday, $today);
        
        $this->assertEquals([
            'main' => 'Last Monday',
            'subtitle' => 'Jan 08, 2024'
        ], $result);
        
        // Test Friday of last week
        $lastFriday = Carbon::parse('2024-01-12');
        $result = $this->service->generateDateTitle($lastFriday, $today);
        
        $this->assertEquals([
            'main' => 'Last Friday',
            'subtitle' => 'Jan 12, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_next_week_dates()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        
        // Test Tuesday of next week
        $nextTuesday = Carbon::parse('2024-01-23');
        $result = $this->service->generateDateTitle($nextTuesday, $today);
        
        $this->assertEquals([
            'main' => 'Next Tuesday',
            'subtitle' => 'Jan 23, 2024'
        ], $result);
        
        // Test Saturday of next week
        $nextSaturday = Carbon::parse('2024-01-27');
        $result = $this->service->generateDateTitle($nextSaturday, $today);
        
        $this->assertEquals([
            'main' => 'Next Saturday',
            'subtitle' => 'Jan 27, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_distant_past_dates()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        $distantPast = Carbon::parse('2023-12-01'); // Friday, more than 3 weeks ago
        $result = $this->service->generateDateTitle($distantPast, $today);
        
        $this->assertEquals([
            'main' => 'Fri, Dec 01, 2023',
            'subtitle' => null
        ], $result);
    }

    /** @test */
    public function it_generates_title_for_distant_future_dates()
    {
        $today = Carbon::parse('2024-01-15'); // Monday
        $distantFuture = Carbon::parse('2024-03-01'); // Friday, more than 3 weeks in future
        $result = $this->service->generateDateTitle($distantFuture, $today);
        
        $this->assertEquals([
            'main' => 'Fri, Mar 01, 2024',
            'subtitle' => null
        ], $result);
    }

    /** @test */
    public function it_handles_week_boundaries_correctly()
    {
        // Test with today being Sunday (end of week)
        $today = Carbon::parse('2024-01-21'); // Sunday
        
        // Yesterday (Saturday) should be "Yesterday", not "This Saturday"
        $yesterday = Carbon::parse('2024-01-20');
        $result = $this->service->generateDateTitle($yesterday, $today);
        
        $this->assertEquals([
            'main' => 'Yesterday',
            'subtitle' => 'Jan 20, 2024'
        ], $result);
        
        // Tomorrow (Monday) should be "Tomorrow", not "Next Monday"
        $tomorrow = Carbon::parse('2024-01-22');
        $result = $this->service->generateDateTitle($tomorrow, $today);
        
        $this->assertEquals([
            'main' => 'Tomorrow',
            'subtitle' => 'Jan 22, 2024'
        ], $result);
    }

    /** @test */
    public function it_uses_today_as_default_reference_date()
    {
        // Mock Carbon::today() by using a specific date
        Carbon::setTestNow('2024-01-15');
        
        $selectedDate = Carbon::parse('2024-01-16');
        $result = $this->service->generateDateTitle($selectedDate);
        
        $this->assertEquals([
            'main' => 'Tomorrow',
            'subtitle' => 'Jan 16, 2024'
        ], $result);
        
        // Clean up
        Carbon::setTestNow();
    }

    /** @test */
    public function it_handles_year_boundaries_correctly()
    {
        $today = Carbon::parse('2024-01-02'); // Tuesday, early in year
        
        // Last week should cross year boundary
        $lastYearDate = Carbon::parse('2023-12-26'); // Tuesday of previous week
        $result = $this->service->generateDateTitle($lastYearDate, $today);
        
        $this->assertEquals([
            'main' => 'Last Tuesday',
            'subtitle' => 'Dec 26, 2023'
        ], $result);
    }

    /** @test */
    public function it_handles_leap_year_dates()
    {
        $today = Carbon::parse('2024-02-28'); // Wednesday in leap year
        $leapDay = Carbon::parse('2024-02-29'); // Thursday (leap day)
        
        $result = $this->service->generateDateTitle($leapDay, $today);
        
        $this->assertEquals([
            'main' => 'Tomorrow',
            'subtitle' => 'Feb 29, 2024'
        ], $result);
    }

    /** @test */
    public function it_handles_different_timezones_consistently()
    {
        // Test that the service works consistently regardless of timezone
        $today = Carbon::parse('2024-01-15 12:00:00', 'UTC');
        $tomorrow = Carbon::parse('2024-01-16 12:00:00', 'UTC');
        
        $result = $this->service->generateDateTitle($tomorrow, $today);
        
        $this->assertEquals([
            'main' => 'Tomorrow',
            'subtitle' => 'Jan 16, 2024'
        ], $result);
    }

    /** @test */
    public function it_generates_consistent_format_for_all_months()
    {
        $today = Carbon::parse('2024-06-15'); // Saturday
        
        // Test different months
        $testDates = [
            '2024-01-15' => 'Jan 15, 2024',
            '2024-02-15' => 'Feb 15, 2024',
            '2024-03-15' => 'Mar 15, 2024',
            '2024-12-15' => 'Dec 15, 2024',
        ];
        
        foreach ($testDates as $dateString => $expectedFormat) {
            $testDate = Carbon::parse($dateString);
            $result = $this->service->generateDateTitle($testDate, $today);
            
            // For distant dates, check the main title contains the expected format
            $this->assertStringContainsString($expectedFormat, $result['main']);
        }
    }
}