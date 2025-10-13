<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Services\TrainingProgressionService;
use Carbon\Carbon;
use Mockery;

class ProgramEdgeCasesFeatureTest extends TestCase
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
    public function program_creation_handles_training_progression_service_exception_gracefully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to throw an exception
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \RuntimeException('Database connection failed'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with service exception',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was still created with default values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Test with service exception',
        ]);
    }

    /** @test */
    public function program_creation_handles_malformed_progression_service_response()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return malformed data
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $malformedData = (object) [
            'sets' => null, // Invalid sets
            'reps' => 'invalid', // Invalid reps
            'suggestedWeight' => 100,
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($malformedData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with malformed data',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default values due to malformed data
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Test with malformed data',
        ]);
    }

    /** @test */
    public function program_creation_handles_zero_or_negative_progression_values()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return invalid values
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $invalidData = (object) [
            'sets' => 0, // Invalid: zero sets
            'reps' => -5, // Invalid: negative reps
            'suggestedWeight' => 100,
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($invalidData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with invalid values',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default values due to invalid data
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Test with invalid values',
        ]);
    }

    /** @test */
    public function program_creation_works_with_missing_config_defaults()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Temporarily clear the config to simulate missing configuration
        $originalConfig = config('training.defaults');
        config(['training.defaults' => null]);

        // Mock the TrainingProgressionService to return null
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn(null);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with missing config',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with hardcoded fallback values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Hardcoded fallback
            'reps' => 10, // Hardcoded fallback
            'comments' => 'Test with missing config',
        ]);

        // Restore original config
        config(['training.defaults' => $originalConfig]);
    }

    /** @test */
    public function program_creation_works_with_bodyweight_exercises_and_service_failure()
    {
        $user = User::factory()->create();
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'is_bodyweight' => true
        ]);

        // Mock the TrainingProgressionService to throw an exception
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $bodyweightExercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Bodyweight exercise with service failure',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $bodyweightExercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Bodyweight exercise with service failure',
        ]);
    }

    /** @test */
    public function program_creation_works_with_bodyweight_exercises_and_valid_progression_data()
    {
        $user = User::factory()->create();
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'is_bodyweight' => true
        ]);

        // Mock the TrainingProgressionService to return valid bodyweight progression data
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $bodyweightData = (object) [
            'sets' => 4,
            'reps' => 15, // Higher reps typical for bodyweight exercises
            'suggestedWeight' => null, // No weight for bodyweight exercises
            'lastWeight' => null
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($bodyweightData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $bodyweightExercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Bodyweight exercise with progression',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with calculated values
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $bodyweightExercise->id,
            'sets' => 4, // From progression data
            'reps' => 15, // From progression data
            'comments' => 'Bodyweight exercise with progression',
        ]);
    }

    /** @test */
    public function quick_add_handles_service_failure_gracefully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to throw an exception
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \RuntimeException('Service temporarily unavailable'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $date = Carbon::today()->toDateString();

        $response = $this->actingAs($user)
            ->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date, 'redirect_to' => 'mobile-entry']));

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Verify program was created with default values despite service failure
        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(3, $program->sets); // Default fallback
        $this->assertEquals(10, $program->reps); // Default fallback
    }

    /** @test */
    public function quick_add_handles_malformed_service_response()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return malformed data
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $malformedData = (object) [
            'sets' => 'invalid',
            'reps' => null,
            'suggestedWeight' => 100,
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($malformedData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $date = Carbon::today()->toDateString();

        $response = $this->actingAs($user)
            ->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date, 'redirect_to' => 'mobile-entry']));

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $date]));

        // Verify program was created with default values due to malformed data
        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(3, $program->sets); // Default fallback
        $this->assertEquals(10, $program->reps); // Default fallback
    }

    /** @test */
    public function quick_create_works_with_missing_config_and_service_failure()
    {
        $user = User::factory()->create();

        // Clear config and mock service failure
        config(['training.defaults' => null]);
        
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \Exception('Service down'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $date = Carbon::today()->toDateString();
        $exerciseName = 'Emergency Exercise';

        $response = $this->actingAs($user)
            ->post(route('programs.quick-create', ['date' => $date]), [
                'exercise_name' => $exerciseName,
                'redirect_to' => 'mobile-entry',
            ]);

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $date]));

        // Verify exercise was created
        $exercise = Exercise::where('title', $exerciseName)->first();
        $this->assertNotNull($exercise);

        // Verify program was created with hardcoded fallback values
        $program = Program::where('exercise_id', $exercise->id)->first();
        $this->assertNotNull($program);
        $this->assertEquals(3, $program->sets); // Hardcoded fallback
        $this->assertEquals(10, $program->reps); // Hardcoded fallback
    }

    /** @test */
    public function program_creation_handles_extremely_high_progression_values()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return extreme but valid values
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $extremeData = (object) [
            'sets' => 50, // Very high but technically valid
            'reps' => 100, // Very high but technically valid
            'suggestedWeight' => 500,
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($extremeData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with extreme values',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that extreme but valid values are accepted
        // (Business logic for reasonable limits should be in TrainingProgressionService)
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 50, // Should accept extreme but valid values
            'reps' => 100, // Should accept extreme but valid values
            'comments' => 'Test with extreme values',
        ]);
    }

    /** @test */
    public function program_creation_handles_incomplete_suggestion_data()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock the TrainingProgressionService to return incomplete data
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $incompleteData = (object) [
            'suggestedWeight' => 100,
            'lastWeight' => 95,
            // Missing sets and reps
        ];
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturn($incompleteData);

        $this->app->instance(TrainingProgressionService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Test with incomplete data',
                'priority' => 5,
            ]);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify that the program was created with default values due to incomplete data
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Should fall back to defaults
            'reps' => 10, // Should fall back to defaults
            'comments' => 'Test with incomplete data',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}