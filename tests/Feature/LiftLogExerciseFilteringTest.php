<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;

class LiftLogExerciseFilteringTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_only_sees_their_exercises_in_lift_log_form()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exercise1 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User1 Exercise']);
        $exercise2 = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User2 Exercise']);

        \App\Models\LiftLog::factory()->create(['user_id' => $user1->id, 'exercise_id' => $exercise1->id]);

        $this->actingAs($user1);

        $response = $this->get(route('lift-logs.index'));

        $response->assertOk();
        $response->assertSee($exercise1->title);
        $response->assertDontSee($exercise2->title);
    }


}