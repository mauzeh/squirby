<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandedLiftLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected $withoutMiddleware = [\App\Http\Middleware\VerifyCsrfToken::class];

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
        ]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_log_a_resistance_band_lift()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '10:00',
            'reps' => 10,
            'rounds' => 3,
            'band_color' => 'red',
            'comments' => 'Resistance band workout',
        ]);

        $response->assertRedirect(route('exercises.show-logs', $exercise->id));
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Resistance band workout',
        ]);
        $this->assertDatabaseHas('lift_sets', [
            'reps' => 10,
            'weight' => 0, // Weight should be 0 for banded exercises
            'band_color' => 'red',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_log_an_assistance_band_lift()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_assistance'
        ]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '10:00',
            'reps' => 10,
            'rounds' => 3,
            'band_color' => 'blue',
            'comments' => 'Assistance band workout',
        ]);

        $response->assertRedirect(route('exercises.show-logs', $exercise->id));
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Assistance band workout',
        ]);
        $this->assertDatabaseHas('lift_sets', [
            'reps' => 10,
            'weight' => 0,
            'band_color' => 'blue',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function band_color_is_required_for_banded_exercises()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '10:00',
            'reps' => 10,
            'rounds' => 3,
            'comments' => 'Missing band color',
        ]);

        $response->assertSessionHasErrors('band_color');
        $this->assertDatabaseMissing('lift_logs', [
            'comments' => 'Missing band color',
        ]);
    }

    /** @test */
    public function weight_is_required_for_non_banded_exercises()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '10:00',
            'reps' => 10,
            'rounds' => 3,
            'band_color' => null, // Should not be present or should be null
            'comments' => 'Missing weight',
        ]);

        $response->assertSessionHasErrors('weight');
        $this->assertDatabaseMissing('lift_logs', [
            'comments' => 'Missing weight',
        ]);
    }

    /** @test */
    public function a_user_can_edit_a_banded_lift_log()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'weight' => 0,
            'band_color' => 'red',
        ]);

        $response = $this->put(route('lift-logs.update', $liftLog->id), [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString(),
            'logged_at' => '11:00',
            'reps' => 12,
            'rounds' => 4,
            'band_color' => 'blue',
            'comments' => 'Updated banded workout',
        ]);

        $response->assertRedirect(route('exercises.show-logs', $exercise->id));
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'comments' => 'Updated banded workout',
        ]);
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'reps' => 12,
            'weight' => 0,
            'band_color' => 'blue',
        ]);
        // Ensure old lift sets are deleted
        $this->assertCount(4, $liftLog->fresh()->liftSets);
    }

    /** @test */
    public function exercise_logs_page_displays_band_color_and_no_1rm_chart_for_banded_exercises()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance'
        ]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'weight' => 0,
            'band_color' => 'red',
        ]);

        $response = $this->get(route('exercises.show-logs', $exercise->id));

        $response->assertSee('Band: Red');
        $response->assertDontSee('0 lbs');
        $response->assertSee('Volume Progress');
        $response->assertSee('progressChart');
        $response->assertDontSee('1RM Progress');
    }

    /** @test */
    public function exercise_logs_page_displays_weight_and_1rm_chart_for_non_banded_exercises()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'weight' => 100,
            'band_color' => null,
        ]);

        $response = $this->get(route('exercises.show-logs', $exercise->id));

        $response->assertSee('100 lbs');
        $response->assertDontSee('Band:');
        $response->assertDontSee('1RM chart not available for banded exercises.');
        $response->assertSee('progressChart'); // Ensure the canvas element is present
        $response->assertSee('1RM Progress');
    }
}
