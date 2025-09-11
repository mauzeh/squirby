<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_program_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $program->user);
    }

    public function test_a_program_belongs_to_an_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->assertInstanceOf(Exercise::class, $program->exercise);
    }
}