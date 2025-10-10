<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService(new \App\Services\IngredientTsvProcessorService());
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_imports_exercises_from_tsv_data()
    {
        $initialCount = Exercise::where('user_id', $this->user->id)->count();
        
        $tsvData = "Burpees\tFull body bodyweight exercise\ttrue\nDumbbell Rows\tBack exercise with dumbbells\tfalse";

        $result = $this->tsvImporterService->importExercises($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['invalidRows']);

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
        $this->assertEmpty($result['invalidRows']);

        $existingExercise->refresh();
        $this->assertEquals('Updated bodyweight exercise', $existingExercise->description);
        $this->assertTrue($existingExercise->is_bodyweight);
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
}