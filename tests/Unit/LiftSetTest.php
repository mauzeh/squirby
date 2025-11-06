<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\LiftSet;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftSetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_lift_set_can_be_created_with_band_color()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $liftSet = LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'red',
        ]);

        $this->assertDatabaseHas('lift_sets', [
            'id' => $liftSet->id,
            'band_color' => 'red',
            'weight' => 0,
        ]);
        $this->assertEquals('red', $liftSet->band_color);
    }

    /** @test */
    public function a_lift_set_can_be_created_without_band_color()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'regular']);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $liftSet = LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 10,
            'band_color' => null,
        ]);

        $this->assertDatabaseHas('lift_sets', [
            'id' => $liftSet->id,
            'band_color' => null,
            'weight' => 100,
        ]);
        $this->assertNull($liftSet->band_color);
    }

    /** @test */
    public function band_color_can_be_updated()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'exercise_type' => 'banded_resistance']);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $liftSet = LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10,
            'band_color' => 'red',
        ]);

        $liftSet->update(['band_color' => 'blue']);

        $this->assertDatabaseHas('lift_sets', [
            'id' => $liftSet->id,
            'band_color' => 'blue',
        ]);
        $this->assertEquals('blue', $liftSet->band_color);
    }
}
