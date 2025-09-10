<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutProgram;
use Carbon\Carbon;

class WorkoutProgramFormsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_access_create_form()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('workout-programs.create'));

        $response->assertStatus(200);
        $response->assertViewIs('workout_programs.create');
        $response->assertViewHas('exercises');
        $response->assertViewHas('selectedDate');
    }

    /** @test */
    public function authenticated_user_can_access_edit_form()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workoutProgram = WorkoutProgram::factory()->create(['user_id' => $user->id]);
        
        // Attach exercise to program
        $workoutProgram->exercises()->attach($exercise->id, [
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Test notes',
            'exercise_order' => 1,
            'exercise_type' => 'main',
        ]);

        $response = $this->get(route('workout-programs.edit', $workoutProgram));

        $response->assertStatus(200);
        $response->assertViewIs('workout_programs.edit');
        $response->assertViewHas('workoutProgram');
        $response->assertViewHas('exercises');
    }

    /** @test */
    public function user_cannot_access_edit_form_for_other_users_program()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $workoutProgram = WorkoutProgram::factory()->create(['user_id' => $user2->id]);
        
        $workoutProgram->exercises()->attach($exercise->id, [
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Test notes',
            'exercise_order' => 1,
            'exercise_type' => 'main',
        ]);

        $this->actingAs($user1);

        $response = $this->get(route('workout-programs.edit', $workoutProgram));

        $response->assertStatus(403);
    }

    /** @test */
    public function create_form_contains_required_elements()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('workout-programs.create'));

        $response->assertStatus(200);
        $response->assertSee('Create New Workout Program');
        $response->assertSee('name="date"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="notes"', false);
        $response->assertSee('name="exercises[0][exercise_id]"', false);
        $response->assertSee('name="exercises[0][exercise_type]"', false);
        $response->assertSee('name="exercises[0][sets]"', false);
        $response->assertSee('name="exercises[0][reps]"', false);
        $response->assertSee('Add Another Exercise');
    }

    /** @test */
    public function edit_form_contains_required_elements_and_existing_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Test Exercise']);
        $workoutProgram = WorkoutProgram::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Program',
            'notes' => 'Test notes'
        ]);
        
        $workoutProgram->exercises()->attach($exercise->id, [
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Test exercise notes',
            'exercise_order' => 1,
            'exercise_type' => 'main',
        ]);

        $response = $this->get(route('workout-programs.edit', $workoutProgram));

        $response->assertStatus(200);
        $response->assertSee('Edit Workout Program');
        $response->assertSee('Test Program');
        $response->assertSee('Test notes');
        $response->assertSee('Test Exercise');
        $response->assertSee('value="3"', false);
        $response->assertSee('value="5"', false);
        $response->assertSee('Test exercise notes');
        $response->assertSee('Add Another Exercise');
    }

    /** @test */
    public function create_form_displays_user_exercises_only()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);

        $this->actingAs($user1);

        $response = $this->get(route('workout-programs.create'));

        $response->assertStatus(200);
        $response->assertSee('User 1 Exercise');
        $response->assertDontSee('User 2 Exercise');
    }

    /** @test */
    public function edit_form_displays_user_exercises_only()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        
        $workoutProgram = WorkoutProgram::factory()->create(['user_id' => $user1->id]);
        $workoutProgram->exercises()->attach($user1Exercise->id, [
            'sets' => 3,
            'reps' => 5,
            'notes' => 'Test notes',
            'exercise_order' => 1,
            'exercise_type' => 'main',
        ]);

        $this->actingAs($user1);

        $response = $this->get(route('workout-programs.edit', $workoutProgram));

        $response->assertStatus(200);
        $response->assertSee('User 1 Exercise');
        $response->assertDontSee('User 2 Exercise');
    }
}