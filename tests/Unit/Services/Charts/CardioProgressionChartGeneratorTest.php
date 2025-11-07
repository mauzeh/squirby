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
    public function it_generates_total_distance_chart_data_for_cardio_exercises()
    {
        // Create mock lift logs with cardio data and lift sets
        $liftSets1 = new Collection([
            (object) ['reps' => 500, 'weight' => 0], // First round
            (object) ['reps' => 500, 'weight' => 0], // Second round
        ]);
        
        $liftLog1 = (object) [
            'display_reps' => 500, // 500m per round
            'logged_at' => Carbon::parse('2023-01-01'),
            'liftSets' => $liftSets1
        ];

        $liftSets2 = new Collection([
            (object) ['reps' => 1000, 'weight' => 0], // Single round
        ]);
        
        $liftLog2 = (object) [
            'display_reps' => 1000, // 1000m per round
            'logged_at' => Carbon::parse('2023-01-02'),
            'liftSets' => $liftSets2
        ];

        $liftLogs = new Collection([$liftLog1, $liftLog2]);

        $chartData = $this->generator->generate($liftLogs);

        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(1, $chartData['datasets']);

        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Total Distance (m)', $dataset['label']);
        $this->assertCount(2, $dataset['data']);
        
        // Check first data point: 500m × 2 rounds = 1000m total
        $this->assertEquals($liftLog1->logged_at->toIso8601String(), $dataset['data'][0]['x']);
        $this->assertEquals(1000, $dataset['data'][0]['y']);
        
        // Check second data point: 1000m × 1 round = 1000m total
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