<?php

namespace Tests\Unit\Services\ExerciseTypes;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\StaticHoldExerciseType;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticHoldExerciseTypeTest extends TestCase
{
    use RefreshDatabase;

    private StaticHoldExerciseType $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new StaticHoldExerciseType();
    }

    /** @test */
    public function it_returns_correct_type_name()
    {
        $this->assertEquals('static_hold', $this->strategy->getTypeName());
    }

    /** @test */
    public function it_does_not_support_1rm_calculation()
    {
        $this->assertFalse($this->strategy->canCalculate1RM());
    }

    /** @test */
    public function it_processes_lift_data_correctly()
    {
        $data = [
            'time' => 30,      // 30 seconds hold
            'weight' => 25,    // 25 lbs added weight
            'sets' => 3,
            'band_color' => 'red', // Should be nullified
        ];

        $processed = $this->strategy->processLiftData($data);

        $this->assertEquals(30, $processed['time']);
        $this->assertEquals(1, $processed['reps']); // Always 1
        $this->assertEquals(25, $processed['weight']);
        $this->assertNull($processed['band_color']);
    }

    /** @test */
    public function it_processes_bodyweight_static_hold()
    {
        $data = [
            'time' => 45,      // 45 seconds hold
            'weight' => 0,     // No added weight
            'sets' => 3,
        ];

        $processed = $this->strategy->processLiftData($data);

        $this->assertEquals(45, $processed['time']);
        $this->assertEquals(1, $processed['reps']); // Always 1
        $this->assertEquals(0, $processed['weight']);
    }

    /** @test */
    public function it_validates_minimum_duration()
    {
        $this->expectException(InvalidExerciseDataException::class);

        $data = [
            'time' => 0,  // Invalid: too short
            'weight' => 0,
            'sets' => 3,
        ];

        $this->strategy->processLiftData($data);
    }

    /** @test */
    public function it_validates_maximum_duration()
    {
        $this->expectException(InvalidExerciseDataException::class);

        $data = [
            'time' => 301,  // Invalid: exceeds 5 minutes (300 seconds)
            'weight' => 0,
            'sets' => 3,
        ];

        $this->strategy->processLiftData($data);
    }

    /** @test */
    public function it_validates_negative_weight()
    {
        $this->expectException(InvalidExerciseDataException::class);

        $data = [
            'time' => 30,
            'weight' => -10,  // Invalid: negative weight
            'sets' => 3,
        ];

        $this->strategy->processLiftData($data);
    }

    /** @test */
    public function it_formats_bodyweight_hold_display()
    {
        $liftLog = $this->createMockLiftLog(30, 0);
        
        $display = $this->strategy->formatWeightDisplay($liftLog);
        
        $this->assertEquals('30s hold', $display);
    }

    /** @test */
    public function it_formats_weighted_hold_display()
    {
        $liftLog = $this->createMockLiftLog(30, 25);
        
        $display = $this->strategy->formatWeightDisplay($liftLog);
        
        $this->assertEquals('30s hold +25 lbs', $display);
    }

    /** @test */
    public function it_formats_long_duration_display()
    {
        $liftLog = $this->createMockLiftLog(90, 0); // 1 minute 30 seconds
        
        $display = $this->strategy->formatWeightDisplay($liftLog);
        
        $this->assertEquals('1m 30s hold', $display);
    }

    /** @test */
    public function it_formats_exact_minute_display()
    {
        $liftLog = $this->createMockLiftLog(120, 0); // 2 minutes
        
        $display = $this->strategy->formatWeightDisplay($liftLog);
        
        $this->assertEquals('2m hold', $display);
    }

    /** @test */
    public function it_formats_complete_display_with_sets()
    {
        $liftLog = $this->createMockLiftLog(30, 0, 3);
        
        $display = $this->strategy->formatCompleteDisplay($liftLog);
        
        $this->assertEquals('30s hold × 3 sets', $display);
    }

    /** @test */
    public function it_formats_complete_display_with_weight_and_sets()
    {
        $liftLog = $this->createMockLiftLog(45, 25, 3);
        
        $display = $this->strategy->formatCompleteDisplay($liftLog);
        
        $this->assertEquals('45s hold +25 lbs × 3 sets', $display);
    }

    /** @test */
    public function it_provides_progression_suggestion_for_short_holds()
    {
        $liftLog = $this->createMockLiftLog(20, 0, 3);
        
        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);
        
        $this->assertEquals('Try 21s hold × 3 sets', $suggestion);
    }

    /** @test */
    public function it_provides_progression_suggestion_for_medium_holds()
    {
        $liftLog = $this->createMockLiftLog(45, 0, 3);
        
        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);
        
        $this->assertEquals('Try 47s hold × 3 sets', $suggestion);
    }

    /** @test */
    public function it_suggests_adding_weight_for_long_bodyweight_holds()
    {
        $liftLog = $this->createMockLiftLog(60, 0, 3);
        
        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);
        
        $this->assertEquals('Try 1m hold +5 lbs × 3 sets', $suggestion);
    }

    /** @test */
    public function it_suggests_adding_sets_for_long_weighted_holds()
    {
        $liftLog = $this->createMockLiftLog(60, 25, 3);
        
        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);
        
        $this->assertEquals('Try 1m hold +25 lbs × 4 sets', $suggestion);
    }

    /** @test */
    public function it_returns_correct_chart_type()
    {
        $this->assertEquals('hold_duration_progression', $this->strategy->getChartType());
    }

    /** @test */
    public function it_returns_correct_display_info()
    {
        $info = $this->strategy->getTypeDisplayInfo();
        
        $this->assertEquals('fas fa-hand-paper', $info['icon']);
        $this->assertEquals('Static Hold', $info['name']);
    }

    /** @test */
    public function it_returns_correct_chart_title()
    {
        $this->assertEquals('Hold Duration Progress', $this->strategy->getChartTitle());
    }

    /** @test */
    public function it_formats_1rm_table_cell_as_not_applicable()
    {
        $liftLog = $this->createMockLiftLog(30, 0);
        
        $display = $this->strategy->format1RMTableCellDisplay($liftLog);
        
        $this->assertEquals('N/A (Static Hold)', $display);
    }

    /** @test */
    public function it_formats_success_message_for_bodyweight_hold()
    {
        $message = $this->strategy->formatSuccessMessageDescription(0, 1, 3, null, 30);
        
        $this->assertEquals('30s hold × 3 sets', $message);
    }

    /** @test */
    public function it_formats_success_message_for_weighted_hold()
    {
        $message = $this->strategy->formatSuccessMessageDescription(25, 1, 3, null, 45);
        
        $this->assertEquals('45s hold +25 lbs × 3 sets', $message);
    }

    /** @test */
    public function it_reads_duration_from_time_field_not_reps()
    {
        $liftLog = $this->createMockLiftLog(45, 0, 3);
        
        // Verify that formatWeightDisplay reads from time field
        $display = $this->strategy->formatWeightDisplay($liftLog);
        $this->assertEquals('45s hold', $display);
        
        // Verify that formatTableCellDisplay reads from time field
        $tableDisplay = $this->strategy->formatTableCellDisplay($liftLog);
        $this->assertEquals('45s hold', $tableDisplay['primary']);
    }

    /** @test */
    public function it_uses_time_field_for_mobile_summary_display()
    {
        $liftLog = $this->createMockLiftLog(60, 25, 3);
        
        $mobileDisplay = $this->strategy->formatMobileSummaryDisplay($liftLog);
        
        // Should show "1m hold +25 lbs" not "1s hold"
        $this->assertEquals('1m hold +25 lbs', $mobileDisplay['weight']);
        $this->assertEquals('3 sets', $mobileDisplay['repsSets']);
    }

    /** @test */
    public function it_uses_time_field_for_progression_suggestions()
    {
        $liftLog = $this->createMockLiftLog(30, 0, 3);
        
        $suggestion = $this->strategy->formatProgressionSuggestion($liftLog);
        
        // Should suggest 32s (30 + 2) not 3s (1 + 2)
        $this->assertEquals('Try 32s hold × 3 sets', $suggestion);
    }

    /** @test */
    public function it_validates_time_field_in_process_lift_data()
    {
        $data = [
            'time' => 45,
            'weight' => 10,
            'sets' => 3,
        ];

        $processed = $this->strategy->processLiftData($data);

        // Should set reps to 1 and keep time as 45
        $this->assertEquals(1, $processed['reps']);
        $this->assertEquals(45, $processed['time']);
        $this->assertEquals(10, $processed['weight']);
    }

    /**
     * Helper method to create a mock LiftLog for testing
     */
    private function createMockLiftLog(int $duration, float $weight, int $sets = 3): LiftLog
    {
        $liftLog = new LiftLog();
        $liftLog->display_reps = 1; // Always 1 for static holds
        $liftLog->display_weight = $weight;
        $liftLog->display_rounds = $sets;
        
        // Create mock exercise to avoid null pointer errors
        $mockExercise = new Exercise();
        $mockExercise->exercise_type = 'static_hold';
        $liftLog->setRelation('exercise', $mockExercise);
        
        // Create mock lift sets collection
        $liftSets = collect();
        for ($i = 0; $i < $sets; $i++) {
            $liftSets->push((object)[
                'reps' => 1,
                'time' => $duration,
                'weight' => $weight,
            ]);
        }
        $liftLog->setRelation('liftSets', $liftSets);
        
        return $liftLog;
    }
}
