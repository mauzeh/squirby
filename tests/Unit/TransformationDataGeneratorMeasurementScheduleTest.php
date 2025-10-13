<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use Carbon\Carbon;

class TransformationDataGeneratorMeasurementScheduleTest extends TestCase
{
    private TransformationDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
    }

    public function test_generates_measurement_schedule_with_correct_duration()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 12;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        // Should have measurements spanning the full 12 weeks
        $this->assertNotEmpty($schedule);
        
        $firstMeasurement = collect($schedule)->min('date');
        $lastMeasurement = collect($schedule)->max('date');
        
        $this->assertTrue($firstMeasurement->gte($startDate));
        $this->assertTrue($lastMeasurement->lt($startDate->copy()->addWeeks($weeks)));
    }

    public function test_generates_approximately_three_measurements_per_week()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 12;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks, 3);
        
        // Should average around 3 measurements per week (36 total ± some variation)
        $totalMeasurements = count($schedule);
        $expectedRange = [24, 48]; // Allow for significant variation
        
        $this->assertGreaterThanOrEqual($expectedRange[0], $totalMeasurements);
        $this->assertLessThanOrEqual($expectedRange[1], $totalMeasurements);
    }

    public function test_varies_days_of_week_to_avoid_patterns()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 12;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        // Collect all days of the week used
        $daysOfWeek = collect($schedule)->pluck('day_of_week')->unique()->sort()->values();
        
        // Should use multiple different days of the week (at least 4 different days)
        $this->assertGreaterThanOrEqual(4, $daysOfWeek->count());
        
        // Should include both weekdays and weekends
        $hasWeekdays = $daysOfWeek->intersect([1, 2, 3, 4, 5])->isNotEmpty();
        $hasWeekends = $daysOfWeek->intersect([0, 6])->isNotEmpty();
        
        $this->assertTrue($hasWeekdays, 'Should include weekday measurements');
        $this->assertTrue($hasWeekends, 'Should include weekend measurements');
    }

    public function test_includes_different_measurement_types()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 12;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        // All measurements should include weight
        $weightMeasurements = collect($schedule)->where('measure_weight', true);
        $this->assertEquals(count($schedule), $weightMeasurements->count());
        
        // Should have some waist measurements (but not all)
        $waistMeasurements = collect($schedule)->where('measure_waist', true);
        $this->assertGreaterThan(0, $waistMeasurements->count());
        $this->assertLessThan(count($schedule), $waistMeasurements->count());
        
        // Should have some body fat measurements (but fewer than waist)
        $bodyFatMeasurements = collect($schedule)->where('measure_body_fat', true);
        $this->assertGreaterThan(0, $bodyFatMeasurements->count());
        $this->assertLessThan($waistMeasurements->count(), $bodyFatMeasurements->count());
    }

    public function test_measurement_schedule_has_proper_structure()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 4;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        foreach ($schedule as $measurement) {
            // Check required fields exist
            $this->assertArrayHasKey('date', $measurement);
            $this->assertArrayHasKey('week', $measurement);
            $this->assertArrayHasKey('day_of_week', $measurement);
            $this->assertArrayHasKey('measure_weight', $measurement);
            $this->assertArrayHasKey('measure_waist', $measurement);
            $this->assertArrayHasKey('measure_body_fat', $measurement);
            $this->assertArrayHasKey('measure_muscle_mass', $measurement);
            $this->assertArrayHasKey('measure_additional', $measurement);
            
            // Check data types
            $this->assertInstanceOf(Carbon::class, $measurement['date']);
            $this->assertIsInt($measurement['week']);
            $this->assertIsInt($measurement['day_of_week']);
            $this->assertIsBool($measurement['measure_weight']);
            $this->assertIsBool($measurement['measure_waist']);
            $this->assertIsBool($measurement['measure_body_fat']);
            $this->assertIsBool($measurement['measure_muscle_mass']);
            $this->assertIsBool($measurement['measure_additional']);
            
            // Check value ranges
            $this->assertGreaterThanOrEqual(1, $measurement['week']);
            $this->assertLessThanOrEqual($weeks, $measurement['week']);
            $this->assertGreaterThanOrEqual(0, $measurement['day_of_week']);
            $this->assertLessThanOrEqual(6, $measurement['day_of_week']);
        }
    }

    public function test_measurements_are_chronologically_ordered()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 8;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        $dates = collect($schedule)->pluck('date');
        $sortedDates = $dates->sort();
        
        // Dates should already be in chronological order
        $this->assertEquals($sortedDates->values()->toArray(), $dates->toArray());
    }

    public function test_weekend_measurements_have_higher_probability_for_detailed_metrics()
    {
        // This test runs multiple times to check probability patterns
        $weekendWaistCount = 0;
        $weekdayWaistCount = 0;
        $totalWeekendMeasurements = 0;
        $totalWeekdayMeasurements = 0;
        
        // Run multiple iterations to get statistical significance
        for ($i = 0; $i < 10; $i++) {
            $startDate = Carbon::parse('2024-01-01');
            $weeks = 12;
            
            $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
            
            foreach ($schedule as $measurement) {
                $isWeekend = in_array($measurement['day_of_week'], [0, 6]);
                
                if ($isWeekend) {
                    $totalWeekendMeasurements++;
                    if ($measurement['measure_waist']) {
                        $weekendWaistCount++;
                    }
                } else {
                    $totalWeekdayMeasurements++;
                    if ($measurement['measure_waist']) {
                        $weekdayWaistCount++;
                    }
                }
            }
        }
        
        // Calculate percentages
        $weekendWaistPercentage = $totalWeekendMeasurements > 0 ? $weekendWaistCount / $totalWeekendMeasurements : 0;
        $weekdayWaistPercentage = $totalWeekdayMeasurements > 0 ? $weekdayWaistCount / $totalWeekdayMeasurements : 0;
        
        // Weekend measurements should have higher probability of waist measurements
        $this->assertGreaterThan($weekdayWaistPercentage, $weekendWaistPercentage);
    }

    public function test_custom_average_measurements_per_week()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 8;
        $customAverage = 2;
        
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks, $customAverage);
        
        $totalMeasurements = count($schedule);
        $expectedRange = [12, 32]; // 8 weeks * 2 avg ± variation (allow more flexibility)
        
        $this->assertGreaterThanOrEqual($expectedRange[0], $totalMeasurements);
        $this->assertLessThanOrEqual($expectedRange[1], $totalMeasurements);
    }
}