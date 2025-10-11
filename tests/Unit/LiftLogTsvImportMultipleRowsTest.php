<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Exercise;
use App\Models\User;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogTsvImportMultipleRowsTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;
    protected $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService(new \App\Services\IngredientTsvProcessorService());
        $this->user = User::factory()->create();
        // Clear any existing exercises to avoid conflicts
        Exercise::where('user_id', $this->user->id)->delete();
        
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
        ]);
    }

    /** @test */
    public function it_imports_multiple_different_lift_logs_for_same_exercise()
    {
        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tWarm up set\n" .
                   "8/4/2025\t6:15 PM\tBench Press\t155\t5\t3\tWorking set\n" .
                   "8/4/2025\t6:30 PM\tBench Press\t175\t3\t2\tHeavy set";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-04', $this->user->id);



        $this->assertEquals(3, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['invalidRows']);

        // Verify all three lift logs were created
        $liftLogs = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->orderBy('logged_at')
            ->get();

        $this->assertCount(3, $liftLogs);

        // Check first lift log
        $firstLog = $liftLogs[0];
        $this->assertEquals('Warm up set', $firstLog->comments);
        $this->assertCount(3, $firstLog->liftSets);
        $this->assertEquals(135, $firstLog->liftSets->first()->weight);
        $this->assertEquals(5, $firstLog->liftSets->first()->reps);

        // Check second lift log
        $secondLog = $liftLogs[1];
        $this->assertEquals('Working set', $secondLog->comments);
        $this->assertCount(3, $secondLog->liftSets);
        $this->assertEquals(155, $secondLog->liftSets->first()->weight);

        // Check third lift log
        $thirdLog = $liftLogs[2];
        $this->assertEquals('Heavy set', $thirdLog->comments);
        $this->assertCount(2, $thirdLog->liftSets);
        $this->assertEquals(175, $thirdLog->liftSets->first()->weight);
        $this->assertEquals(3, $thirdLog->liftSets->first()->reps);
    }

    /** @test */
    public function it_still_detects_exact_duplicates()
    {
        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tWarm up set\n" .
                   "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tWarm up set"; // Exact duplicate

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-04', $this->user->id);

        $this->assertEquals(1, $result['importedCount']); // Only one should be imported
        $this->assertEquals(0, $result['updatedCount']);

        // Verify only one lift log was created
        $liftLogs = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->get();

        $this->assertCount(1, $liftLogs);
    }

    /** @test */
    public function it_updates_existing_when_sets_match_but_comments_differ()
    {
        // Create existing lift log
        $existingLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => \Carbon\Carbon::createFromFormat('m/d/Y g:i A', '8/4/2025 6:00 PM'),
            'comments' => 'Old comments',
        ]);

        // Add lift sets
        for ($i = 0; $i < 3; $i++) {
            $existingLog->liftSets()->create([
                'weight' => 135,
                'reps' => 5,
                'notes' => 'Old comments',
            ]);
        }

        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tUpdated comments";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-04', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);

        $existingLog->refresh();
        $this->assertEquals('Updated comments', $existingLog->comments);
        
        // Verify that lift set notes were also updated
        foreach ($existingLog->liftSets as $set) {
            $this->assertEquals('Updated comments', $set->notes);
        }
    }
}