<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RealisticVariationService;
use Carbon\Carbon;

class RealisticVariationServiceMeasurementTest extends TestCase
{
    private RealisticVariationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RealisticVariationService();
    }

    public function test_adds_measurement_gaps_to_schedule()
    {
        $schedule = [];
        for ($i = 0; $i < 20; $i++) {
            $schedule[] = [
                'date' => Carbon::parse('2024-01-01')->addDays($i),
                'measure_weight' => true
            ];
        }
        
        $modifiedSchedule = $this->service->addMeasurementGaps($schedule, 0.3);
        
        // Should have fewer measurements due to gaps (with 30% gap rate)
        $this->assertLessThanOrEqual(count($schedule), count($modifiedSchedule));
        
        // Should still have some measurements
        $this->assertGreaterThanOrEqual(5, count($modifiedSchedule));
    }

    public function test_simulates_whoosh_effect_on_weight_data()
    {
        $weightData = [];
        for ($i = 0; $i < 30; $i++) {
            $weightData[] = 180.0 - ($i * 0.1); // Gradual weight loss
        }
        
        $modifiedData = $this->service->simulateWhooshEffect($weightData, 1.0); // 100% probability
        
        // Should have same number of data points
        $this->assertCount(30, $modifiedData);
        
        // With 100% probability, should definitely have modifications
        $hasChanges = false;
        for ($i = 0; $i < count($modifiedData); $i++) {
            if ($modifiedData[$i] !== $weightData[$i]) {
                $hasChanges = true;
                break;
            }
        }
        
        $this->assertTrue($hasChanges, 'Whoosh effect should modify the data');
    }

    public function test_adds_plateau_periods_to_progression()
    {
        $progressionData = [];
        for ($i = 0; $i < 20; $i++) {
            $progressionData[] = 180.0 - ($i * 0.5); // Steady weight loss
        }
        
        $modifiedData = $this->service->addPlateauPeriods($progressionData, 5);
        
        // Should have same number of data points
        $this->assertCount(20, $modifiedData);
        
        // Should have some plateau periods (consecutive same values)
        $plateauFound = false;
        for ($i = 1; $i < count($modifiedData) - 3; $i++) {
            if ($modifiedData[$i] === $modifiedData[$i+1] && 
                $modifiedData[$i+1] === $modifiedData[$i+2]) {
                $plateauFound = true;
                break;
            }
        }
        
        // Should find at least one plateau
        $this->assertTrue($plateauFound);
    }

    public function test_adds_measurement_precision_variations()
    {
        $baseWeight = 180.0;
        $baseWaist = 36.0;
        $baseBodyFat = 15.0;
        
        // Test multiple variations to ensure they work
        $weightVariations = [];
        $waistVariations = [];
        $bodyFatVariations = [];
        
        for ($i = 0; $i < 10; $i++) {
            $weightVariations[] = $this->service->addMeasurementPrecisionVariation($baseWeight, 'weight');
            $waistVariations[] = $this->service->addMeasurementPrecisionVariation($baseWaist, 'waist');
            $bodyFatVariations[] = $this->service->addMeasurementPrecisionVariation($baseBodyFat, 'body_fat');
        }
        
        // At least some should be different from base
        $this->assertTrue(in_array(false, array_map(fn($v) => $v === $baseWeight, $weightVariations)));
        $this->assertTrue(in_array(false, array_map(fn($v) => $v === $baseWaist, $waistVariations)));
        $this->assertTrue(in_array(false, array_map(fn($v) => $v === $baseBodyFat, $bodyFatVariations)));
        
        // All should be within reasonable ranges
        foreach ($weightVariations as $weight) {
            $this->assertGreaterThan(179.5, $weight);
            $this->assertLessThan(180.5, $weight);
        }
    }

    public function test_adds_body_fat_specific_variations()
    {
        $baseBodyFat = 15.0;
        
        // Test multiple variations to ensure they're within expected range
        for ($i = 0; $i < 10; $i++) {
            $variatedBF = $this->service->addBodyFatVariation($baseBodyFat, 5.0);
            
            // Should be within reasonable bounds
            $this->assertGreaterThanOrEqual(5.0, $variatedBF);
            $this->assertLessThanOrEqual(50.0, $variatedBF);
            
            // Should be within variation range (±5% of 15 = ±0.75)
            $this->assertGreaterThanOrEqual(14.0, $variatedBF);
            $this->assertLessThanOrEqual(16.0, $variatedBF);
        }
    }

    public function test_adds_muscle_mass_specific_variations()
    {
        $baseMass = 150.0;
        
        // Test multiple variations
        for ($i = 0; $i < 10; $i++) {
            $variatedMass = $this->service->addMuscleMassVariation($baseMass, 3.0);
            
            // Should be within reasonable bounds (80-120% of base)
            $this->assertGreaterThanOrEqual(120.0, $variatedMass); // 80% of 150
            $this->assertLessThanOrEqual(180.0, $variatedMass);    // 120% of 150
            
            // Should be within variation range (±3% of 150 = ±4.5)
            $this->assertGreaterThanOrEqual(145.0, $variatedMass);
            $this->assertLessThanOrEqual(155.0, $variatedMass);
        }
    }

    public function test_measurement_gaps_respect_gap_rate()
    {
        $schedule = [];
        for ($i = 0; $i < 100; $i++) {
            $schedule[] = ['date' => Carbon::parse('2024-01-01')->addDays($i)];
        }
        
        // Test with 0% gap rate (no gaps)
        $noGaps = $this->service->addMeasurementGaps($schedule, 0.0);
        $this->assertCount(100, $noGaps);
        
        // Test with high gap rate
        $manyGaps = $this->service->addMeasurementGaps($schedule, 0.8);
        $this->assertLessThan(50, count($manyGaps)); // Should remove many measurements
    }

    public function test_whoosh_effect_creates_sudden_drops()
    {
        // Create plateau-like data with enough days for whoosh to occur
        $weightData = array_fill(0, 30, 180.0); // 30 days at same weight
        
        $modifiedData = $this->service->simulateWhooshEffect($weightData, 1.0); // 100% probability
        
        // Should have modifications (some values should be different)
        $hasChanges = false;
        for ($i = 0; $i < count($modifiedData); $i++) {
            if ($modifiedData[$i] !== $weightData[$i]) {
                $hasChanges = true;
                break;
            }
        }
        
        $this->assertTrue($hasChanges, 'Whoosh effect should modify plateau data');
        
        // Should have at least some values that are lower than the original
        $lowerValues = array_filter($modifiedData, fn($v) => $v < 180.0);
        $this->assertGreaterThan(0, count($lowerValues), 'Whoosh effect should create some weight reduction');
    }

    public function test_plateau_periods_create_flat_sections()
    {
        $progressionData = [];
        for ($i = 0; $i < 30; $i++) {
            $progressionData[] = 180.0 - $i; // Steady decline
        }
        
        $modifiedData = $this->service->addPlateauPeriods($progressionData, 7);
        
        // Find the longest sequence of identical values
        $maxPlateauLength = 1;
        $currentPlateauLength = 1;
        
        for ($i = 1; $i < count($modifiedData); $i++) {
            if ($modifiedData[$i] === $modifiedData[$i-1]) {
                $currentPlateauLength++;
            } else {
                $maxPlateauLength = max($maxPlateauLength, $currentPlateauLength);
                $currentPlateauLength = 1;
            }
        }
        $maxPlateauLength = max($maxPlateauLength, $currentPlateauLength);
        
        // Should have at least one plateau of reasonable length
        $this->assertGreaterThanOrEqual(3, $maxPlateauLength);
    }

    public function test_precision_variations_are_measurement_type_specific()
    {
        $baseValue = 100.0;
        
        // Weight should have smaller precision variation than body fat
        $weightVariations = [];
        $bodyFatVariations = [];
        
        for ($i = 0; $i < 20; $i++) {
            $weightVariations[] = abs($this->service->addMeasurementPrecisionVariation($baseValue, 'weight') - $baseValue);
            $bodyFatVariations[] = abs($this->service->addMeasurementPrecisionVariation($baseValue, 'body_fat') - $baseValue);
        }
        
        $avgWeightVariation = array_sum($weightVariations) / count($weightVariations);
        $avgBodyFatVariation = array_sum($bodyFatVariations) / count($bodyFatVariations);
        
        // Body fat should have larger average variation than weight
        $this->assertGreaterThan($avgWeightVariation, $avgBodyFatVariation);
    }
}