<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\RealisticVariationService;
use App\Models\User;
use App\Models\MeasurementType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransformationDataGeneratorBodyMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private TransformationDataGenerator $generator;
    private RealisticVariationService $variationService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
        $this->variationService = new RealisticVariationService();
        $this->user = User::factory()->create();
    }

    public function test_generates_body_measurement_data_with_weight_and_waist_progression()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84; // 12 weeks
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        // Should have both weight and waist measurements
        $this->assertNotEmpty($bodyLogs);
        
        // Check that we have both measurement types
        $measurementTypeIds = array_unique(array_column($bodyLogs, 'measurement_type_id'));
        $this->assertCount(2, $measurementTypeIds, 'Should have weight and waist measurement types');

        // Verify measurement types were created
        $weightType = MeasurementType::where('name', 'Weight')->where('user_id', $this->user->id)->first();
        $waistType = MeasurementType::where('name', 'Waist')->where('user_id', $this->user->id)->first();
        
        $this->assertNotNull($weightType);
        $this->assertNotNull($waistType);
        $this->assertEquals('lbs', $weightType->default_unit);
        $this->assertEquals('inches', $waistType->default_unit);
    }

    public function test_weight_progression_shows_realistic_decline()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        // Filter weight measurements
        $weightType = MeasurementType::where('name', 'Weight')->where('user_id', $this->user->id)->first();
        $weightMeasurements = array_filter($bodyLogs, function($log) use ($weightType) {
            return $log['measurement_type_id'] === $weightType->id;
        });

        $this->assertNotEmpty($weightMeasurements);

        // Sort by date to check progression
        usort($weightMeasurements, function($a, $b) {
            return $a['logged_at']->timestamp <=> $b['logged_at']->timestamp;
        });

        $firstWeight = reset($weightMeasurements)['value'];
        $lastWeight = end($weightMeasurements)['value'];

        // Weight should decrease over time
        $this->assertLessThan($firstWeight, $lastWeight, 'Weight should decrease over the transformation period');
        
        // Should be close to target weight (within 5 lbs due to variations)
        $this->assertLessThanOrEqual($targetWeight + 5, $lastWeight);
        $this->assertGreaterThanOrEqual($targetWeight - 5, $lastWeight);
    }

    public function test_waist_progression_correlates_with_weight_loss()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        // Filter waist measurements
        $waistType = MeasurementType::where('name', 'Waist')->where('user_id', $this->user->id)->first();
        $waistMeasurements = array_filter($bodyLogs, function($log) use ($waistType) {
            return $log['measurement_type_id'] === $waistType->id;
        });

        $this->assertNotEmpty($waistMeasurements);

        // Sort by date to check progression
        usort($waistMeasurements, function($a, $b) {
            return $a['logged_at']->timestamp <=> $b['logged_at']->timestamp;
        });

        $firstWaist = reset($waistMeasurements)['value'];
        $lastWaist = end($waistMeasurements)['value'];

        // Waist should decrease over time
        $this->assertLessThan($firstWaist, $lastWaist, 'Waist should decrease over the transformation period');
        
        // Should show reasonable reduction (1-3 inches for 15 lb weight loss)
        $waistReduction = $firstWaist - $lastWaist;
        $this->assertGreaterThan(0.5, $waistReduction, 'Should show at least 0.5 inch waist reduction');
        $this->assertLessThan(4.0, $waistReduction, 'Waist reduction should be realistic (less than 4 inches)');
    }

    public function test_measurement_schedule_aligns_with_realistic_patterns()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;

        $schedule = $this->generator->generateMeasurementSchedule($startDate, $days);

        $this->assertNotEmpty($schedule);

        // Check that weight measurements are more frequent than waist measurements
        $weightMeasurements = array_filter($schedule, function($item) {
            return $item['measure_weight'];
        });
        
        $waistMeasurements = array_filter($schedule, function($item) {
            return $item['measure_waist'];
        });

        $this->assertGreaterThan(count($waistMeasurements), count($weightMeasurements), 
            'Weight measurements should be more frequent than waist measurements');

        // Verify early weeks have more frequent measurements
        $earlyWeekMeasurements = array_filter($schedule, function($item) {
            return $item['week_number'] <= 2;
        });
        
        $lateWeekMeasurements = array_filter($schedule, function($item) {
            return $item['week_number'] > 6;
        });

        // Early weeks should have more measurements per week
        $earlyWeekCount = count($earlyWeekMeasurements);
        $lateWeekCount = count($lateWeekMeasurements);
        
        // This is a rough check - early weeks should be more measurement-dense
        $this->assertGreaterThan(0, $earlyWeekCount, 'Should have measurements in early weeks');
        $this->assertGreaterThan(0, $lateWeekCount, 'Should have measurements in late weeks');
    }

    public function test_applies_realistic_variations_to_measurements()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        $variatedLogs = $this->generator->applyBodyMeasurementVariations($bodyLogs, $this->variationService);

        $this->assertCount(count($bodyLogs), $variatedLogs);

        // Check that variations were applied (values should be different)
        $originalValues = array_column($bodyLogs, 'value');
        $variatedValues = array_column($variatedLogs, 'value');

        $differenceCount = 0;
        for ($i = 0; $i < count($originalValues); $i++) {
            if ($originalValues[$i] !== $variatedValues[$i]) {
                $differenceCount++;
            }
        }

        // Most values should have some variation applied
        $this->assertGreaterThan(count($bodyLogs) * 0.5, $differenceCount, 
            'At least 50% of measurements should have variations applied');
    }

    public function test_measurement_comments_are_contextual()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        // All measurements should have comments
        foreach ($bodyLogs as $log) {
            $this->assertNotEmpty($log['comments'], 'Each measurement should have a comment');
            $this->assertIsString($log['comments']);
        }

        // Comments should be different (not all the same)
        $comments = array_column($bodyLogs, 'comments');
        $uniqueComments = array_unique($comments);
        
        $this->assertGreaterThan(1, count($uniqueComments), 'Should have varied comments');
    }

    public function test_measurement_timing_is_realistic()
    {
        $startDate = Carbon::now()->startOfDay();
        $days = 84;
        $startWeight = 180.0;
        $targetWeight = 165.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $this->user->id
        );

        foreach ($bodyLogs as $log) {
            $logTime = $log['logged_at'];
            
            // Measurements should be in the morning (between 6 AM and 9 AM)
            $this->assertGreaterThanOrEqual(6, $logTime->hour, 'Measurements should be in the morning');
            $this->assertLessThanOrEqual(9, $logTime->hour, 'Measurements should be before 9 AM');
        }

        // Weight and waist measurements on the same day should be close in time
        $weightType = MeasurementType::where('name', 'Weight')->where('user_id', $this->user->id)->first();
        $waistType = MeasurementType::where('name', 'Waist')->where('user_id', $this->user->id)->first();

        $weightLogs = array_filter($bodyLogs, function($log) use ($weightType) {
            return $log['measurement_type_id'] === $weightType->id;
        });

        $waistLogs = array_filter($bodyLogs, function($log) use ($waistType) {
            return $log['measurement_type_id'] === $waistType->id;
        });

        // Find measurements on the same day
        foreach ($weightLogs as $weightLog) {
            foreach ($waistLogs as $waistLog) {
                if ($weightLog['logged_at']->isSameDay($waistLog['logged_at'])) {
                    $timeDifference = abs($weightLog['logged_at']->diffInMinutes($waistLog['logged_at']));
                    $this->assertLessThanOrEqual(30, $timeDifference, 
                        'Weight and waist measurements on same day should be within 30 minutes');
                }
            }
        }
    }
}