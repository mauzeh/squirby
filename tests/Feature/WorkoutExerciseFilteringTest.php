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

    /** @test */
    public function authenticated_user_can_import_their_own_workouts()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $this->actingAs($user);

        $tsvData = "08/04/2025\t18:00\tUser Exercise\t175\t5\t3\tSome comments";
        $date = '2025-08-04';

        $response = $this->post(route('workouts.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'TSV data imported successfully!');
        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseHas('workouts', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->get(route('workouts.index'));
        $response->assertSee($exercise->title);
        $response->assertSee('175'); // Weight
        $response->assertSee('5'); // Reps
    }

    /** @test */
    public function authenticated_user_sees_error_for_invalid_workout_import_rows()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // TSV data with missing columns (invalid)
        $tsvData = "08/04/2025\t18:00\tUser Exercise\t175\t5"; // Missing 2 columns
        $date = '2025-08-04';

        $response = $this->post(route('workouts.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('error', 'No workouts imported due to invalid data in rows: "08/04/2025	18:00	User Exercise	175	5"');
        $this->assertDatabaseCount('workouts', 0);
    }
}
