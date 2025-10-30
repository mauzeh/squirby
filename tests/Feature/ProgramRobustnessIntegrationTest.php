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

class ProgramRobustnessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function system_remains_robust_under_multiple_failure_conditions()
    {
        $user = User::factory()->create();
        $regularExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $bodyweightExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        // Simulate a completely broken environment
        config(['training.defaults' => null]); // Missing config
        
        // Mock service that fails in various ways
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andReturnUsing(function($userId, $exerciseId, $date = null) use ($regularExercise, $bodyweightExercise) {
                if ($exerciseId === $regularExercise->id) {
                    // First call: throw exception
                    throw new \RuntimeException('Service completely down');
                } elseif ($exerciseId === $bodyweightExercise->id) {
                    // Second call: return malformed data
                    return (object) ['sets' => null, 'reps' => 'invalid'];
                } else {
                    // Other calls: return null
                    return null;
                }
            });

        $this->app->instance(TrainingProgressionService::class, $mockService);

        // Test 1: Program creation with service exception
        $response1 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $regularExercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Service exception test',
                'priority' => 5,
            ]);

        $response1->assertRedirect();
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $regularExercise->id,
            'sets' => 3, // Hardcoded fallback
            'reps' => 10, // Hardcoded fallback
        ]);

        // Test 2: Program creation with malformed data
        $response2 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $bodyweightExercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Malformed data test',
                'priority' => 6,
            ]);

        $response2->assertRedirect();
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $bodyweightExercise->id,
            'sets' => 3, // Hardcoded fallback
            'reps' => 10, // Hardcoded fallback
        ]);

        // Test 3: Quick add with service failure
        $date = Carbon::tomorrow()->toDateString();
        $response3 = $this->actingAs($user)
            ->get(route('programs.quick-add', ['exercise' => $regularExercise->id, 'date' => $date]));

        $response3->assertRedirect(route('programs.index', ['date' => $date]));
        
        $quickAddProgram = Program::where('user_id', $user->id)
            ->where('exercise_id', $regularExercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($quickAddProgram);
        $this->assertEquals(3, $quickAddProgram->sets);
        $this->assertEquals(10, $quickAddProgram->reps);

        // Test 4: Quick create with all failures
        $response4 = $this->actingAs($user)
            ->post(route('programs.quick-create', ['date' => $date]), [
                'exercise_name' => 'Emergency Exercise',
            ]);

        $response4->assertRedirect(route('programs.index', ['date' => $date]));
        
        $newExercise = Exercise::where('title', 'Emergency Exercise')->first();
        $this->assertNotNull($newExercise);
        
        $quickCreateProgram = Program::where('exercise_id', $newExercise->id)->first();
        $this->assertNotNull($quickCreateProgram);
        $this->assertEquals(3, $quickCreateProgram->sets);
        $this->assertEquals(10, $quickCreateProgram->reps);

        // Verify all programs were created successfully despite multiple failures
        $totalPrograms = Program::where('user_id', $user->id)->count();
        $this->assertEquals(4, $totalPrograms);

        // Verify no programs have invalid sets/reps
        $invalidPrograms = Program::where('user_id', $user->id)
            ->where(function($query) {
                $query->where('sets', '<=', 0)
                      ->orWhere('reps', '<=', 0)
                      ->orWhereNull('sets')
                      ->orWhereNull('reps');
            })
            ->count();
        
        $this->assertEquals(0, $invalidPrograms, 'No programs should have invalid sets/reps values');
    }

    /** @test */
    public function system_handles_cascading_failures_gracefully()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Simulate cascading failures: config corruption + service failure
        config(['training' => ['defaults' => ['sets' => '', 'reps' => null]]]); // Corrupted config
        
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->andThrow(new \Exception('Complete system failure'));

        $this->app->instance(TrainingProgressionService::class, $mockService);

        // System should still work with hardcoded fallbacks
        $response = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'Cascading failure test',
                'priority' => 5,
            ]);

        $response->assertRedirect();
        
        // Verify program was created with hardcoded fallbacks
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3, // Hardcoded fallback
            'reps' => 10, // Hardcoded fallback
            'comments' => 'Cascading failure test',
        ]);
    }

    /** @test */
    public function system_recovers_from_temporary_service_issues()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Simulate service that fails first, then recovers
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->twice()
            ->andReturnUsing(function() {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    throw new \RuntimeException('Temporary failure');
                } else {
                    return (object) ['sets' => 4, 'reps' => 8, 'suggestedWeight' => 100];
                }
            });

        $this->app->instance(TrainingProgressionService::class, $mockService);

        // Create program during failure
        $response1 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'comments' => 'During failure',
                'priority' => 5,
            ]);

        $response1->assertRedirect();
        
        // Verify fallback was used
        $program1 = Program::where('comments', 'During failure')->first();
        $this->assertEquals(3, $program1->sets);
        $this->assertEquals(10, $program1->reps);

        // Create another program after recovery
        $response2 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'comments' => 'After recovery',
                'priority' => 5,
            ]);

        $response2->assertRedirect();
        
        // Verify calculated values are used
        $program2 = Program::where('comments', 'After recovery')->first();
        $this->assertEquals(4, $program2->sets);
        $this->assertEquals(8, $program2->reps);
    }

    /** @test */
    public function system_handles_mixed_valid_and_invalid_data_scenarios()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise3 = Exercise::factory()->create(['user_id' => $user->id]);

        // Mock service with mixed responses
        $mockService = Mockery::mock(TrainingProgressionService::class);
        $mockService->shouldReceive('getSuggestionDetails')
            ->times(3)
            ->andReturnUsing(function($userId, $exerciseId) use ($exercise1, $exercise2, $exercise3) {
                if ($exerciseId === $exercise1->id) {
                    return (object) ['sets' => 4, 'reps' => 8, 'suggestedWeight' => 100]; // Valid
                } elseif ($exerciseId === $exercise2->id) {
                    return (object) ['sets' => 0, 'reps' => -5, 'suggestedWeight' => 100]; // Invalid
                } else {
                    return null; // No data
                }
            });

        $this->app->instance(TrainingProgressionService::class, $mockService);

        // Test with valid data
        $response1 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise1->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'priority' => 1,
            ]);

        // Test with invalid data
        $response2 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise2->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'priority' => 2,
            ]);

        // Test with no data
        $response3 = $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise3->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'priority' => 3,
            ]);

        // All should succeed
        $response1->assertRedirect();
        $response2->assertRedirect();
        $response3->assertRedirect();

        // Verify correct handling
        $program1 = Program::where('exercise_id', $exercise1->id)->first();
        $this->assertEquals(4, $program1->sets); // Valid data used
        $this->assertEquals(8, $program1->reps);

        $program2 = Program::where('exercise_id', $exercise2->id)->first();
        $this->assertEquals(3, $program2->sets); // Fallback used for invalid data
        $this->assertEquals(10, $program2->reps);

        $program3 = Program::where('exercise_id', $exercise3->id)->first();
        $this->assertEquals(3, $program3->sets); // Default used for no data
        $this->assertEquals(10, $program3->reps);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}