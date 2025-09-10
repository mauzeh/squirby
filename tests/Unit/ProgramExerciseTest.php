<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutProgram;
use App\Models\ProgramExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProgramExerciseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WorkoutProgram $program;
    private Exercise $exercise1;
    private Exercise $exercise2;
    private Exercise $exercise3;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->program = WorkoutProgram::factory()->create(['user_id' => $this->user->id]);
        $this->exercise1 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $this->exercise2 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $this->exercise3 = Exercise::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_program_exercise_can_be_created_with_valid_data()
    {
        $programExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Heavy weight',
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $this->assertDatabaseHas('program_exercises', [
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Heavy weight',
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);
    }

    public function test_validation_rules_are_correct()
    {
        $rules = ProgramExercise::validationRules();

        $this->assertArrayHasKey('workout_program_id', $rules);
        $this->assertArrayHasKey('exercise_id', $rules);
        $this->assertArrayHasKey('sets', $rules);
        $this->assertArrayHasKey('reps', $rules);
        $this->assertArrayHasKey('notes', $rules);
        $this->assertArrayHasKey('exercise_order', $rules);
        $this->assertArrayHasKey('exercise_type', $rules);

        $this->assertStringContainsString('required', $rules['sets']);
        $this->assertStringContainsString('integer', $rules['sets']);
        $this->assertStringContainsString('min:1', $rules['sets']);
        $this->assertStringContainsString('max:20', $rules['sets']);

        $this->assertStringContainsString('required', $rules['reps']);
        $this->assertStringContainsString('integer', $rules['reps']);
        $this->assertStringContainsString('min:1', $rules['reps']);
        $this->assertStringContainsString('max:100', $rules['reps']);

        $this->assertStringContainsString('in:main,accessory', $rules['exercise_type']);
    }

    public function test_get_next_order_for_program_returns_correct_value()
    {
        // Test with empty program
        $nextOrder = ProgramExercise::getNextOrderForProgram($this->program->id);
        $this->assertEquals(1, $nextOrder);

        // Add some exercises
        ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);

        $nextOrder = ProgramExercise::getNextOrderForProgram($this->program->id);
        $this->assertEquals(3, $nextOrder);
    }

    public function test_reorder_exercises_for_program_fixes_gaps()
    {
        // Create exercises with gaps in ordering
        $exercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $exercise2 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 5, // Gap here
            'exercise_type' => 'accessory'
        ]);

        $exercise3 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise3->id,
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 8, // Another gap
            'exercise_type' => 'accessory'
        ]);

        ProgramExercise::reorderExercisesForProgram($this->program->id);

        $exercise1->refresh();
        $exercise2->refresh();
        $exercise3->refresh();

        $this->assertEquals(1, $exercise1->exercise_order);
        $this->assertEquals(2, $exercise2->exercise_order);
        $this->assertEquals(3, $exercise3->exercise_order);
    }

    public function test_move_to_position_moves_exercise_up()
    {
        // Create three exercises in order
        $exercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $exercise2 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);

        $exercise3 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise3->id,
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 3,
            'exercise_type' => 'accessory'
        ]);

        // Move exercise 3 to position 1
        $exercise3->moveToPosition(1);

        $exercise1->refresh();
        $exercise2->refresh();
        $exercise3->refresh();

        $this->assertEquals(2, $exercise1->exercise_order);
        $this->assertEquals(3, $exercise2->exercise_order);
        $this->assertEquals(1, $exercise3->exercise_order);
    }

    public function test_move_to_position_moves_exercise_down()
    {
        // Create three exercises in order
        $exercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $exercise2 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);

        $exercise3 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise3->id,
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 3,
            'exercise_type' => 'accessory'
        ]);

        // Move exercise 1 to position 3
        $exercise1->moveToPosition(3);

        $exercise1->refresh();
        $exercise2->refresh();
        $exercise3->refresh();

        $this->assertEquals(3, $exercise1->exercise_order);
        $this->assertEquals(1, $exercise2->exercise_order);
        $this->assertEquals(2, $exercise3->exercise_order);
    }

    public function test_move_to_position_does_nothing_when_same_position()
    {
        $exercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $originalOrder = $exercise1->exercise_order;
        $exercise1->moveToPosition(1);
        $exercise1->refresh();

        $this->assertEquals($originalOrder, $exercise1->exercise_order);
    }

    public function test_ordered_scope_returns_exercises_in_order()
    {
        // Create exercises out of order
        $exercise3 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise3->id,
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 3,
            'exercise_type' => 'accessory'
        ]);

        $exercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $exercise2 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);

        $orderedExercises = ProgramExercise::ordered()->get();

        $this->assertEquals($exercise1->id, $orderedExercises[0]->id);
        $this->assertEquals($exercise2->id, $orderedExercises[1]->id);
        $this->assertEquals($exercise3->id, $orderedExercises[2]->id);
    }

    public function test_by_type_scope_filters_correctly()
    {
        $mainExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $accessoryExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);

        $mainExercises = ProgramExercise::byType('main')->get();
        $accessoryExercises = ProgramExercise::byType('accessory')->get();

        $this->assertCount(1, $mainExercises);
        $this->assertCount(1, $accessoryExercises);
        $this->assertEquals($mainExercise->id, $mainExercises->first()->id);
        $this->assertEquals($accessoryExercise->id, $accessoryExercises->first()->id);
    }

    public function test_for_program_scope_filters_correctly()
    {
        $anotherProgram = WorkoutProgram::factory()->create(['user_id' => $this->user->id]);

        $programExercise1 = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $programExercise2 = ProgramExercise::create([
            'workout_program_id' => $anotherProgram->id,
            'exercise_id' => $this->exercise2->id,
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 1,
            'exercise_type' => 'accessory'
        ]);

        $exercisesForProgram1 = ProgramExercise::forProgram($this->program->id)->get();
        $exercisesForProgram2 = ProgramExercise::forProgram($anotherProgram->id)->get();

        $this->assertCount(1, $exercisesForProgram1);
        $this->assertCount(1, $exercisesForProgram2);
        $this->assertEquals($programExercise1->id, $exercisesForProgram1->first()->id);
        $this->assertEquals($programExercise2->id, $exercisesForProgram2->first()->id);
    }

    public function test_workout_program_relationship()
    {
        $programExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $this->assertInstanceOf(WorkoutProgram::class, $programExercise->workoutProgram);
        $this->assertEquals($this->program->id, $programExercise->workoutProgram->id);
    }

    public function test_exercise_relationship()
    {
        $programExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $this->assertInstanceOf(Exercise::class, $programExercise->exercise);
        $this->assertEquals($this->exercise1->id, $programExercise->exercise->id);
    }

    public function test_casts_work_correctly()
    {
        $programExercise = ProgramExercise::create([
            'workout_program_id' => $this->program->id,
            'exercise_id' => $this->exercise1->id,
            'sets' => '3',
            'reps' => '5',
            'exercise_order' => '1',
            'exercise_type' => 'main'
        ]);

        $this->assertIsInt($programExercise->sets);
        $this->assertIsInt($programExercise->reps);
        $this->assertIsInt($programExercise->exercise_order);
    }
}