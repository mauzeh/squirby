<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\ExercisePRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExercisePRServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ExercisePRService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $oneRepMaxService = app(\App\Services\OneRepMaxCalculatorService::class);
        $this->service = new ExercisePRService($oneRepMaxService);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function supportsPRTracking_returns_true_for_barbell()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        $this->assertTrue($this->service->supportsPRTracking($exercise));
    }

    /** @test */
    public function supportsPRTracking_returns_false_for_dumbbell()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'dumbbell']);
        
        $this->assertFalse($this->service->supportsPRTracking($exercise));
    }

    /** @test */
    public function supportsPRTracking_returns_false_for_cardio()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        
        $this->assertFalse($this->service->supportsPRTracking($exercise));
    }

    /** @test */
    public function getPRData_returns_null_for_unsupported_exercise()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'dumbbell']);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNull($result);
    }

    /** @test */
    public function getPRData_returns_correct_PRs_for_each_rep_range()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // Create lift logs with different rep ranges
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(3),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 242,
            'reps' => 1,
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(2),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 235,
            'reps' => 2,
        ]);
        
        $liftLog3 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog3->id,
            'weight' => 230,
            'reps' => 3,
        ]);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNotNull($result);
        $this->assertEquals(242, $result['rep_1']['weight']);
        $this->assertEquals($liftLog1->id, $result['rep_1']['lift_log_id']);
        $this->assertEquals(235, $result['rep_2']['weight']);
        $this->assertEquals($liftLog2->id, $result['rep_2']['lift_log_id']);
        $this->assertEquals(230, $result['rep_3']['weight']);
        $this->assertEquals($liftLog3->id, $result['rep_3']['lift_log_id']);
    }

    /** @test */
    public function getPRData_handles_no_lift_logs()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNull($result);
    }

    /** @test */
    public function getPRData_handles_partial_data_missing_rep_ranges()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // Create lift log with only 1 rep data
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 242,
            'reps' => 1,
        ]);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNotNull($result);
        $this->assertEquals(242, $result['rep_1']['weight']);
        $this->assertNull($result['rep_2']);
        $this->assertNull($result['rep_3']);
    }

    /** @test */
    public function getPRData_selects_highest_weight_when_multiple_logs_exist()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        
        // Create multiple lift logs with 1 rep sets
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(5),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 200,
            'reps' => 1,
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(3),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 250,
            'reps' => 1,
        ]);
        
        $liftLog3 = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDays(1),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog3->id,
            'weight' => 225,
            'reps' => 1,
        ]);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNotNull($result);
        $this->assertEquals(250, $result['rep_1']['weight']);
        $this->assertEquals($liftLog2->id, $result['rep_1']['lift_log_id']);
    }

    /** @test */
    public function getCalculatorGrid_generates_correct_percentages()
    {
        $prData = [
            'rep_1' => ['weight' => 242, 'lift_log_id' => 1, 'date' => '2024-01-15'],
            'rep_2' => ['weight' => 235, 'lift_log_id' => 2, 'date' => '2024-01-10'],
            'rep_3' => ['weight' => 230, 'lift_log_id' => 3, 'date' => '2024-01-08'],
        ];
        
        $result = $this->service->getCalculatorGrid($prData);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('rows', $result);
        
        // Check that all expected percentages are present
        $percentages = array_column($result['rows'], 'percentage');
        $expectedPercentages = [100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45];
        $this->assertEquals($expectedPercentages, $percentages);
    }

    /** @test */
    public function getCalculatorGrid_rounds_weights_correctly()
    {
        $prData = [
            'rep_1' => ['weight' => 242, 'lift_log_id' => 1, 'date' => '2024-01-15'],
        ];
        
        $result = $this->service->getCalculatorGrid($prData);
        
        $this->assertNotNull($result);
        
        // Check 100% row (should be 242)
        $row100 = $result['rows'][0];
        $this->assertEquals(100, $row100['percentage']);
        $this->assertEquals(242, $row100['weights'][0]);
        
        // Check 95% row (242 * 0.95 = 229.9, should round to 230)
        $row95 = $result['rows'][1];
        $this->assertEquals(95, $row95['percentage']);
        $this->assertEquals(230, $row95['weights'][0]);
        
        // Check 50% row (242 * 0.5 = 121, should be 121)
        $row50 = $result['rows'][10];
        $this->assertEquals(50, $row50['percentage']);
        $this->assertEquals(121, $row50['weights'][0]);
    }

    /** @test */
    public function getCalculatorGrid_handles_three_columns()
    {
        $prData = [
            'rep_1' => ['weight' => 242, 'lift_log_id' => 1, 'date' => '2024-01-15'],
            'rep_2' => ['weight' => 235, 'lift_log_id' => 2, 'date' => '2024-01-10'],
            'rep_3' => ['weight' => 230, 'lift_log_id' => 3, 'date' => '2024-01-08'],
        ];
        
        $result = $this->service->getCalculatorGrid($prData);
        
        $this->assertNotNull($result);
        $this->assertCount(3, $result['columns']);
        
        // Check column labels
        $this->assertEquals('1 × 1', $result['columns'][0]['label']);
        $this->assertEquals('1 × 2', $result['columns'][1]['label']);
        $this->assertEquals('1 × 3', $result['columns'][2]['label']);
        
        // Check that each row has 3 weights
        foreach ($result['rows'] as $row) {
            $this->assertCount(3, $row['weights']);
        }
    }

    /** @test */
    public function getCalculatorGrid_returns_null_when_no_PR_data()
    {
        $prData = [
            'rep_1' => null,
            'rep_2' => null,
            'rep_3' => null,
        ];
        
        $result = $this->service->getCalculatorGrid($prData);
        
        $this->assertNull($result);
    }

    /** @test */
    public function calculate1RM_uses_Brzycki_formula_correctly()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculate1RM');
        $method->setAccessible(true);
        
        // Test with 5 reps at 200 lbs
        // Brzycki: 1RM = weight × (36 / (37 - reps))
        // 1RM = 200 × (36 / (37 - 5)) = 200 × (36 / 32) = 200 × 1.125 = 225
        $result = $method->invoke($this->service, 200, 5);
        $this->assertEquals(225, $result);
        
        // Test with 10 reps at 150 lbs
        // 1RM = 150 × (36 / (37 - 10)) = 150 × (36 / 27) = 150 × 1.333... = 200
        $result = $method->invoke($this->service, 150, 10);
        $this->assertEquals(200, round($result));
    }

    /** @test */
    public function calculate1RM_handles_edge_cases()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculate1RM');
        $method->setAccessible(true);
        
        // Test with reps = 1 (should return weight as-is)
        $result = $method->invoke($this->service, 242, 1);
        $this->assertEquals(242, $result);
        
        // Test with reps >= 37 (should return weight as-is to avoid division issues)
        $result = $method->invoke($this->service, 100, 37);
        $this->assertEquals(100, $result);
        
        $result = $method->invoke($this->service, 100, 40);
        $this->assertEquals(100, $result);
    }

    /** @test */
    public function getEstimated1RM_returns_null_when_no_lift_logs_exist()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
        ]);

        $result = $this->service->getEstimated1RM($exercise, $this->user);

        $this->assertNull($result);
    }

    /** @test */
    public function getEstimated1RM_calculates_from_best_lift()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
        ]);

        // Create lift logs with various rep ranges (no 1-3 rep tests)
        $log1 = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log1->id,
            'weight' => 200,
            'reps' => 5,
        ]);

        $log2 = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log2->id,
            'weight' => 180,
            'reps' => 8,
        ]);

        $result = $this->service->getEstimated1RM($exercise, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_estimated']);
        $this->assertArrayHasKey('weight', $result);
        $this->assertArrayHasKey('based_on_reps', $result);
        $this->assertArrayHasKey('based_on_weight', $result);
        
        // 200 lbs × 5 reps should give higher estimated 1RM than 180 × 8
        // Using Epley: 200 * (1 + 0.0333 * 5) = 233.3
        $this->assertEquals(233, $result['weight']);
        $this->assertEquals(5, $result['based_on_reps']);
        $this->assertEquals(200, $result['based_on_weight']);
    }

    /** @test */
    public function getCalculatorGrid_uses_estimated_1RM_when_no_actual_PRs()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
        ]);

        $estimated1RM = [
            'weight' => 225,
            'is_estimated' => true,
            'based_on_reps' => 5,
            'based_on_weight' => 200,
        ];

        $result = $this->service->getCalculatorGrid($exercise, [], $estimated1RM);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_estimated']);
        $this->assertCount(1, $result['columns']);
        $this->assertEquals('Est. 1RM', $result['columns'][0]['label']);
        $this->assertEquals(225, $result['columns'][0]['one_rep_max']);
        $this->assertTrue($result['columns'][0]['is_estimated']);
        
        // Check that percentages are calculated correctly
        $this->assertCount(12, $result['rows']);
        $this->assertEquals(225, $result['rows'][0]['weights'][0]); // 100%
        $this->assertEquals(214, $result['rows'][1]['weights'][0]); // 95%
        $this->assertEquals(203, $result['rows'][2]['weights'][0]); // 90%
    }

    /** @test */
    public function getCalculatorGrid_prefers_actual_PRs_over_estimated()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
        ]);

        $prData = [
            'rep_1' => [
                'weight' => 242,
                'lift_log_id' => 1,
                'date' => '2025-11-20',
                'is_estimated' => false,
            ],
            'rep_2' => null,
            'rep_3' => null,
        ];

        $estimated1RM = [
            'weight' => 225,
            'is_estimated' => true,
        ];

        $result = $this->service->getCalculatorGrid($exercise, $prData, $estimated1RM);

        $this->assertNotNull($result);
        $this->assertFalse($result['is_estimated']); // Should use actual PR, not estimated
        $this->assertCount(1, $result['columns']);
        $this->assertEquals('1 × 1', $result['columns'][0]['label']);
        $this->assertEquals(242, $result['columns'][0]['one_rep_max']);
    }
}
