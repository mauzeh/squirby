<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiftSetNotesTextTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function a_lift_set_can_store_long_notes()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        // Creates a 7000-character string to ensure it exceeds VARCHAR(255) and tests TEXT type
        $longNote = str_repeat('This is a very long note that should easily exceed the previous VARCHAR(255) limit. ', 200); 

        $liftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => $longNote,
            'band_color' => null,
        ]);

        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
            'notes' => $longNote,
        ]);
    }
}