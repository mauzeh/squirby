<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ProgramController;
use App\Services\TrainingProgressionService;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ProgramControllerEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $exercise;
    protected $bodyweightExercise;
    protected $trainingProgressionService;
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);
        $this->bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);
        
        // Mock the TrainingProgressionService
        $this->trainingProgressionService = Mockery::mock(TrainingProgressionService::class);
        
        // Mock the ExerciseService
        $this->exerciseService = Mockery::mock(\App\Services\ExerciseService::class);
        
        // Create controller with mocked services
        $this->controller = new ProgramController($this->trainingProgressionService, $this->exerciseService);
        
        // Authenticate the user
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function calculateSetsAndReps_handles_training_progression_service_returning_null()
    {
        // Arrange
        $date = Carbon::today();
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert
        $this->assertEquals(3, $result['sets']); // Default from config
        $this->assertEquals(10, $result['reps']); // Default from config
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_training_progression_service_throwing_exception()
    {
        // Arrange
        $date = Carbon::today();
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should fall back to defaults when exception occurs
        $this->assertEquals(3, $result['sets']); // Default from config
        $this->assertEquals(10, $result['reps']); // Default from config
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_training_progression_service_throwing_runtime_exception()
    {
        // Arrange
        $date = Carbon::today();
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andThrow(new \RuntimeException('Service temporarily unavailable'));

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should fall back to defaults when runtime exception occurs
        $this->assertEquals(3, $result['sets']);
        $this->assertEquals(10, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_missing_config_defaults_gracefully()
    {
        // Arrange
        $date = Carbon::today();
        
        // Temporarily clear the config values to simulate missing configuration
        $originalConfig = config('training.defaults');
        config(['training.defaults' => null]);
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should use hardcoded fallbacks when config is missing
        $this->assertEquals(3, $result['sets']); // Hardcoded fallback
        $this->assertEquals(10, $result['reps']); // Hardcoded fallback
        $this->assertFalse($result['suggestion_available']);
        
        // Restore original config
        config(['training.defaults' => $originalConfig]);
    }

    /** @test */
    public function calculateSetsAndReps_handles_partially_missing_config_defaults()
    {
        // Arrange
        $date = Carbon::today();
        
        // Set only one config value to simulate partial configuration
        config(['training.defaults.sets' => 5]);
        config(['training.defaults.reps' => null]);
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should use config value where available, fallback where not
        $this->assertEquals(5, $result['sets']); // From config
        $this->assertEquals(10, $result['reps']); // Hardcoded fallback
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_empty_config_defaults()
    {
        // Arrange
        $date = Carbon::today();
        
        // Set empty config values
        config(['training.defaults.sets' => '']);
        config(['training.defaults.reps' => '']);
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should use hardcoded fallbacks when config values are empty
        $this->assertEquals(3, $result['sets']); // Hardcoded fallback
        $this->assertEquals(10, $result['reps']); // Hardcoded fallback
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_zero_config_defaults()
    {
        // Arrange
        $date = Carbon::today();
        
        // Set zero config values (which should be treated as invalid)
        config(['training.defaults.sets' => 0]);
        config(['training.defaults.reps' => 0]);
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should use hardcoded fallbacks when config values are zero
        $this->assertEquals(3, $result['sets']); // Hardcoded fallback
        $this->assertEquals(10, $result['reps']); // Hardcoded fallback
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_works_with_bodyweight_exercises_when_progression_data_exists()
    {
        // Arrange
        $date = Carbon::today();
        $suggestionData = (object) [
            'sets' => 4,
            'reps' => 15,
            'suggestedWeight' => null, // Bodyweight exercises don't have weight
            'lastWeight' => null
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->bodyweightExercise->id, $date)
            ->once()
            ->andReturn($suggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->bodyweightExercise->id, 
            $date
        ]);

        // Assert
        $this->assertEquals(4, $result['sets']);
        $this->assertEquals(15, $result['reps']);
        $this->assertTrue($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_works_with_bodyweight_exercises_when_no_progression_data()
    {
        // Arrange
        $date = Carbon::today();
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->bodyweightExercise->id, $date)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->bodyweightExercise->id, 
            $date
        ]);

        // Assert - Should use defaults for bodyweight exercises too
        $this->assertEquals(3, $result['sets']); // Default from config
        $this->assertEquals(10, $result['reps']); // Default from config
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_malformed_suggestion_data()
    {
        // Arrange
        $date = Carbon::today();
        $malformedSuggestionData = (object) [
            'sets' => null, // Missing sets
            'reps' => 'invalid', // Invalid reps type
            'suggestedWeight' => 100,
            'lastWeight' => 95
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($malformedSuggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should fall back to defaults when suggestion data is malformed
        $this->assertEquals(3, $result['sets']); // Default fallback
        $this->assertEquals(10, $result['reps']); // Default fallback
        $this->assertFalse($result['suggestion_available']); // Should be false due to malformed data
    }

    /** @test */
    public function calculateSetsAndReps_handles_suggestion_with_zero_or_negative_values()
    {
        // Arrange
        $date = Carbon::today();
        $invalidSuggestionData = (object) [
            'sets' => 0, // Invalid: zero sets
            'reps' => -5, // Invalid: negative reps
            'suggestedWeight' => 100,
            'lastWeight' => 95
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($invalidSuggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should fall back to defaults when suggestion values are invalid
        $this->assertEquals(3, $result['sets']); // Default fallback
        $this->assertEquals(10, $result['reps']); // Default fallback
        $this->assertFalse($result['suggestion_available']); // Should be false due to invalid data
    }

    /** @test */
    public function calculateSetsAndReps_handles_extremely_high_suggestion_values()
    {
        // Arrange
        $date = Carbon::today();
        $extremeSuggestionData = (object) [
            'sets' => 999, // Extremely high sets
            'reps' => 1000, // Extremely high reps
            'suggestedWeight' => 100,
            'lastWeight' => 95
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($extremeSuggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should accept extreme values if they're technically valid
        // (The business logic for reasonable limits should be in TrainingProgressionService)
        $this->assertEquals(999, $result['sets']);
        $this->assertEquals(1000, $result['reps']);
        $this->assertTrue($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_invalid_exercise_id()
    {
        // Arrange
        $date = Carbon::today();
        $invalidExerciseId = 99999; // Non-existent exercise ID
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $invalidExerciseId, $date)
            ->once()
            ->andReturn(null); // Service returns null for invalid exercise

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $invalidExerciseId, 
            $date
        ]);

        // Assert - Should handle gracefully and return defaults
        $this->assertEquals(3, $result['sets']);
        $this->assertEquals(10, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_invalid_date()
    {
        // Arrange
        $invalidDate = Carbon::createFromFormat('Y-m-d', '1900-01-01'); // Very old date
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $invalidDate)
            ->once()
            ->andReturn(null); // Service returns null for invalid date

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $invalidDate
        ]);

        // Assert - Should handle gracefully and return defaults
        $this->assertEquals(3, $result['sets']);
        $this->assertEquals(10, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_future_date()
    {
        // Arrange
        $futureDate = Carbon::today()->addYears(10); // Far future date
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $futureDate)
            ->once()
            ->andReturn(null); // Service likely returns null for future dates

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $futureDate
        ]);

        // Assert - Should handle gracefully and return defaults
        $this->assertEquals(3, $result['sets']);
        $this->assertEquals(10, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_handles_suggestion_with_missing_properties()
    {
        // Arrange
        $date = Carbon::today();
        $incompleteSuggestionData = (object) [
            'suggestedWeight' => 100,
            'lastWeight' => 95
            // Missing sets and reps properties
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($incompleteSuggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert - Should fall back to defaults when required properties are missing
        $this->assertEquals(3, $result['sets']); // Default fallback
        $this->assertEquals(10, $result['reps']); // Default fallback
        $this->assertFalse($result['suggestion_available']); // Should be false due to missing properties
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}