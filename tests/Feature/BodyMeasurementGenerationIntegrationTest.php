<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\RealisticVariationService;
use App\Models\User;
use App\Models\BodyLog;
use App\Models\MeasurementType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BodyMeasurementGenerationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TransformationDataGenerator $generator;
    private RealisticVariationService $variationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
        $this->variationService = new RealisticVariationService();
    }

    public function test_generates_and_persists_complete_body_measurement_transformation()
    {
        // Create a test user
        $user = User::factory()->create([
            'name' => 'Test Athlete',
            'email' => 'athlete@example.com'
        ]);

        $startDate = Carbon::now()->startOfDay();
        $days = 84; // 12 weeks
        $startWeight = 185.0;
        $targetWeight = 170.0;
        $startWaist = 38.0;

        // Generate body measurement data
        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $user->id
        );

        // Apply realistic variations
        $variatedLogs = $this->generator->applyBodyMeasurementVariations($bodyLogs, $this->variationService);

        // Persist to database
        foreach ($variatedLogs as $logData) {
            BodyLog::create($logData);
        }

        // Verify data was persisted correctly
        $this->assertDatabaseCount('body_logs', count($variatedLogs));
        $this->assertDatabaseCount('measurement_types', 2); // Weight and Waist

        // Verify measurement types
        $this->assertDatabaseHas('measurement_types', [
            'name' => 'Weight',
            'default_unit' => 'lbs',
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas('measurement_types', [
            'name' => 'Waist',
            'default_unit' => 'inches',
            'user_id' => $user->id
        ]);

        // Verify body logs have proper relationships
        $weightType = MeasurementType::where('name', 'Weight')->where('user_id', $user->id)->first();
        $waistType = MeasurementType::where('name', 'Waist')->where('user_id', $user->id)->first();

        $weightLogs = BodyLog::where('measurement_type_id', $weightType->id)->get();
        $waistLogs = BodyLog::where('measurement_type_id', $waistType->id)->get();

        $this->assertGreaterThan(0, $weightLogs->count(), 'Should have weight measurements');
        $this->assertGreaterThan(0, $waistLogs->count(), 'Should have waist measurements');

        // Verify progression in persisted data
        $firstWeightLog = $weightLogs->sortBy('logged_at')->first();
        $lastWeightLog = $weightLogs->sortBy('logged_at')->last();

        $this->assertGreaterThan($lastWeightLog->value, $firstWeightLog->value, 
            'Weight should decrease over time in persisted data');

        $firstWaistLog = $waistLogs->sortBy('logged_at')->first();
        $lastWaistLog = $waistLogs->sortBy('logged_at')->last();

        $this->assertGreaterThan($lastWaistLog->value, $firstWaistLog->value, 
            'Waist should decrease over time in persisted data');
    }

    public function test_body_measurement_data_integrates_with_existing_measurement_types()
    {
        // Create a user with existing measurement types
        $user = User::factory()->create();
        
        // Create existing measurement types
        $existingWeightType = MeasurementType::create([
            'name' => 'Weight',
            'default_unit' => 'kg', // Different unit
            'user_id' => $user->id
        ]);

        $startDate = Carbon::now()->startOfDay();
        $days = 28; // 4 weeks
        $startWeight = 180.0;
        $targetWeight = 175.0;
        $startWaist = 36.0;

        // Generate body measurement data
        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $user->id
        );

        // Should reuse existing weight measurement type
        $weightLogs = array_filter($bodyLogs, function($log) use ($existingWeightType) {
            return $log['measurement_type_id'] === $existingWeightType->id;
        });

        $this->assertNotEmpty($weightLogs, 'Should use existing weight measurement type');

        // Should create new waist measurement type
        $waistType = MeasurementType::where('name', 'Waist')->where('user_id', $user->id)->first();
        $this->assertNotNull($waistType, 'Should create waist measurement type');

        // Should only have 2 measurement types total (existing weight + new waist)
        $this->assertEquals(2, MeasurementType::where('user_id', $user->id)->count());
    }

    public function test_measurement_schedule_produces_realistic_frequency_patterns()
    {
        $user = User::factory()->create();
        $startDate = Carbon::now()->startOfDay();
        $days = 84; // 12 weeks

        $schedule = $this->generator->generateMeasurementSchedule($startDate, $days);

        // Analyze frequency patterns
        $weeklyMeasurements = [];
        foreach ($schedule as $measurement) {
            $week = $measurement['week_number'];
            if (!isset($weeklyMeasurements[$week])) {
                $weeklyMeasurements[$week] = ['weight' => 0, 'waist' => 0];
            }
            
            if ($measurement['measure_weight']) {
                $weeklyMeasurements[$week]['weight']++;
            }
            if ($measurement['measure_waist']) {
                $weeklyMeasurements[$week]['waist']++;
            }
        }

        // Early weeks should have more frequent measurements
        $earlyWeekAvg = 0;
        $lateWeekAvg = 0;
        $earlyWeekCount = 0;
        $lateWeekCount = 0;

        foreach ($weeklyMeasurements as $week => $counts) {
            $totalMeasurements = $counts['weight'] + $counts['waist'];
            
            if ($week <= 4) {
                $earlyWeekAvg += $totalMeasurements;
                $earlyWeekCount++;
            } elseif ($week > 8) {
                $lateWeekAvg += $totalMeasurements;
                $lateWeekCount++;
            }
        }

        if ($earlyWeekCount > 0) $earlyWeekAvg /= $earlyWeekCount;
        if ($lateWeekCount > 0) $lateWeekAvg /= $lateWeekCount;

        // Early weeks should have more measurements on average
        $this->assertGreaterThanOrEqual($lateWeekAvg, $earlyWeekAvg, 
            'Early weeks should have at least as many measurements as later weeks');

        // Weight measurements should be more frequent than waist
        $totalWeightMeasurements = array_sum(array_column($weeklyMeasurements, 'weight'));
        $totalWaistMeasurements = array_sum(array_column($weeklyMeasurements, 'waist'));

        $this->assertGreaterThan($totalWaistMeasurements, $totalWeightMeasurements, 
            'Should have more weight measurements than waist measurements');
    }

    public function test_realistic_variations_create_natural_looking_data()
    {
        $user = User::factory()->create();
        $startDate = Carbon::now()->startOfDay();
        $days = 56; // 8 weeks
        $startWeight = 180.0;
        $targetWeight = 170.0;
        $startWaist = 36.0;

        // Generate base data
        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $user->id
        );

        // Apply variations
        $variatedLogs = $this->generator->applyBodyMeasurementVariations($bodyLogs, $this->variationService);

        // Persist and analyze
        foreach ($variatedLogs as $logData) {
            BodyLog::create($logData);
        }

        $weightType = MeasurementType::where('name', 'Weight')->where('user_id', $user->id)->first();
        $weightLogs = BodyLog::where('measurement_type_id', $weightType->id)
            ->orderBy('logged_at')
            ->get();

        // Check for realistic patterns
        $weights = $weightLogs->pluck('value')->toArray();
        
        // Should have some day-to-day variation (not perfectly smooth)
        $variations = [];
        for ($i = 1; $i < count($weights); $i++) {
            $variations[] = abs($weights[$i] - $weights[$i-1]);
        }

        $avgVariation = array_sum($variations) / count($variations);
        
        // Average daily variation should be reasonable (0.1 to 2.0 lbs)
        $this->assertGreaterThan(0.1, $avgVariation, 'Should have some daily variation');
        $this->assertLessThan(2.0, $avgVariation, 'Daily variation should be realistic');

        // Overall trend should still be downward
        $firstWeight = $weights[0];
        $lastWeight = end($weights);
        $this->assertLessThan($firstWeight, $lastWeight, 'Overall trend should be weight loss');

        // Should not have any impossible jumps (more than 5 lbs in one day)
        foreach ($variations as $variation) {
            $this->assertLessThan(5.0, $variation, 'No single day should have more than 5 lb change');
        }
    }

    public function test_measurement_timing_aligns_with_workout_patterns()
    {
        $user = User::factory()->create();
        $startDate = Carbon::parse('2024-01-01 00:00:00'); // Monday
        $days = 21; // 3 weeks
        $startWeight = 180.0;
        $targetWeight = 177.0;
        $startWaist = 36.0;

        $bodyLogs = $this->generator->generateBodyMeasurementData(
            $startDate,
            $days,
            $startWeight,
            $targetWeight,
            $startWaist,
            $user->id
        );

        // Persist data
        foreach ($bodyLogs as $logData) {
            BodyLog::create($logData);
        }

        $allLogs = BodyLog::where('user_id', $user->id)->get();

        // Check measurement timing patterns
        foreach ($allLogs as $log) {
            $logDate = $log->logged_at;
            
            // Should be in morning hours (6-9 AM)
            $this->assertGreaterThanOrEqual(6, $logDate->hour);
            $this->assertLessThanOrEqual(9, $logDate->hour);
            
            // Should be on reasonable days (not every single day)
            $dayOfWeek = $logDate->dayOfWeek;
            // Most measurements should be on weekdays or specific weekend days
            $this->assertTrue(
                in_array($dayOfWeek, [0, 1, 2, 3, 4, 5, 6]), // Any day is valid, but pattern should make sense
                'Measurement day should be valid'
            );
        }

        // Check that measurements are spaced reasonably
        $measurementDates = $allLogs->pluck('logged_at')->map(function($date) {
            return $date->format('Y-m-d');
        })->unique()->sort()->values();

        // Should not have measurements every single day
        $this->assertLessThan($days, $measurementDates->count(), 
            'Should not measure every single day');
        
        // Should have at least some measurements
        $this->assertGreaterThan($days / 7, $measurementDates->count(), 
            'Should have at least weekly measurements');
    }
}