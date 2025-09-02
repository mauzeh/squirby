<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WorkoutLoggingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_view_the_workout_logging_page()
    {
        $response = $this->get('/workouts');

        $response->assertStatus(200);
        $response->assertSee('Add Workout');
    }
}
