<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutProgram;
use App\Models\ProgramExercise;

class WorkoutProgramExerciseOrderingTest extends TestCase
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
        
        $this->exercise1 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Back Squat'
        ]);
        $this->exercise2 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press'
        ]);
        $this->exercise3 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Romanian Deadlifts'
        ]);
    }

    /** @test */
    public function exercises_are_displayed_in_correct_order()
    {
        $this->actingAs($this->user);
        
        // Add exercises all as main lifts to test ordering within the same type
        $this->program->exercises()->attach($this->exercise1->id, [
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 3,
            'exercise_type' => 'main'
        ]);
        
        $this->program->exercises()->attach($this->exercise2->id, [
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);
        
        $this->program->exercises()->attach($this->exercise3->id, [
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 2,
            'exercise_type' => 'main'
        ]);

        $response = $this->get(route('workout-programs.index', ['date' => $this->program->date->format('Y-m-d')]));

        $response->assertStatus(200);
        
        // Check that exercises appear in the correct order within the Main Lifts section
        $content = $response->getContent();
        $benchPos = strpos($content, 'Bench Press');
        $romanianPos = strpos($content, 'Romanian Deadlifts');
        $squatPos = strpos($content, 'Back Squat');
        
        // Expected order: Bench Press (order 1), Romanian Deadlifts (order 2), Back Squat (order 3)
        $this->assertTrue($benchPos < $romanianPos, "Bench Press should appear before Romanian Deadlifts");
        $this->assertTrue($romanianPos < $squatPos, "Romanian Deadlifts should appear before Back Squat");
    }

    /** @test */
    public function exercises_are_grouped_by_type()
    {
        $this->actingAs($this->user);
        
        $this->program->exercises()->attach($this->exercise1->id, [
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);
        
        $this->program->exercises()->attach($this->exercise2->id, [
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'main'
        ]);
        
        $this->program->exercises()->attach($this->exercise3->id, [
            'sets' => 2,
            'reps' => 10,
            'exercise_order' => 3,
            'exercise_type' => 'accessory'
        ]);

        $response = $this->get(route('workout-programs.index', ['date' => $this->program->date->format('Y-m-d')]));

        $response->assertStatus(200);
        $response->assertSee('Main Lifts');
        $response->assertSee('Accessory Work');
    }

    /** @test */
    public function creating_program_maintains_exercise_order()
    {
        $this->actingAs($this->user);
        
        $programData = [
            'date' => '2025-09-15',
            'name' => 'Test Program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercise1->id,
                    'sets' => 3,
                    'reps' => 5,
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise2->id,
                    'sets' => 3,
                    'reps' => 8,
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise3->id,
                    'sets' => 2,
                    'reps' => 10,
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $programData);

        $response->assertRedirect();
        
        $program = WorkoutProgram::where('user_id', $this->user->id)->first();
        $exercises = $program->exercises()->orderByPivot('exercise_order')->get();
        
        $this->assertEquals($this->exercise1->id, $exercises[0]->id);
        $this->assertEquals(1, $exercises[0]->pivot->exercise_order);
        
        $this->assertEquals($this->exercise2->id, $exercises[1]->id);
        $this->assertEquals(2, $exercises[1]->pivot->exercise_order);
        
        $this->assertEquals($this->exercise3->id, $exercises[2]->id);
        $this->assertEquals(3, $exercises[2]->pivot->exercise_order);
    }

    /** @test */
    public function updating_program_reorders_exercises_correctly()
    {
        $this->actingAs($this->user);
        
        // Create program with initial exercises
        $this->program->exercises()->attach($this->exercise1->id, [
            'sets' => 3,
            'reps' => 5,
            'exercise_order' => 1,
            'exercise_type' => 'main'
        ]);
        
        $this->program->exercises()->attach($this->exercise2->id, [
            'sets' => 3,
            'reps' => 8,
            'exercise_order' => 2,
            'exercise_type' => 'accessory'
        ]);
        
        // Update with reordered exercises
        $updateData = [
            'date' => $this->program->date->format('Y-m-d'),
            'name' => $this->program->name,
            'exercises' => [
                [
                    'exercise_id' => $this->exercise2->id, // Now first
                    'sets' => 4,
                    'reps' => 6,
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise3->id, // New exercise
                    'sets' => 2,
                    'reps' => 10,
                    'exercise_type' => 'accessory'
                ],
                [
                    'exercise_id' => $this->exercise1->id, // Now last
                    'sets' => 3,
                    'reps' => 5,
                    'exercise_type' => 'main'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->put(route('workout-programs.update', $this->program), $updateData);

        $response->assertRedirect();
        
        $this->program->refresh();
        $exercises = $this->program->exercises()->orderByPivot('exercise_order')->get();
        
        $this->assertCount(3, $exercises);
        $this->assertEquals($this->exercise2->id, $exercises[0]->id);
        $this->assertEquals(1, $exercises[0]->pivot->exercise_order);
        
        $this->assertEquals($this->exercise3->id, $exercises[1]->id);
        $this->assertEquals(2, $exercises[1]->pivot->exercise_order);
        
        $this->assertEquals($this->exercise1->id, $exercises[2]->id);
        $this->assertEquals(3, $exercises[2]->pivot->exercise_order);
    }

    /** @test */
    public function exercise_type_categorization_works_correctly()
    {
        $this->actingAs($this->user);
        
        $programData = [
            'date' => '2025-09-15',
            'name' => 'Test Program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercise1->id,
                    'sets' => 3,
                    'reps' => 5,
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise2->id,
                    'sets' => 3,
                    'reps' => 8,
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise3->id,
                    'sets' => 2,
                    'reps' => 10,
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $programData);

        $response->assertRedirect();
        
        $program = WorkoutProgram::where('user_id', $this->user->id)->first();
        
        $mainExercises = $program->exercises()->wherePivot('exercise_type', 'main')->get();
        $accessoryExercises = $program->exercises()->wherePivot('exercise_type', 'accessory')->get();
        
        $this->assertCount(2, $mainExercises);
        $this->assertCount(1, $accessoryExercises);
        
        $this->assertTrue($mainExercises->contains('id', $this->exercise1->id));
        $this->assertTrue($mainExercises->contains('id', $this->exercise2->id));
        $this->assertTrue($accessoryExercises->contains('id', $this->exercise3->id));
    }

    /** @test */
    public function exercise_type_validation_prevents_invalid_types()
    {
        $this->actingAs($this->user);
        
        $programData = [
            'date' => '2025-09-15',
            'name' => 'Test Program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercise1->id,
                    'sets' => 3,
                    'reps' => 5,
                    'exercise_type' => 'invalid_type'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $programData);

        $response->assertSessionHasErrors(['exercises.0.exercise_type']);
    }

    /** @test */
    public function complex_program_with_mixed_types_and_ordering()
    {
        $this->actingAs($this->user);
        
        // Create additional exercises for a more complex program
        $exercise4 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Overhead Press'
        ]);
        $exercise5 = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Face Pulls'
        ]);
        
        $programData = [
            'date' => '2025-09-15',
            'name' => 'Complex Program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercise1->id, // Back Squat - Main
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $exercise4->id, // Overhead Press - Main
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'moderate',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercise3->id, // Romanian Deadlifts - Accessory
                    'sets' => 3,
                    'reps' => 8,
                    'notes' => 'focus on form',
                    'exercise_type' => 'accessory'
                ],
                [
                    'exercise_id' => $exercise5->id, // Face Pulls - Accessory
                    'sets' => 3,
                    'reps' => 15,
                    'notes' => 'light weight',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $programData);

        $response->assertRedirect();
        
        $program = WorkoutProgram::where('user_id', $this->user->id)->first();
        
        // Verify total exercise count
        $this->assertCount(4, $program->exercises);
        
        // Verify type categorization
        $mainExercises = $program->exercises()->wherePivot('exercise_type', 'main')->orderByPivot('exercise_order')->get();
        $accessoryExercises = $program->exercises()->wherePivot('exercise_type', 'accessory')->orderByPivot('exercise_order')->get();
        
        $this->assertCount(2, $mainExercises);
        $this->assertCount(2, $accessoryExercises);
        
        // Verify ordering within types
        $this->assertEquals('Back Squat', $mainExercises[0]->title);
        $this->assertEquals('Overhead Press', $mainExercises[1]->title);
        $this->assertEquals('Romanian Deadlifts', $accessoryExercises[0]->title);
        $this->assertEquals('Face Pulls', $accessoryExercises[1]->title);
        
        // Verify overall ordering
        $allExercises = $program->exercises()->orderByPivot('exercise_order')->get();
        $this->assertEquals(1, $allExercises[0]->pivot->exercise_order);
        $this->assertEquals(2, $allExercises[1]->pivot->exercise_order);
        $this->assertEquals(3, $allExercises[2]->pivot->exercise_order);
        $this->assertEquals(4, $allExercises[3]->pivot->exercise_order);
    }
}