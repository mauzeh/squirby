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
        
        $this->service = new ExercisePRService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function supportsPRTracking_returns_true_for_barbell()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'barbell']);
        
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
        $exercise = Exercise::factory()->create(['exercise_type' => 'barbell']);
        
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
        $exercise = Exercise::factory()->create(['exercise_type' => 'barbell']);
        
        $result = $this->service->getPRData($exercise, $this->user);
        
        $this->assertNull($result);
    }

    /** @test */
    public function getPRData_handles_partial_data_missing_rep_ranges()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'barbell']);
        
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
        $exercise = Exercise::factory()->create(['exercise_type' => 'barbell']);
        
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
        $this->assertEquals('1x1', $result['columns'][0]['label']);
        $this->assertEquals('1x2', $result['columns'][1]['label']);
        $this->assertEquals('1x3', $result['columns'][2]['label']);
        
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
}
