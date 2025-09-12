<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramToLiftLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_and_reps_are_passed_from_program_to_exercise_log_view()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 5,
            'reps' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('exercises.show-logs', [
            'exercise' => $exercise,
            'sets' => $program->sets,
            'reps' => $program->reps,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('sets', 5);
        $response->assertViewHas('reps', 5);
        $response->assertSee('value="5"', false);
    }
}
