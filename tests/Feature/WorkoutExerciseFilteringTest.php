<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;

class WorkoutExerciseFilteringTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_only_sees_their_exercises_in_workout_form()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exercise1 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User1 Exercise']);
        $exercise2 = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User2 Exercise']);

        $this->actingAs($user1);

        $response = $this->get(route('workouts.index'));

        $response->assertOk();
        $response->assertSee($exercise1->title);
        $response->assertDontSee($exercise2->title);
    }
}
