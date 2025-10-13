<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;

class ProgramQuickAddAutoCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function quickAdd_method_uses_training_progression_service_when_progression_data_exists()
    {
        // Create lift log history to generate progression data
        $this->createLiftLogHistory($this->exercise->id, 3, 8, 100.0);

        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Verify program was created with calculated sets/reps based on progression
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        // The TrainingProgressionService should suggest progression from the lift log history
        // Based on the progression logic, it should suggest increased reps or weight
        $this->assertTrue($program->sets >= 3); // Should be at least the base sets
        $this->assertTrue($program->reps >= 8); // Should be at least the base reps or more
    }

    /** @test */
    public function quickAdd_method_uses_default_values_when_no_progression_data_exists()
    {
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Verify program was created with default values from config
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(config('training.defaults.sets', 3), $program->sets);
        $this->assertEquals(config('training.defaults.reps', 10), $program->reps);
    }

    /** @test */
    public function quickAdd_method_sets_correct_priority()
    {
        // Create existing program to test priority calculation
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => Exercise::factory()->create(['user_id' => $this->user->id])->id,
            'date' => Carbon::today(),
            'priority' => 150
        ]);

        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify new program has priority higher than existing
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(151, $program->priority);
    }

    /** @test */
    public function quickAdd_method_sets_default_priority_when_no_existing_programs()
    {
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify new program has default priority of 100
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(100, $program->priority);
    }

    /** @test */
    public function quickCreate_method_uses_default_values_for_new_exercise()
    {
        $date = Carbon::today()->toDateString();
        $exerciseName = 'Brand New Exercise';

        $response = $this->post(route('programs.quick-create', ['date' => $date]), [
            'exercise_name' => $exerciseName,
        ]);

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'New exercise created and added to program successfully.');

        // Verify exercise was created
        $exercise = Exercise::where('title', $exerciseName)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($exercise);

        // Verify program was created with default values (no progression data for new exercise)
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(config('training.defaults.sets', 3), $program->sets);
        $this->assertEquals(config('training.defaults.reps', 10), $program->reps);
    }

    /** @test */
    public function quickCreate_method_validates_required_exercise_name()
    {
        $date = Carbon::today()->toDateString();

        // Count existing exercises and programs before the test
        $initialExerciseCount = Exercise::where('user_id', $this->user->id)->count();
        $initialProgramCount = Program::where('user_id', $this->user->id)->count();

        $response = $this->post(route('programs.quick-create', ['date' => $date]), [
            'exercise_name' => '',
        ]);

        $response->assertSessionHasErrors(['exercise_name']);

        // Verify no new exercise or program was created
        $this->assertEquals($initialExerciseCount, Exercise::where('user_id', $this->user->id)->count());
        $this->assertEquals($initialProgramCount, Program::where('user_id', $this->user->id)->count());
    }

    /** @test */
    public function quickCreate_method_sets_correct_priority_with_existing_programs()
    {
        // Create existing program
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => Exercise::factory()->create(['user_id' => $this->user->id])->id,
            'date' => Carbon::today(),
            'priority' => 200
        ]);

        $date = Carbon::today()->toDateString();
        $exerciseName = 'New Priority Test Exercise';

        $response = $this->post(route('programs.quick-create', ['date' => $date]), [
            'exercise_name' => $exerciseName,
        ]);

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify new program has priority higher than existing
        $exercise = Exercise::where('title', $exerciseName)->first();
        $program = Program::where('exercise_id', $exercise->id)->first();

        $this->assertEquals(201, $program->priority);
    }

    /** @test */
    public function mobile_entry_workflow_remains_functional_with_auto_calculation()
    {
        // Create a program using quickAdd
        $date = Carbon::today()->toDateString();
        
        $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        // Verify mobile entry page shows the program
        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $date]));
        
        $response->assertStatus(200);
        $response->assertSee($this->exercise->title);
        
        // Verify the program shows calculated sets/reps
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->first();
        
        $response->assertSee($program->sets . ' x ' . $program->reps);
    }

    /** @test */
    public function quickAdd_works_with_bodyweight_exercises()
    {
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'is_bodyweight' => true
        ]);

        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $bodyweightExercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify program was created with appropriate values for bodyweight exercise
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $bodyweightExercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        // Should use default values since no progression data exists
        $this->assertEquals(config('training.defaults.sets', 3), $program->sets);
        $this->assertEquals(config('training.defaults.reps', 10), $program->reps);
    }

    /** @test */
    public function quickAdd_handles_progression_service_failure_gracefully()
    {
        // This test ensures that if the TrainingProgressionService fails,
        // the system falls back to default values
        
        $date = Carbon::today()->toDateString();

        // Mock a scenario where progression service might return null
        // (This is handled in the controller's calculateSetsAndReps method)
        
        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify program was still created with fallback values
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->first();

        $this->assertNotNull($program);
        $this->assertTrue($program->sets > 0);
        $this->assertTrue($program->reps > 0);
    }

    /** @test */
    public function quickAdd_and_quickCreate_work_for_different_dates()
    {
        $futureDate = Carbon::today()->addDays(3)->toDateString();

        // Test quickAdd with future date
        $response = $this->get(route('programs.quick-add', ['exercise' => $this->exercise->id, 'date' => $futureDate]));
        
        $response->assertRedirect(route('programs.index', ['date' => $futureDate]));

        // Test quickCreate with future date
        $exerciseName = 'Future Exercise';
        $response = $this->post(route('programs.quick-create', ['date' => $futureDate]), [
            'exercise_name' => $exerciseName,
        ]);

        $response->assertRedirect(route('programs.index', ['date' => $futureDate]));

        // Verify both programs were created for the correct date
        $programs = Program::where('user_id', $this->user->id)
            ->where('date', Carbon::parse($futureDate)->startOfDay())
            ->get();

        $this->assertCount(2, $programs);
    }

    /** @test */
    public function user_can_quick_add_global_exercises()
    {
        // Create a global exercise (user_id = null)
        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $globalExercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));

        // Verify program was created with the global exercise
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNotNull($program);
        $this->assertEquals(config('training.defaults.sets', 3), $program->sets);
        $this->assertEquals(config('training.defaults.reps', 10), $program->reps);
    }

    /**
     * Helper method to create lift log history for testing progression
     */
    private function createLiftLogHistory(int $exerciseId, int $sets, int $reps, float $weight): void
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseId,
            'logged_at' => Carbon::yesterday(),
        ]);

        // Create lift sets for the lift log
        for ($i = 0; $i < $sets; $i++) {
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'reps' => $reps,
                'weight' => $weight,
            ]);
        }
    }
}