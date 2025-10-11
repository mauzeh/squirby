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

        $this->actingAs($user1);

        $response = $this->get(route('lift-logs.index'));

        $response->assertOk();
        $response->assertSee($exercise1->title);
        $response->assertDontSee($exercise2->title);
    }

    /** @test */
    public function authenticated_user_cannot_import_lift_logs_with_other_users_exercises()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $exercise2 = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User2 Exercise']);

        $this->actingAs($user1);

        $tsvData = "08/04/2025\t18:00\tUser2 Exercise\t175\t5\t3\tSome comments";
        $date = '2025-08-04';

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error');
        $errorMessage = session('error');
        $this->assertStringContainsString('No exercises were found for the following names:', $errorMessage);
        $this->assertStringContainsString('User2 Exercise', $errorMessage);
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function authenticated_user_can_import_their_own_lift_logs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $this->actingAs($user);

        $tsvData = "08/04/2025\t18:00\tUser Exercise\t175\t5\t3\tSome comments";
        $date = '2025-08-04';

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);
        $this->assertDatabaseCount('lift_logs', 1);
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->get(route('lift-logs.index'));
        $response->assertSee($exercise->title);
        $response->assertSee('175'); // Weight
        $response->assertSee('5'); // Reps
    }

    /** @test */
    public function authenticated_user_sees_error_for_invalid_lift_log_import_rows()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // TSV data with missing columns (invalid)
        $tsvData = "08/04/2025\t18:00\tUser Exercise\t175\t5"; // Missing 2 columns
        $date = '2025-08-04';

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error', 'No lift logs imported due to invalid data in rows: "08/04/2025	18:00	User Exercise	175	5"');
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function authenticated_user_can_import_lift_logs_without_comments()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise No Comments']);

        $this->actingAs($user);

        // TSV data with 6 columns (no comments)
        $tsvData = "08/05/2025\t10:00\tUser Exercise No Comments\t100\t10\t3";
        $date = '2025-08-05';

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => $date,
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);
        $this->assertDatabaseCount('lift_logs', 1);
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'comments' => "",
        ]);

        $response = $this->get(route('lift-logs.index'));
        $response->assertSee($exercise->title);
        $response->assertSee('100'); // Weight
        $response->assertSee('10'); // Reps
    }
}