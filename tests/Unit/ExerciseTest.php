<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_exercise_can_be_created_with_is_bodyweight_attribute()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'description' => 'A bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertTrue($exercise->is_bodyweight);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function an_exercise_defaults_to_not_bodyweight()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'description' => 'A weighted exercise',
        ]);

        $this->assertFalse($exercise->is_bodyweight);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => false,
        ]);
    }
}
