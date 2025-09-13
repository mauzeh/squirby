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
     * Test that the generateBestPerDay method correctly generates chart data.
     *
     * This test provides a collection of lift logs with multiple entries on the same day.
     * It asserts that the chart data is generated correctly, with only the best lift log per day included.
     *
     * @test
     */
    public function it_generates_best_per_day_chart_data_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
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

        // Act: Generate the chart data.
        $chartData = $service->generateBestPerDay($liftLogs);

        // Assert: Check that the chart data is in the correct format and contains the correct data.
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(1, $chartData['datasets']);

        $dataset = $chartData['datasets'][0];
        $this->assertEquals('1RM (est.)', $dataset['label']);
        $this->assertIsArray($dataset['data']);
        $this->assertCount(2, $dataset['data']);

        // Check that the best lift log for each day is used.
        $this->assertEquals(110, $dataset['data'][0]['y']);
        $this->assertEquals(120, $dataset['data'][1]['y']);
    }

    /**
     * Test that the generateBestPerDay method correctly handles multiple lift logs on the same day.
     *
     * This test provides a collection of lift logs with multiple entries on the same day, with different values.
     * It asserts that the chart data is generated correctly, with only the best lift log per day included.
     *
     * @test
     */
    public function it_handles_multiple_logs_on_the_same_day_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
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

        // Act: Generate the chart data.
        $chartData = $service->generateBestPerDay($liftLogs);

        // Assert: Check that the chart data contains the correct number of data points and the correct values.
        $dataset = $chartData['datasets'][0];
        $this->assertCount(2, $dataset['data']);
        $this->assertEquals(150, $dataset['data'][0]['y']);
        $this->assertEquals(200, $dataset['data'][1]['y']);
    }

    /**
     * Test that the generateAllDataPoints method correctly generates chart data.
     *
     * This test provides a collection of lift logs with multiple entries on the same day.
     * It asserts that the chart data is generated correctly, with all data points included.
     *
     * @test
     */
    public function it_generates_all_data_points_chart_data_correctly()
    {
        // Arrange: Create a new ChartService instance and a collection of lift logs.
        $service = new ChartService();
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
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 200,
            ],
        ]);

        // Act: Generate the chart data.
        $chartData = $service->generateAllDataPoints($liftLogs);

        // Assert: Check that the chart data contains all data points.
        $dataset = $chartData['datasets'][0];
        $this->assertCount(3, $dataset['data']);
        $this->assertEquals(100, $dataset['data'][0]['y']);
        $this->assertEquals(150, $dataset['data'][1]['y']);
        $this->assertEquals(200, $dataset['data'][2]['y']);
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
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(1, $chartData['datasets']);

        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Weight', $dataset['label']);
        $this->assertIsArray($dataset['data']);
        $this->assertCount(3, $dataset['data']);

        // Check that the data contains the correct values, with null for the missing date.
        $this->assertEquals('01/01', $chartData['labels'][0]);
        $this->assertEquals(150, $dataset['data'][0]);
        $this->assertEquals('01/02', $chartData['labels'][1]);
        $this->assertNull($dataset['data'][1]);
        $this->assertEquals('01/03', $chartData['labels'][2]);
        $this->assertEquals(152, $dataset['data'][2]);
    }
}
