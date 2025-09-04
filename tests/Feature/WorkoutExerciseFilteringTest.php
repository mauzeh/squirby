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

    /** @test */
    public function authenticated_user_cannot_import_workouts_with_other_users_exercises()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exercise2 = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User2 Exercise']);

        $this->actingAs($user1);

        $tsvData = "08/04/2025\t18:00\tUser2 Exercise\t175\t5\t3\tSome comments";
        $date = '2025-08-04';

        $response = $this->post(route('workouts.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('error', 'No exercises found for: User2 Exercise');
        $this->assertDatabaseCount('workouts', 0);
    }
}
