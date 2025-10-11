<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTsvImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_import_exercises_via_web_interface()
    {
        $tsvData = "Burpees\tFull body bodyweight exercise\ttrue\nDumbbell Rows\tBack exercise with dumbbells\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Burpees',
            'description' => 'Full body bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Dumbbell Rows',
            'description' => 'Back exercise with dumbbells',
            'is_bodyweight' => false,
        ]);
    }

    public function test_user_can_view_exercises_index_with_tsv_export()
    {
        // Create some exercises
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'is_bodyweight' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Export');
        $response->assertSee('TSV Import');
        $response->assertSee('Test Exercise');
    }

    public function test_import_with_empty_data_shows_error()
    {
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => ''
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('error', 'TSV data cannot be empty.');
    }

    public function test_import_with_no_new_data_shows_success_message()
    {
        // Create existing exercise that matches what we'll try to import
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Push Ups',
            'description' => 'Bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        // Try to import the exact same data
        $tsvData = "Push Ups\tBodyweight exercise\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
    }

    public function test_import_with_invalid_data_shows_error()
    {
        $tsvData = "Invalid\nAnother Invalid Row";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('error');
    }

    public function test_user_can_import_exercises_with_two_columns_only()
    {
        $tsvData = "Running\tCardio exercise\nSwimming\tFull body cardio";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Running',
            'description' => 'Cardio exercise',
            'is_bodyweight' => false, // Should default to false
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Swimming',
            'description' => 'Full body cardio',
            'is_bodyweight' => false, // Should default to false
        ]);
    }
}