<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\TsvImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogTsvImportBandedTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tsvImporterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tsvImporterService = app(TsvImporterService::class);
        
        // Mock the config helper for testing purposes
        config(['bands.colors' => [
            'red' => ['resistance' => 10, 'order' => 1],
            'blue' => ['resistance' => 20, 'order' => 2],
            'green' => ['resistance' => 30, 'order' => 3],
        ]]);
    }

    /** @test */
    public function it_imports_lift_logs_with_valid_band_color_for_banded_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Pull-ups',
            'band_type' => 'resistance'
        ]);

        $tsvData = "2025-08-26\t08:00\tBanded Pull-ups\t0\t10\t3\tGood form\tred";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        $liftLog = LiftLog::first();
        $this->assertEquals($exercise->id, $liftLog->exercise_id);
        $this->assertEquals('Good form', $liftLog->comments);
        
        $liftSet = $liftLog->liftSets->first();
        $this->assertEquals('red', $liftSet->band_color);
        $this->assertEquals(0, $liftSet->weight);
        $this->assertEquals(10, $liftSet->reps);
    }

    /** @test */
    public function it_imports_lift_logs_with_none_band_color_for_non_banded_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Push-ups',
            'band_type' => null
        ]);

        $tsvData = "2025-08-26\t08:00\tRegular Push-ups\t0\t15\t3\tBodyweight\tnone";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        $liftLog = LiftLog::first();
        $liftSet = $liftLog->liftSets->first();
        $this->assertNull($liftSet->band_color);
    }

    /** @test */
    public function it_rejects_lift_logs_with_none_band_color_for_banded_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Squats',
            'band_type' => 'resistance'
        ]);

        $tsvData = "2025-08-26\t08:00\tBanded Squats\t0\t12\t3\tNo band\tnone";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertStringContainsString("Invalid band color 'none' for banded exercise 'Banded Squats'", $result['invalidRows'][0]);
    }

    /** @test */
    public function it_rejects_lift_logs_with_band_color_for_non_banded_exercises()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Bench Press',
            'band_type' => null
        ]);

        $tsvData = "2025-08-26\t08:00\tRegular Bench Press\t135\t8\t3\tHeavy set\tred";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertStringContainsString("Invalid band color 'red' for non-banded exercise 'Regular Bench Press'", $result['invalidRows'][0]);
    }

    /** @test */
    public function it_rejects_lift_logs_with_invalid_band_color()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Rows',
            'band_type' => 'assistance'
        ]);

        $tsvData = "2025-08-26\t08:00\tBanded Rows\t0\t10\t3\tGood form\tyellow";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertStringContainsString("Invalid band color 'yellow' - must be one of: red, blue, green, none", $result['invalidRows'][0]);
    }

    /** @test */
    public function it_handles_missing_band_color_column_gracefully()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Deadlift',
            'band_type' => null
        ]);

        // TSV data without band_color column (old format)
        $tsvData = "2025-08-26\t08:00\tRegular Deadlift\t225\t5\t3\tHeavy set";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        $liftLog = LiftLog::first();
        $liftSet = $liftLog->liftSets->first();
        $this->assertNull($liftSet->band_color);
    }

    /** @test */
    public function it_imports_multiple_banded_exercises_with_different_colors()
    {
        $exercise1 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Pull-ups',
            'band_type' => 'resistance'
        ]);
        
        $exercise2 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Assisted Dips',
            'band_type' => 'assistance'
        ]);

        $tsvData = "2025-08-26\t08:00\tBanded Pull-ups\t0\t8\t3\tRed band\tred\n" .
                   "2025-08-26\t08:15\tAssisted Dips\t0\t12\t3\tBlue band\tblue";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        $liftLogs = LiftLog::with('liftSets')->get();
        $this->assertCount(2, $liftLogs);
        
        $pullUpLog = $liftLogs->where('exercise_id', $exercise1->id)->first();
        $this->assertEquals('red', $pullUpLog->liftSets->first()->band_color);
        
        $dipLog = $liftLogs->where('exercise_id', $exercise2->id)->first();
        $this->assertEquals('blue', $dipLog->liftSets->first()->band_color);
    }

    /** @test */
    public function it_considers_band_color_in_duplicate_detection()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Squats',
            'band_type' => 'resistance'
        ]);

        // Create existing lift log with red band
        $existingLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => '2025-08-26 08:00:00',
            'comments' => 'Red band workout'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $existingLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'red'
        ]);

        // Try to import same exercise with different band color - should not be duplicate
        $tsvData = "2025-08-26\t08:00\tBanded Squats\t0\t10\t1\tBlue band workout\tblue";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        // Should have 2 lift logs now
        $this->assertCount(2, LiftLog::all());
    }

    /** @test */
    public function it_provides_descriptive_error_messages_for_band_color_validation_failures()
    {
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Exercise',
            'band_type' => 'resistance'
        ]);

        $nonBandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Non-Banded Exercise',
            'band_type' => null
        ]);

        $tsvData = "2025-08-26\t08:00\tBanded Exercise\t0\t10\t3\tNo band specified\tnone\n" .
                   "2025-08-26\t08:15\tNon-Banded Exercise\t135\t8\t3\tWrong band color\tred\n" .
                   "2025-08-26\t08:30\tBanded Exercise\t0\t12\t3\tInvalid color\tyellow";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(3, $result['invalidRows']);

        // Verify specific error messages for different validation failures
        $this->assertStringContainsString("Invalid band color 'none' for banded exercise 'Banded Exercise'", $result['invalidRows'][0]);
        $this->assertStringContainsString("Invalid band color 'red' for non-banded exercise 'Non-Banded Exercise'", $result['invalidRows'][1]);
        $this->assertStringContainsString("Invalid band color 'yellow' - must be one of: red, blue, green, none", $result['invalidRows'][2]);
    }

    /** @test */
    public function it_validates_band_colors_against_configured_colors()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Exercise',
            'band_type' => 'resistance'
        ]);

        // Test with colors not in config
        $tsvData = "2025-08-26\t08:00\tBanded Exercise\t0\t10\t3\tPurple band\tpurple\n" .
                   "2025-08-26\t08:15\tBanded Exercise\t0\t10\t3\tOrange band\torange";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(2, $result['invalidRows']);

        // Verify error messages reference the configured colors
        foreach ($result['invalidRows'] as $invalidRow) {
            $this->assertStringContainsString("must be one of: red, blue, green, none", $invalidRow);
        }
    }

    /** @test */
    public function it_exports_lift_logs_to_tsv_format_with_band_colors()
    {
        // Create banded exercise
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Pull-ups',
            'band_type' => 'resistance'
        ]);

        // Create regular exercise
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Bench Press',
            'band_type' => null
        ]);

        // Create lift logs with different band colors
        $bandedLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => '2025-08-26 08:00:00',
            'comments' => 'Red band workout'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $bandedLiftLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'red'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $bandedLiftLog->id,
            'weight' => 0,
            'reps' => 8,
            'band_color' => 'red'
        ]);

        $regularLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $regularExercise->id,
            'logged_at' => '2025-08-26 08:15:00',
            'comments' => 'Heavy set'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $regularLiftLog->id,
            'weight' => 135,
            'reps' => 8,
            'band_color' => null
        ]);

        $liftLogs = LiftLog::with(['exercise', 'liftSets'])
            ->where('user_id', $this->user->id)
            ->orderBy('logged_at')
            ->get();

        $tsvContent = $this->generateLiftLogTsv($liftLogs);

        // Verify TSV format and content
        $lines = explode("\n", trim($tsvContent));
        $this->assertCount(3, $lines); // 2 sets for banded + 1 set for regular

        // Check first banded set
        $firstSetData = explode("\t", $lines[0]);
        $this->assertEquals('08/26/2025', $firstSetData[0]);
        $this->assertEquals('8:00 AM', $firstSetData[1]);
        $this->assertEquals('Banded Pull-ups', $firstSetData[2]);
        $this->assertEquals('0', $firstSetData[3]);
        $this->assertEquals('10', $firstSetData[4]);
        $this->assertEquals('2', $firstSetData[5]); // Total rounds for this lift log
        $this->assertEquals('Red band workout', $firstSetData[6]);
        $this->assertEquals('red', $firstSetData[7]);

        // Check second banded set
        $secondSetData = explode("\t", $lines[1]);
        $this->assertEquals('red', $secondSetData[7]);
        $this->assertEquals('8', $secondSetData[4]); // Different reps

        // Check regular exercise set
        $regularSetData = explode("\t", $lines[2]);
        $this->assertEquals('Regular Bench Press', $regularSetData[2]);
        $this->assertEquals('135', $regularSetData[3]);
        $this->assertEquals('none', $regularSetData[7]);
    }

    /** @test */
    public function it_exports_multiple_lift_sets_as_separate_tsv_rows()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Banded Squats',
            'band_type' => 'resistance'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => '2025-08-26 09:00:00',
            'comments' => 'Progressive sets'
        ]);

        // Create multiple sets with different band colors
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 12,
            'band_color' => 'red'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'blue'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 8,
            'band_color' => 'green'
        ]);

        $liftLogs = LiftLog::with(['exercise', 'liftSets'])
            ->where('user_id', $this->user->id)
            ->get();

        $tsvContent = $this->generateLiftLogTsv($liftLogs);

        // Verify each set is exported as separate row
        $lines = explode("\n", trim($tsvContent));
        $this->assertCount(3, $lines);

        // Verify each row has correct band color and reps
        $firstSetData = explode("\t", $lines[0]);
        $this->assertEquals('12', $firstSetData[4]);
        $this->assertEquals('red', $firstSetData[7]);

        $secondSetData = explode("\t", $lines[1]);
        $this->assertEquals('10', $secondSetData[4]);
        $this->assertEquals('blue', $secondSetData[7]);

        $thirdSetData = explode("\t", $lines[2]);
        $this->assertEquals('8', $thirdSetData[4]);
        $this->assertEquals('green', $thirdSetData[7]);

        // Verify all rows have same total rounds count
        foreach ($lines as $line) {
            $data = explode("\t", $line);
            $this->assertEquals('3', $data[5]); // Total rounds should be 3 for all
        }
    }

    /** @test */
    public function it_exports_mixed_banded_and_regular_exercises_correctly()
    {
        // Create mixed exercises
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Assisted Dips',
            'band_type' => 'assistance'
        ]);

        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Barbell Rows',
            'band_type' => null
        ]);

        // Create lift logs
        $bandedLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => '2025-08-26 10:00:00',
            'comments' => 'Assistance work'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $bandedLog->id,
            'weight' => 0,
            'reps' => 15,
            'band_color' => 'blue'
        ]);

        $regularLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $regularExercise->id,
            'logged_at' => '2025-08-26 10:15:00',
            'comments' => 'Back work'
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $regularLog->id,
            'weight' => 95,
            'reps' => 12,
            'band_color' => null
        ]);

        $liftLogs = LiftLog::with(['exercise', 'liftSets'])
            ->where('user_id', $this->user->id)
            ->orderBy('logged_at')
            ->get();

        $tsvContent = $this->generateLiftLogTsv($liftLogs);

        $lines = explode("\n", trim($tsvContent));
        $this->assertCount(2, $lines);

        // Check banded exercise export
        $bandedData = explode("\t", $lines[0]);
        $this->assertEquals('Assisted Dips', $bandedData[2]);
        $this->assertEquals('0', $bandedData[3]);
        $this->assertEquals('blue', $bandedData[7]);

        // Check regular exercise export
        $regularData = explode("\t", $lines[1]);
        $this->assertEquals('Barbell Rows', $regularData[2]);
        $this->assertEquals('95', $regularData[3]);
        $this->assertEquals('none', $regularData[7]);
    }

    /**
     * Helper method to generate TSV content for lift logs (simulating export functionality)
     */
    private function generateLiftLogTsv($liftLogs): string
    {
        $lines = [];
        foreach ($liftLogs as $liftLog) {
            foreach ($liftLog->liftSets as $liftSet) {
                $lines[] = implode("\t", [
                    $liftLog->logged_at->format('m/d/Y'),
                    $liftLog->logged_at->format('g:i A'),
                    $liftLog->exercise->title,
                    $liftSet->weight,
                    $liftSet->reps,
                    $liftLog->liftSets->count(),
                    $liftLog->comments ?? '',
                    $liftSet->band_color ?? 'none'
                ]);
            }
        }
        return implode("\n", $lines);
    }
}