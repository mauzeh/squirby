<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use Carbon\Carbon;

class TransformationDataGeneratorMeasurementProgressionTest extends TestCase
{
    private TransformationDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
    }

    public function test_calculates_body_fat_progression_correctly()
    {
        $startBodyFat = 18.0;
        $targetBodyFat = 12.0;
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-15'),
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-02-15'),
            Carbon::parse('2024-03-01')
        ];
        
        $progression = $this->generator->calculateBodyFatProgression($startBodyFat, $targetBodyFat, $measurementDates);
        
        // Should have progression for each date
        $this->assertCount(5, $progression);
        
        // First measurement should be close to starting value
        $firstValue = $progression['2024-01-01'];
        $this->assertGreaterThan(17.0, $firstValue);
        $this->assertLessThanOrEqual(18.0, $firstValue);
        
        // Last measurement should be close to target
        $lastValue = $progression['2024-03-01'];
        $this->assertGreaterThanOrEqual(12.0, $lastValue);
        $this->assertLessThan(14.0, $lastValue);
        
        // Should show decreasing trend
        $this->assertGreaterThan($lastValue, $firstValue);
    }

    public function test_calculates_muscle_mass_progression_with_phases()
    {
        $startMass = 150.0;
        $strengthData = []; // Not used in current implementation
        $measurementDates = [];
        
        // Generate 12 weeks of measurement dates
        for ($week = 0; $week < 12; $week++) {
            $measurementDates[] = Carbon::parse('2024-01-01')->addWeeks($week);
        }
        
        $progression = $this->generator->calculateMuscleMassProgression($startMass, $strengthData, $measurementDates);
        
        // Should have progression for each date
        $this->assertCount(12, $progression);
        
        // Early weeks should show muscle gain (newbie gains)
        $earlyValue = $progression['2024-01-01'];
        $midValue = $progression['2024-02-12']; // Week 6
        $this->assertGreaterThan($earlyValue, $midValue);
        
        // Should not exceed reasonable muscle gain
        foreach ($progression as $value) {
            $this->assertGreaterThanOrEqual($startMass, $value);
            $this->assertLessThanOrEqual($startMass + 3, $value); // Max 3 lbs gain
        }
    }

    public function test_calculates_additional_measurements_with_different_rates()
    {
        $weightLossRatio = 0.1; // 10% weight loss
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-03-01')
        ];
        
        $measurements = $this->generator->calculateAdditionalMeasurements($weightLossRatio, $measurementDates);
        
        // Should have measurements for all body parts
        $this->assertArrayHasKey('chest', $measurements);
        $this->assertArrayHasKey('arm', $measurements);
        $this->assertArrayHasKey('thigh', $measurements);
        
        // Each body part should have measurements for each date
        foreach (['chest', 'arm', 'thigh'] as $bodyPart) {
            $this->assertCount(3, $measurements[$bodyPart]);
            
            // Should show decreasing trend
            $firstValue = $measurements[$bodyPart]['2024-01-01'];
            $lastValue = $measurements[$bodyPart]['2024-03-01'];
            $this->assertGreaterThan($lastValue, $firstValue);
        }
        
        // Arms should reduce less than chest and thigh (different reduction rates)
        $chestReduction = $measurements['chest']['2024-01-01'] - $measurements['chest']['2024-03-01'];
        $armReduction = $measurements['arm']['2024-01-01'] - $measurements['arm']['2024-03-01'];
        $thighReduction = $measurements['thigh']['2024-01-01'] - $measurements['thigh']['2024-03-01'];
        
        $this->assertGreaterThan($armReduction, $chestReduction);
        $this->assertGreaterThan($armReduction, $thighReduction);
    }

    public function test_body_fat_progression_respects_target_minimum()
    {
        $startBodyFat = 15.0;
        $targetBodyFat = 10.0;
        $measurementDates = [
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-03-01')
        ];
        
        $progression = $this->generator->calculateBodyFatProgression($startBodyFat, $targetBodyFat, $measurementDates);
        
        // No value should go below target
        foreach ($progression as $value) {
            $this->assertGreaterThanOrEqual($targetBodyFat, $value);
        }
    }

    public function test_muscle_mass_progression_handles_different_phases()
    {
        $startMass = 140.0;
        $measurementDates = [];
        
        // Generate dates spanning different phases
        for ($week = 0; $week < 12; $week++) {
            $measurementDates[] = Carbon::parse('2024-01-01')->addWeeks($week);
        }
        
        $progression = $this->generator->calculateMuscleMassProgression($startMass, [], $measurementDates);
        
        // Week 1-6: Should show gains
        $week1 = $progression['2024-01-01'];
        $week6 = $progression['2024-02-12'];
        $this->assertGreaterThan($week1, $week6);
        
        // Week 6-10: Should maintain
        $week10 = $progression['2024-03-11'];
        $this->assertEqualsWithDelta($week6, $week10, 0.5); // Allow small variation
        
        // Week 10-12: May show slight decrease
        $week12 = $progression['2024-03-18']; // Week 11 (0-indexed)
        $this->assertLessThanOrEqual($week10, $week12);
    }

    public function test_additional_measurements_have_realistic_starting_values()
    {
        $weightLossRatio = 0.05; // Small weight loss
        $measurementDates = [Carbon::parse('2024-01-01')];
        
        $measurements = $this->generator->calculateAdditionalMeasurements($weightLossRatio, $measurementDates);
        
        // Check realistic starting values
        $chest = $measurements['chest']['2024-01-01'];
        $arm = $measurements['arm']['2024-01-01'];
        $thigh = $measurements['thigh']['2024-01-01'];
        
        // Typical measurements for a 180lb male
        $this->assertGreaterThan(40, $chest);
        $this->assertLessThan(45, $chest);
        
        $this->assertGreaterThan(13, $arm);
        $this->assertLessThan(16, $arm);
        
        $this->assertGreaterThan(22, $thigh);
        $this->assertLessThan(26, $thigh);
    }

    public function test_progression_methods_handle_empty_dates()
    {
        $bodyFatProgression = $this->generator->calculateBodyFatProgression(18.0, 12.0, []);
        $this->assertEmpty($bodyFatProgression);
        
        $muscleMassProgression = $this->generator->calculateMuscleMassProgression(150.0, [], []);
        $this->assertEmpty($muscleMassProgression);
        
        $additionalMeasurements = $this->generator->calculateAdditionalMeasurements(0.1, []);
        $this->assertArrayHasKey('chest', $additionalMeasurements);
        $this->assertEmpty($additionalMeasurements['chest']);
    }
}