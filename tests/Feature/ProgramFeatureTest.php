<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Carbon\Carbon;

class ProgramFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_programs()
    {
        $this->get(route('programs.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_see_their_own_programs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id, 'date' => Carbon::today()]);

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertStatus(200)
            ->assertSee($exercise->name);
    }

    public function test_user_cannot_see_programs_of_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $program = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id, 'date' => Carbon::today()]);

        $this->actingAs($user1)
            ->get(route('programs.index'))
            ->assertStatus(200)
            ->assertDontSee($exercise->name);
    }

    public function test_user_can_create_a_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 3,
                'reps' => 10,
                'weight' => 50,
                'comments' => 'Test comments',
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('programs', [
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
            'comments' => 'Test comments',
        ]);
    }

    public function test_user_cannot_create_a_program_with_invalid_data()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('programs.store'), [])
            ->assertSessionHasErrors(['exercise_id', 'date', 'sets', 'reps']);
    }

    public function test_user_can_edit_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->get(route('programs.edit', $program))
            ->assertStatus(200);
    }

    public function test_user_cannot_edit_program_of_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $program = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user1)
            ->get(route('programs.edit', $program))
            ->assertStatus(403);
    }

    public function test_user_can_update_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 5,
                'reps' => 5,
                'weight' => 100,
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 5,
            'reps' => 5,
        ]);
    }

    public function test_user_can_delete_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->delete(route('programs.destroy', $program))
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }
}