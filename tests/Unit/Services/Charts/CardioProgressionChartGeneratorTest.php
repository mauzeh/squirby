<?php

namespace Tests\Unit\Services\Charts;

use Tests\TestCase;
use App\Services\Charts\CardioProgressionChartGenerator;
use App\Models\LiftLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CardioProgressionChartGeneratorTest extends TestCase
{
    private CardioProgressionChartGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new CardioProgressionChartGenerator();
    }

    /** @test */
    public function it_supports_cardio_exercise_types()
    {
        $this->assertTrue($this->generator->supports('cardio_progression'));
        $this->assertTrue($this->generator->supports('cardio'));
        $this->assertFalse($this->generator->supports('weight_progression'));
        $this->assertFalse($this->generator->supports('volume_progression'));
    }

    /** @test */
    public function it_generates_distance_chart_data_for_cardio_exercises()
    {
        // Create mock lift logs with cardio data using stdClass to avoid database calls
        $liftLog1 = (object) [
            'display_reps' => 500, // 500m
            'logged_at' => Carbon::parse('2023-01-01')
        ];

        $liftLog2 = (object) [
            'display_reps' => 1000, // 1000m
            'logged_at' => Carbon::parse('2023-01-02')
        ];

        $liftLogs = new Collection([$liftLog1, $liftLog2]);

        $chartData = $this->generator->generate($liftLogs);

        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(1, $chartData['datasets']);

        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Distance (m)', $dataset['label']);
        $this->assertCount(2, $dataset['data']);
        
        // Check first data point
        $this->assertEquals($liftLog1->logged_at->toIso8601String(), $dataset['data'][0]['x']);
        $this->assertEquals(500, $dataset['data'][0]['y']);
        
        // Check second data point
        $this->assertEquals($liftLog2->logged_at->toIso8601String(), $dataset['data'][1]['x']);
        $this->assertEquals(1000, $dataset['data'][1]['y']);
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
}