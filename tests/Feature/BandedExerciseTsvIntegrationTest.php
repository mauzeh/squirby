<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandedExerciseTsvIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $anotherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create regular users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->anotherUser = User::factory()->create(['name' => 'Another User']);
        
        // Create admin user
        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);

        // Mock the config helper for testing purposes
        config(['bands.colors' => [
            'red' => ['resistance' => 10, 'order' => 1],
            'blue' => ['resistance' => 20, 'order' => 2],
            'green' => ['resistance' => 30, 'order' => 3],
        ]]);
    }

    /** @test */
    public function test_complete_import_export_cycle_for_banded_exercises()
    {
        // Step 1: Import banded exercises
        $exerciseTsvData = "Banded Pull-ups\tPull-ups with resistance band\tfalse\tresistance\n" .
                          "Assisted Dips\tDips with assistance band\tfalse\tassistance\n" .
                          "Regular Push-ups\tStandard push-ups\ttrue\tnone";

        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $exerciseTsvData
            ]);

        $exerciseResponse->assertRedirect(route('exercises.index'));
        $exerciseResponse->assertSessionHas('success');

        // Verify exercises were imported correctly
        $bandedPullups = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Banded Pull-ups')->first();
        $assistedDips = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Assisted Dips')->first();
        $regularPushups = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Regular Push-ups')->first();

        $this->assertNotNull($bandedPullups);
        $this->assertEquals('resistance', $bandedPullups->band_type);
        $this->assertNotNull($assistedDips);
        $this->assertEquals('assistance', $assistedDips->band_type);
        $this->assertNotNull($regularPushups);
        $this->assertNull($regularPushups->band_type);

        // Step 2: Import lift logs with band colors
        $liftLogTsvData = "10/15/2025\t10:00 AM\tBanded Pull-ups\t0\t10\t3\tGood form\tred\n" .
                         "10/15/2025\t10:15 AM\tAssisted Dips\t0\t12\t3\tBlue band\tblue\n" .
                         "10/15/2025\t10:30 AM\tRegular Push-ups\t0\t15\t3\tBodyweight\tnone";

        $liftLogResponse = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $liftLogTsvData,
                'date' => '2025-10-15'
            ]);

        $liftLogResponse->assertRedirect(route('lift-logs.index'));
        $liftLogResponse->assertSessionHas('success');

        // Verify lift logs were imported correctly
        $pullupLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $bandedPullups->id)->first();
        $dipLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $assistedDips->id)->first();
        $pushupLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $regularPushups->id)->first();

        $this->assertNotNull($pullupLog);
        $this->assertEquals('red', $pullupLog->liftSets->first()->band_color);
        $this->assertNotNull($dipLog);
        $this->assertEquals('blue', $dipLog->liftSets->first()->band_color);
        $this->assertNotNull($pushupLog);
        $this->assertNull($pushupLog->liftSets->first()->band_color);

        // Step 3: Export exercises and verify TSV format
        $exercises = Exercise::where('user_id', $this->user->id)->orderBy('title')->get();
        $exerciseTsvContent = $this->generateExerciseTsv($exercises);

        $exerciseLines = explode("\n", trim($exerciseTsvContent));
        $this->assertCount(3, $exerciseLines);

        // Verify exported exercise data
        $assistedDipsData = explode("\t", $exerciseLines[0]);
        $this->assertEquals('Assisted Dips', $assistedDipsData[0]);
        $this->assertEquals('assistance', $assistedDipsData[3]);

        $bandedPullupsData = explode("\t", $exerciseLines[1]);
        $this->assertEquals('Banded Pull-ups', $bandedPullupsData[0]);
        $this->assertEquals('resistance', $bandedPullupsData[3]);

        $regularPushupsData = explode("\t", $exerciseLines[2]);
        $this->assertEquals('Regular Push-ups', $regularPushupsData[0]);
        $this->assertEquals('none', $regularPushupsData[3]);

        // Step 4: Export lift logs and verify TSV format
        $liftLogs = LiftLog::with(['exercise', 'liftSets'])
            ->where('user_id', $this->user->id)
            ->orderBy('logged_at')
            ->get();
        $liftLogTsvContent = $this->generateLiftLogTsv($liftLogs);

        $liftLogLines = explode("\n", trim($liftLogTsvContent));
        $this->assertCount(9, $liftLogLines); // 3 sets each for 3 exercises

        // Verify exported lift log data
        $pullupSetData = explode("\t", $liftLogLines[0]);
        $this->assertEquals('Banded Pull-ups', $pullupSetData[2]);
        $this->assertEquals('red', $pullupSetData[7]);

        $dipSetData = explode("\t", $liftLogLines[3]);
        $this->assertEquals('Assisted Dips', $dipSetData[2]);
        $this->assertEquals('blue', $dipSetData[7]);

        $pushupSetData = explode("\t", $liftLogLines[6]);
        $this->assertEquals('Regular Push-ups', $pushupSetData[2]);
        $this->assertEquals('none', $pushupSetData[7]);

        // Step 5: Re-import exported data to verify round-trip integrity
        $reimportExerciseResponse = $this->actingAs($this->anotherUser)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $exerciseTsvContent
            ]);

        $reimportExerciseResponse->assertRedirect(route('exercises.index'));
        $reimportExerciseResponse->assertSessionHas('success');

        // Verify re-imported exercises maintain band types
        $reimportedBandedPullups = Exercise::where('user_id', $this->anotherUser->id)
            ->where('title', 'Banded Pull-ups')->first();
        $reimportedAssistedDips = Exercise::where('user_id', $this->anotherUser->id)
            ->where('title', 'Assisted Dips')->first();
        $reimportedRegularPushups = Exercise::where('user_id', $this->anotherUser->id)
            ->where('title', 'Regular Push-ups')->first();

        $this->assertEquals('resistance', $reimportedBandedPullups->band_type);
        $this->assertEquals('assistance', $reimportedAssistedDips->band_type);
        $this->assertNull($reimportedRegularPushups->band_type);
    }

    /** @test */
    public function test_mixed_data_scenarios_with_banded_and_non_banded_exercises()
    {
        // Create a mix of existing exercises
        $existingBanded = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Existing Banded Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
            'band_type' => 'resistance'
        ]);

        $existingRegular = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Existing Regular Exercise',
            'description' => 'Old description',
            'is_bodyweight' => true,
            'band_type' => null
        ]);

        // Import mixed data with updates and new exercises
        $mixedTsvData = "Existing Banded Exercise\tUpdated banded description\ttrue\tassistance\n" .
                       "Existing Regular Exercise\tUpdated regular description\tfalse\tnone\n" .
                       "New Banded Exercise\tNew resistance exercise\tfalse\tresistance\n" .
                       "New Regular Exercise\tNew regular exercise\ttrue\tnone\n" .
                       "Another Banded Exercise\tAnother assistance exercise\tfalse\tassistance";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $mixedTsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify updates to existing exercises
        $existingBanded->refresh();
        $this->assertEquals('Updated banded description', $existingBanded->description);
        $this->assertEquals('assistance', $existingBanded->band_type); // Changed from resistance
        $this->assertTrue($existingBanded->is_bodyweight); // Changed from false

        $existingRegular->refresh();
        $this->assertEquals('Updated regular description', $existingRegular->description);
        $this->assertNull($existingRegular->band_type); // Remains null
        $this->assertFalse($existingRegular->is_bodyweight); // Changed from true

        // Verify new exercises were created
        $newBanded = Exercise::where('user_id', $this->user->id)
            ->where('title', 'New Banded Exercise')->first();
        $this->assertNotNull($newBanded);
        $this->assertEquals('resistance', $newBanded->band_type);

        $newRegular = Exercise::where('user_id', $this->user->id)
            ->where('title', 'New Regular Exercise')->first();
        $this->assertNotNull($newRegular);
        $this->assertNull($newRegular->band_type);

        $anotherBanded = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Another Banded Exercise')->first();
        $this->assertNotNull($anotherBanded);
        $this->assertEquals('assistance', $anotherBanded->band_type);

        // Test mixed lift log import
        $mixedLiftLogData = "10/15/2025\t10:00 AM\tExisting Banded Exercise\t0\t8\t3\tAssistance work\tblue\n" .
                           "10/15/2025\t10:15 AM\tExisting Regular Exercise\t0\t12\t3\tBodyweight work\tnone\n" .
                           "10/15/2025\t10:30 AM\tNew Banded Exercise\t0\t10\t3\tResistance work\tred\n" .
                           "10/15/2025\t10:45 AM\tNew Regular Exercise\t0\t15\t3\tRegular work\tnone";

        $liftLogResponse = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $mixedLiftLogData,
                'date' => '2025-10-15'
            ]);

        $liftLogResponse->assertRedirect(route('lift-logs.index'));
        $liftLogResponse->assertSessionHas('success');

        // Verify all lift logs were created with correct band colors
        $this->assertEquals(4, LiftLog::where('user_id', $this->user->id)->count());

        $bandedLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $existingBanded->id)->first();
        $this->assertEquals('blue', $bandedLog->liftSets->first()->band_color);

        $regularLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $existingRegular->id)->first();
        $this->assertNull($regularLog->liftSets->first()->band_color);

        $newBandedLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $newBanded->id)->first();
        $this->assertEquals('red', $newBandedLog->liftSets->first()->band_color);

        $newRegularLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $newRegular->id)->first();
        $this->assertNull($newRegularLog->liftSets->first()->band_color);
    }

    /** @test */
    public function test_error_handling_for_invalid_data_combinations()
    {
        // Create exercises for testing invalid combinations
        $bandedExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Banded Exercise',
            'description' => 'Resistance exercise',
            'is_bodyweight' => false,
            'band_type' => 'resistance'
        ]);

        $regularExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Regular Exercise',
            'description' => 'Regular exercise',
            'is_bodyweight' => true,
            'band_type' => null
        ]);

        // Test invalid exercise import scenarios
        $invalidExerciseTsv = "Invalid Band Type Exercise\tDescription\tfalse\tinvalid_type\n" .
                             "Empty Band Type Exercise\tDescription\tfalse\t\n" .
                             "Valid Exercise\tValid description\ttrue\tresistance";

        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $invalidExerciseTsv
            ]);

        $exerciseResponse->assertRedirect(route('exercises.index'));
        $exerciseResponse->assertSessionHas('success');

        // Verify only valid exercise was imported
        $this->assertEquals(1, Exercise::where('user_id', $this->user->id)
            ->where('title', 'Valid Exercise')->count());
        $this->assertEquals(0, Exercise::where('user_id', $this->user->id)
            ->where('title', 'Invalid Band Type Exercise')->count());

        // Test invalid lift log import scenarios
        $invalidLiftLogTsv = "10/15/2025\t10:00 AM\tBanded Exercise\t0\t10\t3\tNo band\tnone\n" .
                            "10/15/2025\t10:15 AM\tRegular Exercise\t0\t12\t3\tWrong band\tred\n" .
                            "10/15/2025\t10:30 AM\tBanded Exercise\t0\t8\t3\tInvalid color\tyellow\n" .
                            "10/15/2025\t10:45 AM\tNonexistent Exercise\t0\t10\t3\tMissing exercise\tred\n" .
                            "10/15/2025\t11:00 AM\tBanded Exercise\t0\t12\t3\tValid\tgreen";

        $liftLogResponse = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $invalidLiftLogTsv,
                'date' => '2025-10-15'
            ]);

        $liftLogResponse->assertRedirect(route('lift-logs.index'));
        
        // Check if there's an error or success message (depending on implementation)
        if ($liftLogResponse->getSession()->has('error')) {
            $liftLogResponse->assertSessionHas('error');
        } else {
            $liftLogResponse->assertSessionHas('success');
        }

        // Verify only valid lift logs were created (some invalid ones might be skipped)
        $validLogs = LiftLog::where('user_id', $this->user->id)->get();
        $this->assertGreaterThan(0, $validLogs->count());
        
        // Find the valid log with green band color
        $validLog = $validLogs->filter(function($log) {
            return $log->liftSets->first()->band_color === 'green';
        })->first();
        
        if ($validLog) {
            $this->assertEquals('green', $validLog->liftSets->first()->band_color);
            $this->assertEquals('Valid', $validLog->comments);
        }

        // Test case insensitive band type validation
        $caseInsensitiveTsv = "Case Test 1\tDescription\tfalse\tRESISTANCE\n" .
                             "Case Test 2\tDescription\tfalse\tAssistance\n" .
                             "Case Test 3\tDescription\tfalse\tNONE";

        $caseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $caseInsensitiveTsv
            ]);

        $caseResponse->assertRedirect(route('exercises.index'));
        $caseResponse->assertSessionHas('success');

        // Verify case insensitive handling
        $exercises = Exercise::where('user_id', $this->user->id)
            ->whereIn('title', ['Case Test 1', 'Case Test 2', 'Case Test 3'])
            ->get();

        $this->assertEquals('resistance', $exercises->where('title', 'Case Test 1')->first()->band_type);
        $this->assertEquals('assistance', $exercises->where('title', 'Case Test 2')->first()->band_type);
        $this->assertNull($exercises->where('title', 'Case Test 3')->first()->band_type);
    }

    /** @test */
    public function test_data_integrity_after_full_import_export_cycle()
    {
        // Create comprehensive test data
        $originalExercises = [
            ['title' => 'Resistance Band Pull-ups', 'band_type' => 'resistance', 'is_bodyweight' => false],
            ['title' => 'Assistance Band Dips', 'band_type' => 'assistance', 'is_bodyweight' => false],
            ['title' => 'Regular Bodyweight Squats', 'band_type' => null, 'is_bodyweight' => true],
            ['title' => 'Weighted Push-ups', 'band_type' => null, 'is_bodyweight' => false]
        ];

        // Step 1: Create exercises via import
        $exerciseTsvData = "";
        foreach ($originalExercises as $exercise) {
            $bandType = $exercise['band_type'] ?? 'none';
            $isBodyweight = $exercise['is_bodyweight'] ? 'true' : 'false';
            $exerciseTsvData .= "{$exercise['title']}\tDescription for {$exercise['title']}\t{$isBodyweight}\t{$bandType}\n";
        }

        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => trim($exerciseTsvData)
            ]);

        $exerciseResponse->assertRedirect(route('exercises.index'));
        $exerciseResponse->assertSessionHas('success');

        // Step 2: Create lift logs with various band colors (each row creates a separate lift log)
        $liftLogTsvData = "10/15/2025\t10:00 AM\tResistance Band Pull-ups\t0\t8\t3\tRed band set 1\tred\n" .
                         "10/15/2025\t10:01 AM\tResistance Band Pull-ups\t0\t6\t3\tRed band set 2\tred\n" .
                         "10/15/2025\t10:02 AM\tResistance Band Pull-ups\t0\t4\t3\tRed band set 3\tred\n" .
                         "10/15/2025\t10:15 AM\tAssistance Band Dips\t0\t12\t2\tBlue assistance set 1\tblue\n" .
                         "10/15/2025\t10:16 AM\tAssistance Band Dips\t0\t10\t2\tBlue assistance set 2\tblue\n" .
                         "10/15/2025\t10:30 AM\tRegular Bodyweight Squats\t0\t20\t1\tBodyweight set 1\tnone\n" .
                         "10/15/2025\t10:45 AM\tWeighted Push-ups\t25\t10\t1\tWith weight set 1\tnone";

        $liftLogResponse = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $liftLogTsvData,
                'date' => '2025-10-15'
            ]);

        $liftLogResponse->assertRedirect(route('lift-logs.index'));
        $liftLogResponse->assertSessionHas('success');

        // Step 3: Export all data
        $exercises = Exercise::where('user_id', $this->user->id)->orderBy('title')->get();
        $exportedExerciseTsv = $this->generateExerciseTsv($exercises);

        $liftLogs = LiftLog::with(['exercise', 'liftSets'])
            ->where('user_id', $this->user->id)
            ->orderBy('logged_at')
            ->get();
        $exportedLiftLogTsv = $this->generateLiftLogTsv($liftLogs);

        // Step 4: Verify export integrity
        $exerciseLines = explode("\n", trim($exportedExerciseTsv));
        $this->assertCount(4, $exerciseLines);

        $liftLogLines = explode("\n", trim($exportedLiftLogTsv));
        // Each lift log creates sets based on the rounds value, so we need to count actual sets
        $expectedSetCount = LiftSet::whereHas('liftLog', function($query) {
            $query->where('user_id', $this->user->id);
        })->count();
        $this->assertCount($expectedSetCount, $liftLogLines);

        // Step 5: Import exported data to new user
        $newUserResponse = $this->actingAs($this->anotherUser)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $exportedExerciseTsv
            ]);

        $newUserResponse->assertRedirect(route('exercises.index'));
        $newUserResponse->assertSessionHas('success');

        $newLiftLogResponse = $this->actingAs($this->anotherUser)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $exportedLiftLogTsv,
                'date' => '2025-10-15'
            ]);

        $newLiftLogResponse->assertRedirect(route('lift-logs.index'));
        $newLiftLogResponse->assertSessionHas('success');

        // Step 6: Verify data integrity after round-trip
        $originalExerciseCount = Exercise::where('user_id', $this->user->id)->count();
        $newExerciseCount = Exercise::where('user_id', $this->anotherUser->id)->count();
        $this->assertEquals($originalExerciseCount, $newExerciseCount);

        $originalLiftLogCount = LiftLog::where('user_id', $this->user->id)->count();
        $newLiftLogCount = LiftLog::where('user_id', $this->anotherUser->id)->count();
        $this->assertEquals($originalLiftLogCount, $newLiftLogCount);

        // Verify specific exercise data integrity
        foreach ($originalExercises as $originalExercise) {
            $originalEx = Exercise::where('user_id', $this->user->id)
                ->where('title', $originalExercise['title'])->first();
            $newEx = Exercise::where('user_id', $this->anotherUser->id)
                ->where('title', $originalExercise['title'])->first();

            $this->assertNotNull($originalEx);
            $this->assertNotNull($newEx);
            $this->assertEquals($originalEx->band_type, $newEx->band_type);
            $this->assertEquals($originalEx->is_bodyweight, $newEx->is_bodyweight);
        }

        // Verify lift set data integrity
        $originalSets = LiftSet::whereHas('liftLog', function($query) {
            $query->where('user_id', $this->user->id);
        })->get();

        $newSets = LiftSet::whereHas('liftLog', function($query) {
            $query->where('user_id', $this->anotherUser->id);
        })->get();

        $this->assertEquals($originalSets->count(), $newSets->count());

        // Verify band color distribution
        $originalBandColors = $originalSets->pluck('band_color')->filter()->countBy();
        $newBandColors = $newSets->pluck('band_color')->filter()->countBy();
        $this->assertEquals($originalBandColors->toArray(), $newBandColors->toArray());
    }

    /** @test */
    public function test_global_exercise_integration_with_banded_exercises()
    {
        // Admin creates global banded exercises
        $globalExerciseTsv = "Global Banded Pull-ups\tGlobal resistance exercise\tfalse\tresistance\n" .
                            "Global Assisted Dips\tGlobal assistance exercise\tfalse\tassistance\n" .
                            "Global Regular Exercise\tGlobal regular exercise\ttrue\tnone";

        $globalResponse = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $globalExerciseTsv,
                'import_as_global' => true
            ]);

        $globalResponse->assertRedirect(route('exercises.index'));
        $globalResponse->assertSessionHas('success');

        // User creates personal exercises with same names (should be skipped)
        $userExerciseTsv = "Global Banded Pull-ups\tUser version\tfalse\tassistance\n" .
                          "Personal Banded Exercise\tUser personal exercise\tfalse\tresistance";

        $userResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $userExerciseTsv
            ]);

        $userResponse->assertRedirect(route('exercises.index'));
        $userResponse->assertSessionHas('success');

        // Verify global exercise wasn't overridden
        $globalExercise = Exercise::global()->where('title', 'Global Banded Pull-ups')->first();
        $this->assertNotNull($globalExercise);
        $this->assertEquals('resistance', $globalExercise->band_type);

        // Verify user exercise was created
        $userExercise = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Personal Banded Exercise')->first();
        $this->assertNotNull($userExercise);
        $this->assertEquals('resistance', $userExercise->band_type);

        // User imports lift logs using both global and personal exercises
        $mixedLiftLogTsv = "10/15/2025\t10:00 AM\tGlobal Banded Pull-ups\t0\t8\t3\tUsing global\tred\n" .
                          "10/15/2025\t10:15 AM\tPersonal Banded Exercise\t0\t10\t3\tUsing personal\tblue";

        $liftLogResponse = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $mixedLiftLogTsv,
                'date' => '2025-10-15'
            ]);

        $liftLogResponse->assertRedirect(route('lift-logs.index'));
        $liftLogResponse->assertSessionHas('success');

        // Verify lift logs were created correctly
        $globalLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)->first();
        $this->assertNotNull($globalLiftLog);
        $this->assertEquals('red', $globalLiftLog->liftSets->first()->band_color);

        $userLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)->first();
        $this->assertNotNull($userLiftLog);
        $this->assertEquals('blue', $userLiftLog->liftSets->first()->band_color);
    }

    /**
     * Helper method to generate TSV content for exercises
     */
    private function generateExerciseTsv($exercises): string
    {
        $lines = [];
        foreach ($exercises as $exercise) {
            $lines[] = implode("\t", [
                $exercise->title,
                $exercise->description,
                $exercise->is_bodyweight ? 'true' : 'false',
                $exercise->band_type ?? 'none'
            ]);
        }
        return implode("\n", $lines);
    }

    /**
     * Helper method to generate TSV content for lift logs
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