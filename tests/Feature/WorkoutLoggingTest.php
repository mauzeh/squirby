<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WorkoutLoggingTest extends TestCase
{
    use RefreshDatabase;

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
        $this->withHeaders([
            'Accept' => 'application/json',
        ]);

        // Define the route directly within the test
        \Illuminate\Support\Facades\Route::post('/workouts', [\App\Http\Controllers\WorkoutController::class, 'store']);

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
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test workout comments',
            'logged_at' => \Carbon\Carbon::parse($now->format('Y-m-d H:i'))->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'Workout created successfully.');
    }
}
