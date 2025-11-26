<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ChartService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\MeasurementType;

/**
 * Class ChartServiceTest
 *
 * This class tests the ChartService.
 * It includes tests for generating chart data with all data points, and with only the best lift log per day.
 * It also tests edge cases, such as multiple lift logs on the same day.
 */
class ChartServiceTest extends TestCase
{
    /**
     * Test that the generateProgressChart method correctly generates chart data.
     *
     * This test provides a collection of lift logs and a mock exercise with 1RM support.
     * It asserts that the chart data is generated correctly using the exercise type strategy.
     *
     * @test
     */
    public function it_generates_best_per_day_chart_data_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
        
        // Create a mock exercise that returns a regular exercise type strategy
        $exercise = $this->createMock(\App\Models\Exercise::class);
        $strategy = new \App\Services\ExerciseTypes\RegularExerciseType();
        $exercise->method('getTypeStrategy')->willReturn($strategy);
        
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 110,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 120,
            ],
        ]);

        // Act: Generate the chart data using the new method.
        $chartData = $service->generateProgressChart($liftLogs, $exercise);

        // Assert: Check that the chart data is in the correct format and contains the correct data.
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
    }

    /**
     * Test that the generateProgressChart method correctly handles multiple lift logs on the same day.
     *
     * This test provides a collection of lift logs with multiple entries on the same day, with different values.
     * It asserts that the chart data is generated correctly using the exercise type strategy.
     *
     * @test
     */
    public function it_handles_multiple_logs_on_the_same_day_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
        
        // Create a mock exercise that returns a regular exercise type strategy
        $exercise = $this->createMock(\App\Models\Exercise::class);
        $strategy = new \App\Services\ExerciseTypes\RegularExerciseType();
        $exercise->method('getTypeStrategy')->willReturn($strategy);
        
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 150,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 120,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 200,
            ],
        ]);

        // Act: Generate the chart data using the new method.
        $chartData = $service->generateProgressChart($liftLogs, $exercise);

        // Assert: Check that the chart data is generated correctly.
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
    }

    /**
     * Test that the generateProgressChart method correctly generates chart data for banded exercises.
     *
     * This test provides a collection of lift logs and a banded exercise.
     * It asserts that the chart data is generated correctly using the exercise type strategy.
     *
     * @test
     */
    public function it_generates_all_data_points_chart_data_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
        
        // Create a mock exercise that returns a banded exercise type strategy
        $exercise = $this->createMock(\App\Models\Exercise::class);
        $strategy = new \App\Services\ExerciseTypes\BandedExerciseType();
        $exercise->method('getTypeStrategy')->willReturn($strategy);
        
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
                'liftSets' => collect([(object)['reps' => 8]]),
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 150,
                'liftSets' => collect([(object)['reps' => 10]]),
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 200,
                'liftSets' => collect([(object)['reps' => 12]]),
            ],
        ]);

        // Act: Generate the chart data using the new method.
        $chartData = $service->generateProgressChart($liftLogs, $exercise);

        // Assert: Check that the chart data is generated correctly.
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
    }

    /**
     * Test that the generateBodyLogChartData method correctly generates chart data.
     *
     * This test provides a collection of body logs with gaps in the dates.
     * It asserts that the chart data is generated correctly, with null values for the missing dates.
     *
     * @test
     */
    public function it_generates_body_log_chart_data_correctly()
    {
        // Arrange: Create a new ChartService instance, a measurement type, and a collection of body logs.
        $service = new ChartService();
        $measurementType = new MeasurementType(['name' => 'Weight', 'default_unit' => 'lbs']);
        $bodyLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'value' => 150,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-03'),
                'value' => 152,
            ],
        ]);
        $bodyLogs = $bodyLogs->sortByDesc('logged_at');

        // Act: Generate the chart data.
        $chartData = $service->generateBodyLogChartData($bodyLogs, $measurementType);

        // Assert: Check that the chart data is in the correct format and contains the correct data.
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(2, $chartData['datasets']); // Data + trend line

        // Check main dataset
        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Weight', $dataset['label']);
        $this->assertIsArray($dataset['data']);
        $this->assertCount(3, $dataset['data']);

        // Check that the data contains the correct values, with null for the missing date.
        $this->assertEquals('2025-01-01', $dataset['data'][0]['x']);
        $this->assertEquals(150, $dataset['data'][0]['y']);
        $this->assertEquals('2025-01-02', $dataset['data'][1]['x']);
        $this->assertNull($dataset['data'][1]['y']);
        $this->assertEquals('2025-01-03', $dataset['data'][2]['x']);
        $this->assertEquals(152, $dataset['data'][2]['y']);

        // Check trend line dataset
        $trendDataset = $chartData['datasets'][1];
        $this->assertEquals('Trend', $trendDataset['label']);
        $this->assertIsArray($trendDataset['data']);
        $this->assertCount(2, $trendDataset['data']); // Start and end points
    }
}
