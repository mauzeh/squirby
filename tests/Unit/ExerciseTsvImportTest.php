<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService(new \App\Services\IngredientTsvProcessorService());
        $this->user = User::factory()->create();
        
        // Create admin user
        $this->admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function it_imports_exercises_from_tsv_data()
    {
        $initialCount = Exercise::where('user_id', $this->user->id)->count();
        
        $tsvData = "Burpees\tFull body bodyweight exercise\ttrue\nDumbbell Rows\tBack exercise with dumbbells\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertEquals('personal', $result['importMode']);
        $this->assertCount(2, $result['importedExercises']);
        $this->assertEmpty($result['updatedExercises']);
        $this->assertEmpty($result['skippedExercises']);

        $exercises = Exercise::where('user_id', $this->user->id)->get();
        $this->assertCount($initialCount + 2, $exercises);

        $burpees = $exercises->where('title', 'Burpees')->first();
        $this->assertNotNull($burpees);
        $this->assertEquals('Full body bodyweight exercise', $burpees->description);
        $this->assertTrue($burpees->is_bodyweight);

        $dumbbellRows = $exercises->where('title', 'Dumbbell Rows')->first();
        $this->assertNotNull($dumbbellRows);
        $this->assertEquals('Back exercise with dumbbells', $dumbbellRows->description);
        $this->assertFalse($dumbbellRows->is_bodyweight);

        // Verify detailed exercise lists
        $importedExercise = $result['importedExercises'][0];
        $this->assertEquals('Burpees', $importedExercise['title']);
        $this->assertEquals('personal', $importedExercise['type']);
        $this->assertTrue($importedExercise['is_bodyweight']);
    }

    /** @test */
    public function it_updates_existing_exercises()
    {
        // Create an existing exercise
        $existingExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Mountain Climbers',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "Mountain Climbers\tUpdated bodyweight exercise\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertCount(1, $result['updatedExercises']);

        $existingExercise->refresh();
        $this->assertEquals('Updated bodyweight exercise', $existingExercise->description);
        $this->assertTrue($existingExercise->is_bodyweight);

        // Verify change tracking
        $updatedExercise = $result['updatedExercises'][0];
        $this->assertEquals('Mountain Climbers', $updatedExercise['title']);
        $this->assertEquals('personal', $updatedExercise['type']);
        $this->assertArrayHasKey('changes', $updatedExercise);
        $this->assertArrayHasKey('description', $updatedExercise['changes']);
        $this->assertArrayHasKey('is_bodyweight', $updatedExercise['changes']);
    }

    /** @test */
    public function it_handles_invalid_rows()
    {
        $tsvData = "Jumping Jacks\tCardio bodyweight exercise\ttrue\nInvalid\nKettlebell Swings\tHip hinge exercise\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertStringContainsString('Invalid', $result['invalidRows'][0]);
    }

    /** @test */
    public function it_handles_boolean_variations()
    {
        $tsvData = "Plank Hold\tCore stability exercise\t1\nBarbell Curls\tBicep exercise\t0\nWall Sits\tLeg endurance exercise\tTRUE\nLat Pulldowns\tBack exercise\tFALSE";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(4, $result['importedCount']);

        $exercises = Exercise::where('user_id', $this->user->id)->get();
        
        $this->assertTrue($exercises->where('title', 'Plank Hold')->first()->is_bodyweight);
        $this->assertFalse($exercises->where('title', 'Barbell Curls')->first()->is_bodyweight);
        $this->assertTrue($exercises->where('title', 'Wall Sits')->first()->is_bodyweight);
        $this->assertFalse($exercises->where('title', 'Lat Pulldowns')->first()->is_bodyweight);
    }

    /** @test */
    public function it_handles_two_column_input_with_default_bodyweight_false()
    {
        $tsvData = "Running\tCardio exercise\nSwimming\tFull body cardio";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['invalidRows']);

        $exercises = Exercise::where('user_id', $this->user->id)->get();
        
        $running = $exercises->where('title', 'Running')->first();
        $this->assertNotNull($running);
        $this->assertEquals('Cardio exercise', $running->description);
        $this->assertFalse($running->is_bodyweight); // Should default to false

        $swimming = $exercises->where('title', 'Swimming')->first();
        $this->assertNotNull($swimming);
        $this->assertEquals('Full body cardio', $swimming->description);
        $this->assertFalse($swimming->is_bodyweight); // Should default to false
    }

    /** @test */
    public function it_imports_global_exercises_when_admin()
    {
        $tsvData = "Global Burpees\tGlobal bodyweight exercise\ttrue\nGlobal Squats\tGlobal leg exercise\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->admin->id, true);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEquals('global', $result['importMode']);
        $this->assertCount(2, $result['importedExercises']);

        $globalExercises = Exercise::global()->get();
        $this->assertCount(2, $globalExercises);

        $globalBurpees = $globalExercises->where('title', 'Global Burpees')->first();
        $this->assertNotNull($globalBurpees);
        $this->assertTrue($globalBurpees->isGlobal());
        $this->assertTrue($globalBurpees->is_bodyweight);

        // Verify detailed exercise lists
        $importedExercise = $result['importedExercises'][0];
        $this->assertEquals('global', $importedExercise['type']);
    }

    /** @test */
    public function it_throws_exception_when_non_admin_tries_global_import()
    {
        $tsvData = "Global Exercise\tDescription\ttrue";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only administrators can import global exercises.');

        $this->tsvImporterService->importExercises($tsvData, $this->user->id, true);
    }

    /** @test */
    public function it_updates_existing_global_exercises()
    {
        // Create existing global exercise
        $existingGlobal = Exercise::create([
            'user_id' => null,
            'title' => 'Global Push-ups',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "Global Push-ups\tUpdated global exercise\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->admin->id, true);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertCount(1, $result['updatedExercises']);

        $existingGlobal->refresh();
        $this->assertEquals('Updated global exercise', $existingGlobal->description);
        $this->assertTrue($existingGlobal->is_bodyweight);

        // Verify change tracking
        $updatedExercise = $result['updatedExercises'][0];
        $this->assertEquals('global', $updatedExercise['type']);
        $this->assertArrayHasKey('changes', $updatedExercise);
    }

    /** @test */
    public function it_skips_user_exercise_when_global_conflict_exists()
    {
        // Create global exercise
        Exercise::create([
            'user_id' => null,
            'title' => 'Conflicting Exercise',
            'description' => 'Global exercise',
            'is_bodyweight' => true,
        ]);

        $tsvData = "Conflicting Exercise\tUser exercise\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id, false);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(1, $result['skippedCount']);
        $this->assertCount(1, $result['skippedExercises']);

        $skippedExercise = $result['skippedExercises'][0];
        $this->assertEquals('Conflicting Exercise', $skippedExercise['title']);
        $this->assertStringContainsString('conflicts with existing global exercise', $skippedExercise['reason']);

        // Verify no user exercise was created
        $userExercises = Exercise::userSpecific($this->user->id)->get();
        $this->assertCount(0, $userExercises);
    }

    /** @test */
    public function it_skips_exercises_with_same_data()
    {
        // Create existing exercise with same data
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Same Exercise',
            'description' => 'Same description',
            'is_bodyweight' => true,
        ]);

        $tsvData = "Same Exercise\tSame description\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id, false);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(1, $result['skippedCount']);
        $this->assertCount(1, $result['skippedExercises']);

        $skippedExercise = $result['skippedExercises'][0];
        $this->assertEquals('Same Exercise', $skippedExercise['title']);
        $this->assertStringContainsString('already exists with same data', $skippedExercise['reason']);
    }

    /** @test */
    public function it_handles_case_insensitive_matching()
    {
        // Create existing exercise
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Push-Ups',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "PUSH-UPS\tUpdated description\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id, false);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);

        $exercises = Exercise::userSpecific($this->user->id)->get();
        $this->assertCount(1, $exercises);
        $this->assertEquals('Updated description', $exercises->first()->description);
    }

    /** @test */
    public function it_skips_global_exercise_when_user_conflict_exists()
    {
        // Create user exercise
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Conflicting Exercise',
            'description' => 'User exercise',
            'is_bodyweight' => false,
        ]);

        $tsvData = "Conflicting Exercise\tGlobal exercise\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->admin->id, true);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(1, $result['skippedCount']);
        $this->assertCount(1, $result['skippedExercises']);

        $skippedExercise = $result['skippedExercises'][0];
        $this->assertEquals('Conflicting Exercise', $skippedExercise['title']);
        $this->assertStringContainsString('conflicts with existing user exercise', $skippedExercise['reason']);

        // Verify no global exercise was created
        $globalExercises = Exercise::global()->get();
        $this->assertCount(0, $globalExercises);
    }

    /** @test */
    public function it_processes_mixed_import_scenarios()
    {
        // Create existing user exercise
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Existing User Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        // Create global exercise that will conflict
        Exercise::create([
            'user_id' => null,
            'title' => 'Global Conflict',
            'description' => 'Global exercise',
            'is_bodyweight' => true,
        ]);

        $tsvData = "New Exercise\tNew description\ttrue\n" .
                   "Existing User Exercise\tUpdated description\tfalse\n" .
                   "Global Conflict\tUser attempt\tfalse\n" .
                   "Same Exercise\tSame description\ttrue\n" .
                   "Same Exercise\tSame description\ttrue";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id, false);

        $this->assertEquals(2, $result['importedCount']); // New Exercise + Same Exercise (first occurrence)
        $this->assertEquals(1, $result['updatedCount']); // Existing User Exercise
        $this->assertEquals(2, $result['skippedCount']); // Global Conflict + Same Exercise (second occurrence)

        $this->assertCount(2, $result['importedExercises']);
        $this->assertCount(1, $result['updatedExercises']);
        $this->assertCount(2, $result['skippedExercises']);
    }

    /** @test */
    public function it_imports_exercises_with_valid_band_types()
    {
        $tsvData = "Banded Pull-ups\tPull-ups with resistance band\tfalse\tresistance\n" .
                   "Assisted Dips\tDips with assistance band\tfalse\tassistance\n" .
                   "Regular Push-ups\tStandard push-ups\ttrue\tnone";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(3, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);

        $exercises = Exercise::where('user_id', $this->user->id)->get();
        
        $bandedPullups = $exercises->where('title', 'Banded Pull-ups')->first();
        $this->assertNotNull($bandedPullups);
        $this->assertEquals('resistance', $bandedPullups->band_type);

        $assistedDips = $exercises->where('title', 'Assisted Dips')->first();
        $this->assertNotNull($assistedDips);
        $this->assertEquals('assistance', $assistedDips->band_type);

        $regularPushups = $exercises->where('title', 'Regular Push-ups')->first();
        $this->assertNotNull($regularPushups);
        $this->assertNull($regularPushups->band_type); // 'none' should be converted to null

        // Verify band_type is included in result arrays
        $importedExercise = $result['importedExercises'][0];
        $this->assertArrayHasKey('band_type', $importedExercise);
    }

    /** @test */
    public function it_rejects_exercises_with_invalid_band_types()
    {
        $tsvData = "Valid Exercise\tValid description\ttrue\tresistance\n" .
                   "Invalid Exercise\tInvalid band type\tfalse\tinvalid_type\n" .
                   "Another Valid\tAnother valid exercise\tfalse\tnone";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']); // Only valid exercises
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertCount(1, $result['invalidRows']); // One invalid row

        $invalidRow = $result['invalidRows'][0];
        $this->assertStringContainsString('Invalid band type', $invalidRow);
        $this->assertStringContainsString('invalid_type', $invalidRow);
        $this->assertStringContainsString("must be 'resistance', 'assistance', or 'none'", $invalidRow);

        // Verify only valid exercises were created
        $exercises = Exercise::where('user_id', $this->user->id)->get();
        $this->assertCount(2, $exercises);
        $this->assertNull($exercises->where('title', 'Invalid Exercise')->first());
    }

    /** @test */
    public function it_updates_existing_exercises_with_band_type_changes()
    {
        // Create existing exercise without band_type
        $existingExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Banded Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $tsvData = "Banded Exercise\tUpdated description\ttrue\tresistance";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);

        $existingExercise->refresh();
        $this->assertEquals('Updated description', $existingExercise->description);
        $this->assertTrue($existingExercise->is_bodyweight);
        $this->assertEquals('resistance', $existingExercise->band_type);

        // Verify change tracking includes band_type
        $updatedExercise = $result['updatedExercises'][0];
        $this->assertArrayHasKey('changes', $updatedExercise);
        $this->assertArrayHasKey('band_type', $updatedExercise['changes']);
        $this->assertEquals(null, $updatedExercise['changes']['band_type']['from']);
        $this->assertEquals('resistance', $updatedExercise['changes']['band_type']['to']);
    }

    /** @test */
    public function it_handles_band_type_case_insensitivity()
    {
        $tsvData = "Exercise 1\tDescription\tfalse\tRESISTANCE\n" .
                   "Exercise 2\tDescription\tfalse\tAssistance\n" .
                   "Exercise 3\tDescription\tfalse\tNONE";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(3, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);

        $exercises = Exercise::where('user_id', $this->user->id)->get();
        
        $this->assertEquals('resistance', $exercises->where('title', 'Exercise 1')->first()->band_type);
        $this->assertEquals('assistance', $exercises->where('title', 'Exercise 2')->first()->band_type);
        $this->assertNull($exercises->where('title', 'Exercise 3')->first()->band_type);
    }

    /** @test */
    public function it_defaults_to_none_band_type_when_column_missing()
    {
        // Test with 3-column format (no band_type column)
        $tsvData = "Exercise 1\tDescription\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);

        $exercise = Exercise::where('user_id', $this->user->id)->first();
        $this->assertNull($exercise->band_type); // Should default to null (none)
    }

    /** @test */
    public function it_imports_global_exercises_with_band_types()
    {
        $tsvData = "Global Banded Exercise\tGlobal resistance exercise\tfalse\tresistance";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->admin->id, true);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEquals('global', $result['importMode']);

        $globalExercise = Exercise::global()->first();
        $this->assertNotNull($globalExercise);
        $this->assertEquals('resistance', $globalExercise->band_type);

        // Verify band_type is included in result
        $importedExercise = $result['importedExercises'][0];
        $this->assertEquals('resistance', $importedExercise['band_type']);
        $this->assertEquals('global', $importedExercise['type']);
    }

    /** @test */
    public function it_provides_descriptive_error_messages_for_invalid_band_types()
    {
        $tsvData = "Exercise 1\tDescription\tfalse\tinvalid_band\n" .
                   "Exercise 2\tDescription\tfalse\twrong_type\n" .
                   "Exercise 3\tDescription\tfalse\tbad_value";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertCount(3, $result['invalidRows']);

        // Verify each error message contains the specific invalid band type and expected format
        foreach ($result['invalidRows'] as $invalidRow) {
            $this->assertStringContainsString("Invalid band type", $invalidRow);
            $this->assertStringContainsString("must be 'resistance', 'assistance', or 'none'", $invalidRow);
        }

        // Verify specific error messages
        $this->assertStringContainsString("invalid_band", $result['invalidRows'][0]);
        $this->assertStringContainsString("wrong_type", $result['invalidRows'][1]);
        $this->assertStringContainsString("bad_value", $result['invalidRows'][2]);
    }
}