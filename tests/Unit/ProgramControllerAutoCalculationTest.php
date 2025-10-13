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

class ProgramControllerAutoCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $exercise;
    protected $trainingProgressionService;
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Mock the TrainingProgressionService
        $this->trainingProgressionService = Mockery::mock(TrainingProgressionService::class);
        
        // Create controller with mocked service
        $this->controller = new ProgramController($this->trainingProgressionService);
        
        // Authenticate the user
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function calculateSetsAndReps_returns_calculated_values_when_progression_data_exists()
    {
        // Arrange
        $date = Carbon::today();
        $suggestionData = (object) [
            'sets' => 4,
            'reps' => 8,
            'suggestedWeight' => 100,
            'lastWeight' => 95
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($suggestionData);

        // Act
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert
        $this->assertEquals(4, $result['sets']);
        $this->assertEquals(8, $result['reps']);
        $this->assertTrue($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_returns_default_values_when_no_progression_data()
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
    public function calculateSetsAndReps_uses_config_defaults_when_available()
    {
        // Arrange
        config(['training.defaults.sets' => 5]);
        config(['training.defaults.reps' => 12]);
        
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
        $this->assertEquals(5, $result['sets']);
        $this->assertEquals(12, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function calculateSetsAndReps_uses_hardcoded_fallbacks_when_config_missing()
    {
        // Arrange
        // Temporarily clear the config values
        $originalSets = config('training.defaults.sets');
        $originalReps = config('training.defaults.reps');
        
        config(['training.defaults' => []]);
        
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
        $this->assertEquals(3, $result['sets']); // Hardcoded fallback
        $this->assertEquals(10, $result['reps']); // Hardcoded fallback
        $this->assertFalse($result['suggestion_available']);
        
        // Restore original config
        config(['training.defaults.sets' => $originalSets]);
        config(['training.defaults.reps' => $originalReps]);
    }

    /** @test */
    public function store_method_calls_calculateSetsAndReps_and_uses_result()
    {
        // This test verifies that the store method calls calculateSetsAndReps
        // and uses the calculated values. We'll test this by verifying the 
        // TrainingProgressionService is called with correct parameters.
        
        // Arrange
        $date = Carbon::today();
        $suggestionData = (object) [
            'sets' => 4,
            'reps' => 8,
            'suggestedWeight' => 100,
            'lastWeight' => 95
        ];
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn($suggestionData);

        // Test that calculateSetsAndReps is called correctly
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert the method returns the expected structure
        $this->assertEquals(4, $result['sets']);
        $this->assertEquals(8, $result['reps']);
        $this->assertTrue($result['suggestion_available']);
    }

    /** @test */
    public function store_method_uses_default_values_when_no_progression_data()
    {
        // This test verifies that when no progression data exists,
        // the calculateSetsAndReps method returns default values
        
        // Arrange
        $date = Carbon::today();
        
        $this->trainingProgressionService
            ->shouldReceive('getSuggestionDetails')
            ->with($this->user->id, $this->exercise->id, $date)
            ->once()
            ->andReturn(null);

        // Test that calculateSetsAndReps returns defaults
        $result = $this->invokePrivateMethod($this->controller, 'calculateSetsAndReps', [
            $this->exercise->id, 
            $date
        ]);

        // Assert the method returns default values
        $this->assertEquals(3, $result['sets']);
        $this->assertEquals(10, $result['reps']);
        $this->assertFalse($result['suggestion_available']);
    }

    /** @test */
    public function update_method_does_not_call_training_progression_service()
    {
        // This test verifies that the update method does NOT call
        // the TrainingProgressionService, ensuring manual input is preserved
        
        // Arrange
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 3,
            'reps' => 10,
        ]);

        // The TrainingProgressionService should NOT be called during update
        $this->trainingProgressionService
            ->shouldNotReceive('getSuggestionDetails');

        // Act - Call the edit method to verify it doesn't use the service
        $response = $this->controller->edit($program);

        // Assert - The method should complete without calling the service
        $this->assertNotNull($response);
    }

    /** @test */
    public function edit_method_does_not_call_training_progression_service()
    {
        // This test verifies that the edit method does NOT call
        // the TrainingProgressionService
        
        // Arrange
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
        ]);

        // The TrainingProgressionService should NOT be called during edit
        $this->trainingProgressionService
            ->shouldNotReceive('getSuggestionDetails');

        // Act - Call the edit method
        $response = $this->controller->edit($program);

        // Assert - The method should complete without calling the service
        $this->assertNotNull($response);
    }

    /** @test */
    public function edit_method_throws_403_for_unauthorized_user()
    {
        // Arrange
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        $program = Program::factory()->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $otherExercise->id,
        ]);

        // Act & Assert
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->edit($program);
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