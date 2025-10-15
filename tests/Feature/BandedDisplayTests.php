<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandedDisplayTests extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Mock the config helper for testing purposes
        config(['bands.colors' => [
            'red' => ['resistance' => 10, 'order' => 1],
            'blue' => ['resistance' => 20, 'order' => 2],
            'green' => ['resistance' => 30, 'order' => 3],
            'black' => ['resistance' => 40, 'order' => 4],
        ]]);
    }

    /** @test */
    public function lift_logs_table_omits_1rm_column_for_banded_exercises()
    {
        $this->actingAs($this->user);
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $nonBandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => null,
        ]);

        $bandedLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $bandedLiftLog->id,
            'reps' => 10,
            'weight' => 0,
            'band_color' => 'red',
        ]);

        $nonBandedLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $nonBandedExercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $nonBandedLiftLog->id,
            'reps' => 5,
            'weight' => 100,
            'band_color' => null,
        ]);

        $response = $this->get(route('lift-logs.index'));

        // For banded exercise, 1RM should not be displayed as a value
        $response->assertDontSeeText('1RM:'); // Assert that the 1RM label is not present
        $response->assertSeeText('Band: red');

        // For non-banded exercise, 1RM should be displayed
        $response->assertSeeText(round($nonBandedLiftLog->one_rep_max) . ' lbs');
        $response->assertSeeText('100 lbs');
    }

    /** @test */
    public function exercise_logs_page_does_not_render_1rm_chart_for_banded_exercises()
    {
        $this->actingAs($this->user);
        $bandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);

        $bandedLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $bandedLiftLog->id,
            'reps' => 10,
            'weight' => 0,
            'band_color' => 'red',
        ]);

        $response = $this->get(route('exercises.show-logs', $bandedExercise->id));

        $response->assertSeeText('1RM chart not available for banded exercises.');
        $response->assertDontSee('<canvas id="oneRepMaxChart">');
    }

    /** @test */
    public function exercise_logs_page_renders_1rm_chart_for_non_banded_exercises()
    {
        $this->actingAs($this->user);
        $nonBandedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => null,
        ]);

        $nonBandedLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $nonBandedExercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $nonBandedLiftLog->id,
            'reps' => 5,
            'weight' => 100,
            'band_color' => null,
        ]);

        $nonBandedLiftLog->refresh(); // Ensure accessors are re-evaluated
        $this->assertGreaterThan(0, $nonBandedLiftLog->best_one_rep_max); // Ensure 1RM is calculated

        $response = $this->get(route('exercises.show-logs', $nonBandedExercise->id));

        $response->assertDontSeeText('1RM chart not available for banded exercises.');
        $response->assertSeeInOrder([
            '<h3>1RM Progress</h3>',
            '<canvas id="oneRepMaxChart">',
        ]);
    }
}
