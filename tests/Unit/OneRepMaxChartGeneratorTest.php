<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Charts\OneRepMaxChartGenerator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Test OneRepMaxChartGenerator
 * 
 * Tests the chart generation logic for 1RM progress charts,
 * including handling of empty data and high-rep sets.
 */
class OneRepMaxChartGeneratorTest extends TestCase
{
    protected OneRepMaxChartGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new OneRepMaxChartGenerator();
    }

    /**
     * @test
     */
    public function it_generates_chart_data_with_valid_1rm_values()
    {
        // Arrange: Create lift logs with valid 1RM values
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 105,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-03'),
                'best_one_rep_max' => 110,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertNotEmpty($result['datasets']);
        
        // Check main dataset
        $mainDataset = $result['datasets'][0];
        $this->assertEquals('1RM (est.)', $mainDataset['label']);
        $this->assertCount(3, $mainDataset['data']);
        
        // Check data points
        $this->assertEquals(100, $mainDataset['data'][0]['y']);
        $this->assertEquals(105, $mainDataset['data'][1]['y']);
        $this->assertEquals(110, $mainDataset['data'][2]['y']);
        
        // Check trend line exists (2+ data points)
        $this->assertCount(2, $result['datasets']); // Main + trend
        $this->assertEquals('Trend', $result['datasets'][1]['label']);
    }

    /**
     * @test
     */
    public function it_returns_empty_datasets_when_all_1rm_values_are_zero()
    {
        // Arrange: Create lift logs with zero 1RM (high-rep sets)
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 0,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 0,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert: Should return empty datasets array
        $this->assertIsArray($result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEmpty($result['datasets']);
    }

    /**
     * @test
     */
    public function it_filters_out_zero_1rm_values_but_keeps_valid_ones()
    {
        // Arrange: Mix of valid and zero 1RM values
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 0, // High-rep set
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-03'),
                'best_one_rep_max' => 110,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert: Should only include the 2 valid data points
        $this->assertIsArray($result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertNotEmpty($result['datasets']);
        
        $mainDataset = $result['datasets'][0];
        $this->assertCount(2, $mainDataset['data']);
        $this->assertEquals(100, $mainDataset['data'][0]['y']);
        $this->assertEquals(110, $mainDataset['data'][1]['y']);
    }

    /**
     * @test
     */
    public function it_handles_multiple_logs_on_same_day_by_taking_best()
    {
        // Arrange: Multiple logs on the same day
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01 08:00:00'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01 14:00:00'),
                'best_one_rep_max' => 110, // Best of the day
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-01 18:00:00'),
                'best_one_rep_max' => 105,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert: Should only have 1 data point with the best value
        $mainDataset = $result['datasets'][0];
        $this->assertCount(1, $mainDataset['data']);
        $this->assertEquals(110, $mainDataset['data'][0]['y']);
    }

    /**
     * @test
     */
    public function it_does_not_include_trend_line_with_single_data_point()
    {
        // Arrange: Single lift log
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert: Should only have main dataset, no trend line
        $this->assertCount(1, $result['datasets']);
        $this->assertEquals('1RM (est.)', $result['datasets'][0]['label']);
    }

    /**
     * @test
     */
    public function it_includes_trend_line_with_two_or_more_data_points()
    {
        // Arrange: Two lift logs
        $liftLogs = new Collection([
            (object)[
                'logged_at' => Carbon::parse('2025-01-01'),
                'best_one_rep_max' => 100,
            ],
            (object)[
                'logged_at' => Carbon::parse('2025-01-02'),
                'best_one_rep_max' => 105,
            ],
        ]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert: Should have main dataset + trend line
        $this->assertCount(2, $result['datasets']);
        $this->assertEquals('1RM (est.)', $result['datasets'][0]['label']);
        $this->assertEquals('Trend', $result['datasets'][1]['label']);
    }

    /**
     * @test
     */
    public function it_supports_correct_exercise_types()
    {
        // Assert
        $this->assertTrue($this->generator->supports('weighted'));
        $this->assertTrue($this->generator->supports('one_rep_max'));
        $this->assertTrue($this->generator->supports('weight_progression'));
        $this->assertFalse($this->generator->supports('bodyweight'));
        $this->assertFalse($this->generator->supports('cardio'));
    }

    /**
     * @test
     */
    public function it_returns_empty_datasets_for_empty_collection()
    {
        // Arrange: Empty collection
        $liftLogs = new Collection([]);

        // Act
        $result = $this->generator->generate($liftLogs);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEmpty($result['datasets']);
    }
}
