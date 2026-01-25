<?php

namespace Tests\Unit\Services\Charts;

use Tests\TestCase;
use App\Services\Charts\HoldDurationProgressionChartGenerator;
use App\Models\LiftLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class HoldDurationProgressionChartGeneratorTest extends TestCase
{
    private HoldDurationProgressionChartGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new HoldDurationProgressionChartGenerator();
    }

    /** @test */
    public function it_supports_hold_duration_progression_exercise_type()
    {
        $this->assertTrue($this->generator->supports('hold_duration_progression'));
        $this->assertFalse($this->generator->supports('weight_progression'));
        $this->assertFalse($this->generator->supports('cardio_progression'));
    }

    /** @test */
    public function it_generates_total_duration_chart_data_using_time_field()
    {
        // Create mock lift logs with static hold data using time field
        $liftSets1 = new Collection([
            (object) ['reps' => 1, 'time' => 30, 'weight' => 0], // 30 second hold
            (object) ['reps' => 1, 'time' => 30, 'weight' => 0], // 30 second hold
            (object) ['reps' => 1, 'time' => 30, 'weight' => 0], // 30 second hold
        ]);
        
        $liftLog1 = (object) [
            'logged_at' => Carbon::parse('2023-01-01'),
            'liftSets' => $liftSets1
        ];

        $liftSets2 = new Collection([
            (object) ['reps' => 1, 'time' => 45, 'weight' => 0], // 45 second hold
            (object) ['reps' => 1, 'time' => 45, 'weight' => 0], // 45 second hold
        ]);
        
        $liftLog2 = (object) [
            'logged_at' => Carbon::parse('2023-01-02'),
            'liftSets' => $liftSets2
        ];

        $liftLogs = new Collection([$liftLog1, $liftLog2]);

        $chartData = $this->generator->generate($liftLogs);

        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(2, $chartData['datasets']);

        // Assert for the main dataset
        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Total Duration (seconds)', $dataset['label']);
        $this->assertCount(2, $dataset['data']);
        
        // Check first data point: 30s × 3 sets = 90s total
        $this->assertEquals($liftLog1->logged_at->toIso8601String(), $dataset['data'][0]['x']);
        $this->assertEquals(90, $dataset['data'][0]['y']);
        
        // Check second data point: 45s × 2 sets = 90s total
        $this->assertEquals($liftLog2->logged_at->toIso8601String(), $dataset['data'][1]['x']);
        $this->assertEquals(90, $dataset['data'][1]['y']);

        // Assert for the trend line dataset
        $trendLineDataset = $chartData['datasets'][1];
        $this->assertEquals('Trend', $trendLineDataset['label']);
        $this->assertCount(2, $trendLineDataset['data']);
    }

    /** @test */
    public function it_reads_from_time_field_not_reps_field()
    {
        // Create a lift log where reps=1 but time=60
        // This tests that we're reading from time field, not reps
        $liftSets = new Collection([
            (object) ['reps' => 1, 'time' => 60, 'weight' => 0],
        ]);
        
        $liftLog = (object) [
            'logged_at' => Carbon::parse('2023-01-01'),
            'liftSets' => $liftSets
        ];

        $liftLogs = new Collection([$liftLog]);
        $chartData = $this->generator->generate($liftLogs);

        $dataset = $chartData['datasets'][0];
        
        // Should be 60 (from time field), not 1 (from reps field)
        $this->assertEquals(60, $dataset['data'][0]['y']);
    }

    /** @test */
    public function it_handles_empty_lift_logs_collection()
    {
        $liftLogs = new Collection([]);
        
        $chartData = $this->generator->generate($liftLogs);
        
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(1, $chartData['datasets']);
        $this->assertEmpty($chartData['datasets'][0]['data']);
    }

    /** @test */
    public function it_handles_lift_logs_with_missing_time_field()
    {
        // Test graceful handling when time field is null
        $liftSets = new Collection([
            (object) ['reps' => 1, 'time' => null, 'weight' => 0],
        ]);
        
        $liftLog = (object) [
            'logged_at' => Carbon::parse('2023-01-01'),
            'liftSets' => $liftSets
        ];

        $liftLogs = new Collection([$liftLog]);
        $chartData = $this->generator->generate($liftLogs);

        $dataset = $chartData['datasets'][0];
        
        // Should default to 0 when time is null
        $this->assertEquals(0, $dataset['data'][0]['y']);
    }
}
