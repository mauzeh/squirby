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
            'black' => ['resistance' => 40, 'order' => 4],
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
        $this->assertStringContainsString("Invalid band color 'yellow' - must be one of: red, blue, green, black, none", $result['invalidRows'][0]);
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
}