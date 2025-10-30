<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\TrainingProgressionService;
use Carbon\Carbon;
use Mockery;

class ProgramCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up default training configuration
        config(['training.defaults.sets' => 3]);
        config(['training.defaults.reps' => 10]);
    }

    /** @test */
    public function program_creation_form_renders_without_sets_reps_input_fields()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('programs.create'));

        $response->assertStatus(200);
        
        // Assert that sets and reps input fields are NOT present
        $response->assertDontSee('name="sets"', false);
        $response->assertDontSee('name="reps"', false);
        $response->assertDontSee('id="sets"', false);
        $response->assertDontSee('id="reps"', false);
        
        // Assert that other form fields are present
        $response->assertSee('name="exercise_id"', false);
        $response->assertSee('name="date"', false);
        $response->assertSee('name="priority"', false);
        $response->assertSee('name="comments"', false);
        
        // Assert that informational text about auto-calculation is present
        $response->assertSee('Sets and reps will be automatically calculated');
        $response->assertSee('based on your training progression history');
    }

    /** @test */
    public function program_creation_saves_with_auto_calculated_values_when_progression_data_exists()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Create lift log history to provide progression data
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(1),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 100,
        ]);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test program with progression data',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with calculated sets and reps
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test program with progression data',
            'priority' => 5,
        ]);

        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->first();

        // The exact values depend on the progression model, but they should not be null
        $this->assertNotNull($program->sets);
        $this->assertNotNull($program->reps);
        $this->assertGreaterThan(0, $program->sets);
        $this->assertGreaterThan(0, $program->reps);
    }

    /** @test */
    public function program_creation_saves_with_default_values_when_no_progression_data_exists()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test program without progression data',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default sets and reps
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Default from config
            'reps' => 10, // Default from config
            'comments' => 'Test program without progression data',
            'priority' => 5,
        ]);
    }

    /** @test */
    public function program_editing_form_continues_to_show_manual_sets_reps_input_fields()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 4,
            'reps' => 8,
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.edit', $program));

        $response->assertStatus(200);
        
        // Assert that sets and reps input fields ARE present in edit form
        $response->assertSee('name="sets"', false);
        $response->assertSee('name="reps"', false);
        $response->assertSee('id="sets"', false);
        $response->assertSee('id="reps"', false);
        
        // Assert that current values are displayed
        $response->assertSee('value="4"', false); // sets value
        $response->assertSee('value="8"', false); // reps value
        
        // Assert that other form fields are present
        $response->assertSee('name="exercise_id"', false);
        $response->assertSee('name="date"', false);
        $response->assertSee('name="priority"', false);
        $response->assertSee('name="comments"', false);
        
        // Assert that auto-calculation info is NOT present in edit form
        $response->assertDontSee('Sets and reps will be automatically calculated');
    }

    /** @test */
    public function program_editing_continues_to_work_with_manual_input_validation()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
        ]);

        // Test successful update with valid manual input
        $response = $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 5,
                'reps' => 12,
                'comments' => 'Updated manually',
                'priority' => 3,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 5,
            'reps' => 12,
            'comments' => 'Updated manually',
            'priority' => 3,
        ]);
    }

    /** @test */
    public function program_editing_validates_manual_sets_reps_input()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        // Test validation errors for missing sets and reps
        $response = $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                // Missing sets and reps
            ]);

        $response->assertSessionHasErrors(['sets', 'reps']);

        // Test validation errors for invalid sets and reps
        $response = $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 0, // Invalid: must be at least 1
                'reps' => -1, // Invalid: must be at least 1
            ]);

        $response->assertSessionHasErrors(['sets', 'reps']);
    }

    /** @test */
    public function program_creation_handles_training_progression_service_failure_gracefully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to throw an exception
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with service failure',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was still created with default values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Test with service failure',
        ]);
    }

    /** @test */
    public function program_creation_handles_null_progression_service_response()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return null
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn(null);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with null response',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should use defaults when service returns null
            'reps' => 10, // Should use defaults when service returns null
            'comments' => 'Test with null response',
        ]);
    }

    /** @test */
    public function program_creation_with_new_exercise_uses_auto_calculated_values()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'new_exercise_name' => 'Brand New Exercise',
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with new exercise',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'Brand New Exercise',
            'user_id' => $user->id,
        ]);

        $exercise = Exercise::where('title', 'Brand New Exercise')->first();

        // Verify that the program was created with default values (no progression data for new exercise)
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should use defaults for new exercise
            'reps' => 10, // Should use defaults for new exercise
            'comments' => 'Test with new exercise',
        ]);
    }

    /** @test */
    public function program_creation_validation_does_not_require_sets_and_reps()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Test that sets and reps are not required in creation
        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                // No sets or reps provided - should not cause validation errors
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));
        $response->assertSessionDoesntHaveErrors(['sets', 'reps']);

        // Verify program was created successfully
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function program_creation_still_validates_required_fields()
    {
        $user = User::factory()->create();

        // Test that other required fields are still validated
        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                // Missing exercise_id/new_exercise_name and date
            ]);

        $response->assertSessionHasErrors(['exercise_id', 'date']);
    }

    /** @test */
    public function existing_programs_display_correctly_without_modification()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Create an existing program with specific sets/reps
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 4,
            'reps' => 6,
            'date' => Carbon::today(),
            'comments' => 'Existing program',
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200);
        
        // Verify that existing program data is displayed correctly
        $response->assertSee($exercise->title);
        $response->assertSee('4'); // sets
        $response->assertSee('6'); // reps
        $response->assertSee('Existing program');
        
        // Verify the program data hasn't been modified in the database
        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 4,
            'reps' => 6,
            'comments' => 'Existing program',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}