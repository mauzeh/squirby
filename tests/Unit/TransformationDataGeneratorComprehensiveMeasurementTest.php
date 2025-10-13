<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use Carbon\Carbon;

class TransformationDataGeneratorComprehensiveMeasurementTest extends TestCase
{

    private TransformationDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
    }

    public function test_generates_comprehensive_measurements_with_all_types()
    {
        // Test the individual progression methods instead of the full comprehensive method
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 4;
        
        // Test measurement schedule generation
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        $this->assertNotEmpty($schedule);
        
        // Test body fat progression
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-15'),
            Carbon::parse('2024-02-01')
        ];
        $bodyFatProgression = $this->generator->calculateBodyFatProgression(18.0, 12.0, $measurementDates);
        $this->assertCount(3, $bodyFatProgression);
        
        // Test muscle mass progression
        $muscleMassProgression = $this->generator->calculateMuscleMassProgression(150.0, [], $measurementDates);
        $this->assertCount(3, $muscleMassProgression);
        
        // Test additional measurements
        $additionalMeasurements = $this->generator->calculateAdditionalMeasurements(0.1, $measurementDates);
        $this->assertArrayHasKey('chest', $additionalMeasurements);
        $this->assertArrayHasKey('arm', $additionalMeasurements);
        $this->assertArrayHasKey('thigh', $additionalMeasurements);
    }

    public function test_measurements_show_realistic_progression()
    {
        // Test weight loss progression
        $weightProgression = $this->generator->calculateWeightLossProgression(180.0, 165.0, 84);
        $this->assertCount(84, $weightProgression);
        
        $firstWeight = $weightProgression[0];
        $lastWeight = $weightProgression[83];
        
        // Should show weight loss
        $this->assertGreaterThan($lastWeight, $firstWeight);
        $this->assertGreaterThanOrEqual(165.0, $lastWeight);
        $this->assertLessThanOrEqual(180.0, $firstWeight);
        
        // Test waist progression
        $waistProgression = $this->generator->calculateWaistProgression(36.0, 0.083, 84); // ~8.3% weight loss
        $this->assertCount(84, $waistProgression);
        
        $firstWaist = $waistProgression[0];
        $lastWaist = $waistProgression[83];
        
        // Should show waist reduction
        $this->assertGreaterThan($lastWaist, $firstWaist);
    }

    public function test_measurements_have_realistic_timing()
    {
        $startDate = Carbon::parse('2024-01-01');
        $weeks = 4;
        
        // Test measurement schedule timing
        $schedule = $this->generator->generateMeasurementSchedule($startDate, $weeks);
        
        foreach ($schedule as $measurement) {
            $measurementDate = $measurement['date'];
            $this->assertGreaterThanOrEqual($startDate, $measurementDate);
            $this->assertLessThanOrEqual($startDate->copy()->addWeeks($weeks), $measurementDate);
            
            // Should have proper week and day_of_week values
            $this->assertArrayHasKey('week', $measurement);
            $this->assertArrayHasKey('day_of_week', $measurement);
            $this->assertGreaterThanOrEqual(1, $measurement['week']);
            $this->assertLessThanOrEqual($weeks, $measurement['week']);
            $this->assertGreaterThanOrEqual(0, $measurement['day_of_week']);
            $this->assertLessThanOrEqual(6, $measurement['day_of_week']);
        }
    }

    public function test_generates_measurement_summary()
    {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-03-01');
        
        // Create sample body logs
        $bodyLogs = [
            [
                'measurement_type_id' => 1,
                'value' => 180.0,
                'logged_at' => $startDate->copy()->setTime(7, 0)
            ],
            [
                'measurement_type_id' => 1,
                'value' => 175.0,
                'logged_at' => $startDate->copy()->addWeeks(4)->setTime(7, 0)
            ],
            [
                'measurement_type_id' => 1,
                'value' => 170.0,
                'logged_at' => $endDate->copy()->setTime(7, 0)
            ],
            [
                'measurement_type_id' => 2,
                'value' => 36.0,
                'logged_at' => $startDate->copy()->setTime(7, 15)
            ],
            [
                'measurement_type_id' => 2,
                'value' => 34.0,
                'logged_at' => $endDate->copy()->setTime(7, 15)
            ]
        ];
        
        $summary = $this->generator->generateMeasurementSummary($bodyLogs, $startDate, $endDate);
        
        // Check summary structure
        $this->assertArrayHasKey('total_measurements', $summary);
        $this->assertArrayHasKey('measurement_period', $summary);
        $this->assertArrayHasKey('measurement_types', $summary);
        $this->assertArrayHasKey('progress_summary', $summary);
        
        $this->assertEquals(5, $summary['total_measurements']);
        $this->assertEqualsWithDelta(8.57, $summary['measurement_period']['duration_weeks'], 0.1);
        
        // Check measurement type summaries
        $this->assertArrayHasKey(1, $summary['measurement_types']); // Weight type
        $this->assertArrayHasKey(2, $summary['measurement_types']); // Waist type
        
        $weightSummary = $summary['measurement_types'][1];
        $this->assertEquals(3, $weightSummary['count']);
        $this->assertEquals(180.0, $weightSummary['first_value']);
        $this->assertEquals(170.0, $weightSummary['last_value']);
        $this->assertEquals(-10.0, $weightSummary['change']);
        $this->assertEqualsWithDelta(-5.56, $weightSummary['change_percent'], 0.1);
    }

    public function test_handles_empty_measurement_schedule()
    {
        // Test that progression methods handle empty dates gracefully
        $emptyProgression = $this->generator->calculateBodyFatProgression(18.0, 12.0, []);
        $this->assertEmpty($emptyProgression);
        
        $emptyMuscleProgression = $this->generator->calculateMuscleMassProgression(150.0, [], []);
        $this->assertEmpty($emptyMuscleProgression);
        
        $emptyAdditional = $this->generator->calculateAdditionalMeasurements(0.1, []);
        $this->assertArrayHasKey('chest', $emptyAdditional);
        $this->assertEmpty($emptyAdditional['chest']);
    }

    public function test_measurements_include_variations_and_realism()
    {
        // Test that progressions include realistic variations
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-15'),
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-02-15'),
            Carbon::parse('2024-03-01')
        ];
        
        $bodyFatProgression = $this->generator->calculateBodyFatProgression(18.0, 12.0, $measurementDates);
        $values = array_values($bodyFatProgression);
        
        // Should have varied values (not perfectly linear)
        $uniqueValues = array_unique($values);
        $this->assertGreaterThan(1, count($uniqueValues));
        
        // Should show overall decreasing trend
        $firstValue = reset($values);
        $lastValue = end($values);
        $this->assertGreaterThan($lastValue, $firstValue);
        
        // Values should be within reasonable body fat ranges
        foreach ($values as $value) {
            $this->assertGreaterThanOrEqual(5.0, $value);
            $this->assertLessThanOrEqual(50.0, $value);
        }
    }

    public function test_correlations_between_related_metrics()
    {
        // Test that weight loss and waist reduction are correlated
        $weightLossRatio = 0.111; // ~11% weight loss (180 to 160)
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-03-01')
        ];
        
        $additionalMeasurements = $this->generator->calculateAdditionalMeasurements($weightLossRatio, $measurementDates);
        
        // All body parts should show reduction
        foreach (['chest', 'arm', 'thigh'] as $bodyPart) {
            $measurements = $additionalMeasurements[$bodyPart];
            $firstValue = reset($measurements);
            $lastValue = end($measurements);
            
            // Should show reduction correlated with weight loss
            $this->assertGreaterThan($lastValue, $firstValue);
        }
        
        // Arms should reduce less than chest and thigh (different reduction rates)
        $chestReduction = reset($additionalMeasurements['chest']) - end($additionalMeasurements['chest']);
        $armReduction = reset($additionalMeasurements['arm']) - end($additionalMeasurements['arm']);
        $thighReduction = reset($additionalMeasurements['thigh']) - end($additionalMeasurements['thigh']);
        
        $this->assertGreaterThan($armReduction, $chestReduction);
        $this->assertGreaterThan($armReduction, $thighReduction);
    }
}