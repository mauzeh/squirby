<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ChartService;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChartServiceDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected ChartService $chartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chartService = new ChartService();
    }

    /** @test */
    public function it_generates_1rm_chart_for_weighted_exercises()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        $liftLogs = $this->createLiftLogsWithSets($exercise, [
            ['weight' => 100, 'reps' => 5],
            ['weight' => 105, 'reps' => 5],
        ]);

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);

        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEquals('1RM (est.)', $chartData['datasets'][0]['label']);
    }

    /** @test */
    public function it_generates_volume_chart_for_bodyweight_exercises()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $liftLogs = $this->createLiftLogsWithSets($exercise, [
            ['weight' => 0, 'reps' => 10],
            ['weight' => 0, 'reps' => 12],
        ]);

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);

        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEquals('Total Reps', $chartData['datasets'][0]['label']);
    }

    /** @test */
    public function it_generates_volume_chart_for_banded_exercises()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $liftLogs = $this->createLiftLogsWithSets($exercise, [
            ['weight' => 0, 'reps' => 8],
            ['weight' => 0, 'reps' => 10],
        ]);

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);

        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEquals('Total Reps', $chartData['datasets'][0]['label']);
    }

    /** @test */
    public function it_generates_distance_chart_for_cardio_exercises()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $liftLogs = $this->createLiftLogsWithSets($exercise, [
            ['weight' => 0, 'reps' => 500],  // 500m
            ['weight' => 0, 'reps' => 1000], // 1000m
        ]);

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);

        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEquals('Distance (m)', $chartData['datasets'][0]['label']);
    }

    private function createLiftLogsWithSets(Exercise $exercise, array $setsData): Collection
    {
        $user = User::factory()->create();
        $liftLogs = collect();

        foreach ($setsData as $index => $setData) {
            $liftLog = LiftLog::factory()->create([
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'logged_at' => now()->addDays($index),
            ]);

            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'weight' => $setData['weight'],
                'reps' => $setData['reps'],
            ]);

            $liftLog->load('liftSets');
            $liftLogs->push($liftLog);
        }

        return $liftLogs;
    }
}