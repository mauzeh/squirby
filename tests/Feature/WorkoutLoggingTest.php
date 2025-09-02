<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WorkoutLoggingTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test */
    public function a_user_can_view_the_workout_logging_page()
    {
        $response = $this->get('/workouts');

        $response->assertStatus(200);
        $response->assertSee('Add Workout');
    }

    /** @test */
    public function a_user_can_create_a_workout()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $exercise = \App\Models\Exercise::factory()->create();

        $now = now();

        $workoutData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test workout comments',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $now->format('H:i'),
        ];

        $response = $this->post(route('workouts.store'), $workoutData);

        $this->assertDatabaseHas('workouts', [
            'exercise_id' => $exercise->id,
            'comments' => 'Test workout comments',
            'logged_at' => \Carbon\Carbon::parse($now->format('Y-m-d H:i'))->format('Y-m-d H:i:s'),
        ]);

        $workout = \App\Models\Workout::where('exercise_id', $exercise->id)->first();

        $this->assertDatabaseCount('workout_sets', 3);
        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id,
            'weight' => 100,
            'reps' => 5,
            'notes' => 'Test workout comments',
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'Workout created successfully.');
    }

    /** @test */
    public function a_user_can_update_a_workout()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $exercise = \App\Models\Exercise::factory()->create();
        $workout = \App\Models\Workout::factory()->create([
            'exercise_id' => $exercise->id,
            'comments' => 'Original comments',
        ]);
        $workout->workoutSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => 'Original comments',
        ]);

        $updatedExercise = \App\Models\Exercise::factory()->create();
        $updatedWorkoutData = [
            'exercise_id' => $updatedExercise->id,
            'weight' => 120,
            'reps' => 6,
            'rounds' => 4,
            'comments' => 'Updated comments',
            'date' => $workout->logged_at->format('Y-m-d'),
            'logged_at' => $workout->logged_at->format('H:i'),
        ];

        $response = $this->put(route('workouts.update', $workout->id), $updatedWorkoutData);

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'exercise_id' => $updatedExercise->id,
            'comments' => 'Updated comments',
        ]);

        $this->assertDatabaseCount('workout_sets', 4);
        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id,
            'weight' => 120,
            'reps' => 6,
            'notes' => 'Updated comments',
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'Workout updated successfully.');
    }

    /** @test */
    public function a_user_can_view_workouts_on_index_page()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $backSquat = \App\Models\Exercise::factory()->create(['title' => 'Back Squat']);
        $deadlift = \App\Models\Exercise::factory()->create(['title' => 'Deadlift']);

        $workout1 = \App\Models\Workout::factory()->create([
            'exercise_id' => $backSquat->id,
            'comments' => 'Squat comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat comments',
        ]);

        $workout2 = \App\Models\Workout::factory()->create([
            'exercise_id' => $deadlift->id,
            'comments' => 'Deadlift comments',
        ]);
        $workout2->workoutSets()->create([
            'weight' => 300,
            'reps' => 3,
            'notes' => 'Deadlift comments',
        ]);

        $response = $this->get(route('workouts.index'));
        $response->assertStatus(200);

        // Assert Back Squat workout details
        $response->assertSee($backSquat->title);
        $response->assertSee($workout1->display_weight . ' lbs');
        $response->assertSee($workout1->display_reps . ' x ' . $workout1->display_rounds);
        $response->assertSee($workout1->comments);

        // Assert Deadlift workout details
        $response->assertSee($deadlift->title);
        $response->assertSee($workout2->display_weight . ' lbs');
        $response->assertSee($workout2->display_reps . ' x ' . $workout2->display_rounds);
        $response->assertSee($workout2->comments);
    }

    /** @test */
    public function a_user_can_view_exercise_logs_page(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        $exercise = \App\Models\Exercise::factory()->create();

        $response = $this->get('/exercises/' . $exercise->id . '/logs');

        $response->assertStatus(200);
        $response->assertSee($exercise->name);
    }

    /** @test */
    public function a_user_can_view_workouts_on_exercise_logs_page()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $backSquat = \App\Models\Exercise::factory()->create(['title' => 'Back Squat']);

        $workout1 = \App\Models\Workout::factory()->create([
            'exercise_id' => $backSquat->id,
            'comments' => 'Squat workout 1 comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat workout 1 comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat workout 1 comments',
        ]);
        $workout1->workoutSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat workout 1 comments',
        ]);

        $workout2 = \App\Models\Workout::factory()->create([
            'exercise_id' => $backSquat->id,
            'comments' => 'Squat workout 2 comments',
        ]);
        $workout2->workoutSets()->create([
            'weight' => 300,
            'reps' => 3,
            'notes' => 'Squat workout 2 comments',
        ]);

        $response = $this->get('/exercises/' . $backSquat->id . '/logs');
        $response->assertStatus(200);

        // Assert Back Squat workout details
        $response->assertSee($backSquat->title);
        $response->assertSee($workout1->display_weight . ' lbs');
        $response->assertSee($workout1->display_reps . ' x ' . $workout1->display_rounds);
        $response->assertSee($workout1->comments);
        $response->assertSee($workout2->display_weight . ' lbs');
        $response->assertSee($workout2->display_reps . ' x ' . $workout2->display_rounds);
        $response->assertSee($workout2->comments);
    }
}