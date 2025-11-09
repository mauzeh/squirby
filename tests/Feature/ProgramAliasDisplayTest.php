<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\Program;
use Carbon\Carbon;

class ProgramAliasDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_index_displays_exercise_alias_instead_of_title()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.index'));

        $response->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press');
    }

    public function test_program_index_displays_exercise_title_when_no_alias_exists()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.index'));

        $response->assertStatus(200)
            ->assertSee('Bench Press');
    }

    public function test_program_create_form_displays_exercise_aliases_in_dropdown()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.create'));

        $response->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press');
    }

    public function test_program_edit_form_displays_exercise_alias()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.edit', $program));

        $response->assertStatus(200)
            ->assertSee('BP');
    }

    public function test_different_users_see_their_own_aliases_in_programs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        // User 1 has alias "BP"
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        $program1 = Program::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()
        ]);

        // User 2 has alias "Bench"
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Bench'
        ]);
        $program2 = Program::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()
        ]);

        // User 1 sees "BP"
        $response1 = $this->actingAs($user1)
            ->get(route('programs.index'));
        $response1->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press')
            ->assertDontSee('Bench');

        // User 2 sees "Bench"
        $response2 = $this->actingAs($user2)
            ->get(route('programs.index'));
        $response2->assertStatus(200)
            ->assertSee('Bench')
            ->assertDontSee('Bench Press')
            ->assertDontSee('BP');
    }
}
