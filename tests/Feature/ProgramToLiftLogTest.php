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

    public function test_exercise_log_view_loads_successfully()
    {
        $user = User::factory()->create();
        
        // Make user an admin since ProgramController requires admin access
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        }
        $user->roles()->attach($adminRole);
        
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
    }
}
