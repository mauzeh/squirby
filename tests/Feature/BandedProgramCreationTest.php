<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Program;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandedProgramCreationTest extends TestCase
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
            'black' => ['resistance' => 40, 'order' => 4],
        ]]);
        config(['bands.max_reps_before_band_change' => 15]);
        config(['bands.default_reps_on_band_change' => 8]);
    }

    /** @test */
    public function a_user_can_add_a_banded_exercise_to_a_program()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $date = Carbon::today();

        $response = $this->post(route('programs.store'), [
            'exercise_id' => $exercise->id,
            'date' => $date->toDateString(),
            'sets' => 3,
            'reps' => 10,
            'priority' => 0,
            'comments' => 'Programmed banded exercise',
        ]);

        $response->assertRedirect(route('programs.index', ['date' => $date->toDateString()]));
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
            'comments' => 'Programmed banded exercise',
            'date' => $date->toDateTimeString(),
        ]);
    }

    /** @test */
    public function program_mobile_entry_suggests_next_band_and_reps_for_resistance_band_exercise()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $date = Carbon::today();

        // Log a lift with red band, 15 reps (max reps before band change)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date->copy()->subDay(),
        ]);
        // Create 3 sets to match expected suggestion
        for ($i = 0; $i < 3; $i++) {
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'reps' => 15,
                'band_color' => 'red',
            ]);
        }

        // Add exercise to program for today
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $date->toDateString()]));

        // Debug: dump the response content to see what's actually rendered
        // dd($response->getContent());

        $response->assertSeeText('Suggested:');
        $response->assertSeeText('Band: blue');
        $response->assertSeeText('(3 x 8)');
        
        // Verify that the suggested band color is pre-selected in the dropdown
        $this->assertStringContainsString('value="blue"', $response->getContent());
        $this->assertStringContainsString('selected', $response->getContent());
    }

    /** @test */
    public function program_mobile_entry_suggests_next_band_and_reps_for_assistance_band_exercise()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'assistance',
        ]);
        $date = Carbon::today();

        // Log a lift with black band, 15 reps (max reps before band change)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date->copy()->subDay(),
        ]);
        // Create 3 sets to match expected suggestion
        for ($i = 0; $i < 3; $i++) {
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'reps' => 15,
                'band_color' => 'black',
            ]);
        }

        // Add exercise to program for today
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $date->toDateString()]));

        $response->assertSeeText('Suggested:');
        $response->assertSeeText('Band: green');
        $response->assertSeeText('(3 x 8)');
        
        // Verify that the suggested band color is pre-selected in the dropdown
        $this->assertStringContainsString('value="green"', $response->getContent());
        $this->assertStringContainsString('selected', $response->getContent());
    }

    /** @test */
    public function program_mobile_entry_suggests_incremented_reps_within_same_band()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $date = Carbon::today();

        // Log a lift with red band, 10 reps
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date->copy()->subDay(),
        ]);
        // Create 3 sets to match expected suggestion
        for ($i = 0; $i < 3; $i++) {
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'reps' => 10,
                'band_color' => 'red',
            ]);
        }

        // Add exercise to program for today
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $date->toDateString()]));

        $response->assertSeeText('Suggested:');
        $response->assertSeeText('Band: red');
        $response->assertSeeText('(3 x 11)');
        
        // Verify that the suggested band color is pre-selected in the dropdown
        $this->assertStringContainsString('value="red"', $response->getContent());
        $this->assertStringContainsString('selected', $response->getContent());
    }

    /** @test */
    public function mobile_entry_page_loads_correctly_for_banded_exercise_in_program()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $date = Carbon::today();

        // Log a lift with red band, 9 reps (to get a suggestion of 10 reps)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date->copy()->subDay(),
        ]);
        // Create 3 sets to match expected suggestion
        for ($i = 0; $i < 3; $i++) {
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'reps' => 9,
                'band_color' => 'red',
            ]);
        }

        // Add exercise to program for today
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $date->toDateString()]));

        $response->assertOk(); // Assert that the page loads successfully (no 500 error)
        $response->assertSeeText('Suggested:');
        $response->assertSeeText('Band: red');
        $response->assertSeeText('(3 x 10)');
        
        // Verify that the suggested band color is pre-selected in the dropdown
        $this->assertStringContainsString('value="red"', $response->getContent());
        $this->assertStringContainsString('selected', $response->getContent());
    }

    /** @test */
    public function a_user_can_quick_add_a_banded_exercise_to_program()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
        ]);
        $date = Carbon::today();

        $response = $this->get(route('programs.quick-add', [
            'exercise' => $exercise->id,
            'date' => $date->toDateString(),
            'redirect_to' => 'mobile-entry',
        ]));

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $date->toDateString()]));
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $date->toDateTimeString(),
        ]);
    }
}
