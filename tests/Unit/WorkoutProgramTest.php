<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkoutProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_program_belongs_to_user()
    {
        $user = User::factory()->create();
        $program = WorkoutProgram::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $program->user);
        $this->assertEquals($user->id, $program->user->id);
    }

    public function test_workout_program_has_many_exercises_with_pivot_data()
    {
        $user = User::factory()->create();
        $program = WorkoutProgram::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $program->exercises()->attach($exercise->id, [
            'sets' => 3,
            'reps' => 5,
            'notes' => 'heavy',
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);

        $this->assertCount(1, $program->exercises);
        $this->assertEquals(3, $program->exercises->first()->pivot->sets);
        $this->assertEquals(5, $program->exercises->first()->pivot->reps);
        $this->assertEquals('heavy', $program->exercises->first()->pivot->notes);
        $this->assertEquals(1, $program->exercises->first()->pivot->exercise_order);
        $this->assertEquals('main', $program->exercises->first()->pivot->exercise_type);
    }

    public function test_exercises_are_ordered_by_exercise_order()
    {
        $user = User::factory()->create();
        $program = WorkoutProgram::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Exercise 2']);

        // Attach exercises in reverse order
        $program->exercises()->attach($exercise1->id, [
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 2,
            'exercise_type' => 'main'
        ]);
        $program->exercises()->attach($exercise2->id, [
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 1,
            'exercise_type' => 'accessory'
        ]);

        $exercises = $program->exercises;
        $this->assertEquals('Exercise 2', $exercises->first()->title);
        $this->assertEquals('Exercise 1', $exercises->last()->title);
    }

    public function test_scope_for_date_filters_by_specific_date()
    {
        $user = User::factory()->create();
        $date1 = '2025-09-15';
        $date2 = '2025-09-16';
        
        $program1 = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => $date1
        ]);
        $program2 = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => $date2
        ]);

        $results = WorkoutProgram::forDate($date1)->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals($program1->id, $results->first()->id);
    }

    public function test_scope_for_date_range_filters_by_date_range()
    {
        $user = User::factory()->create();
        
        $program1 = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-09-15'
        ]);
        $program2 = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-09-16'
        ]);
        $program3 = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-09-20'
        ]);

        $results = WorkoutProgram::forDateRange('2025-09-15', '2025-09-17')->get();
        
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $program1->id));
        $this->assertTrue($results->contains('id', $program2->id));
        $this->assertFalse($results->contains('id', $program3->id));
    }

    public function test_scope_for_user_filters_by_user_id()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $program1 = WorkoutProgram::factory()->create(['user_id' => $user1->id]);
        $program2 = WorkoutProgram::factory()->create(['user_id' => $user2->id]);

        $results = WorkoutProgram::forUser($user1->id)->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals($program1->id, $results->first()->id);
    }

    public function test_date_is_cast_to_carbon_date()
    {
        $user = User::factory()->create();
        $program = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-09-15'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $program->date);
        $this->assertEquals('2025-09-15', $program->date->format('Y-m-d'));
    }

    public function test_fillable_fields_are_properly_set()
    {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'date' => '2025-09-15',
            'name' => 'Heavy Squat Day',
            'notes' => 'Focus on form'
        ];

        $program = WorkoutProgram::create($data);

        $this->assertEquals($user->id, $program->user_id);
        $this->assertEquals('2025-09-15', $program->date->format('Y-m-d'));
        $this->assertEquals('Heavy Squat Day', $program->name);
        $this->assertEquals('Focus on form', $program->notes);
    }
}