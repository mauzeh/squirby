<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Program;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_view_their_own_programs()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('programs.index'));

        $response->assertStatus(200);
        $response->assertSee($program->exercise->name);
    }

    public function test_a_user_cannot_view_programs_belonging_to_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $program = Program::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->get(route('programs.index'));

        $response->assertStatus(200);
        $response->assertDontSee($program->exercise->name);
    }

    public function test_a_user_can_successfully_create_a_program_with_valid_data()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $programData = [
            'exercise_id' => $exercise->id,
            'date' => now()->format('Y-m-d H:i:s'),
            'sets' => 5,
            'reps' => 5,
            'comments' => 'Test comments',
        ];

        $response = $this->actingAs($user)->post(route('programs.store'), $programData);

        $response->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programs', $programData);
    }

    public function test_validation_errors_are_returned_when_creating_a_program_with_invalid_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('programs.store'), []);

        $response->assertSessionHasErrors(['exercise_id', 'date', 'sets', 'reps']);
    }

    public function test_a_user_can_update_their_own_program()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create();

        $updatedProgramData = [
            'exercise_id' => $exercise->id,
            'date' => now()->addDay()->format('Y-m-d H:i:s'),
            'sets' => 10,
            'reps' => 10,
            'comments' => 'Updated comments',
        ];

        $response = $this->actingAs($user)->put(route('programs.update', $program), $updatedProgramData);

        $response->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programs', $updatedProgramData);
    }

    public function test_a_user_can_delete_their_own_program()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('programs.destroy', $program));

        $response->assertRedirect(route('programs.index'));
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('programs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_the_create_view_is_rendered_with_exercise_names()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('programs.create'));

        $response->assertStatus(200);
        $response->assertViewHas('exercises');
        $response->assertSee($exercise->title);
    }
}
