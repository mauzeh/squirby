<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\BandService;
use App\Services\OneRepMaxCalculatorService;
use App\Services\TrainingProgressionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TrainingProgressionService $trainingProgressionService;
    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;
    protected BandService $bandService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oneRepMaxCalculatorService = new OneRepMaxCalculatorService();
        $this->bandService = new BandService();
        $this->trainingProgressionService = new TrainingProgressionService(
            $this->oneRepMaxCalculatorService,
            $this->bandService
        );

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

    public function test_get_suggestion_details_returns_null_if_no_last_log()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNull($suggestion);
    }

    public function test_banded_exercise_progression_reps_increase_within_band()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => 'resistance']);

        // Log a lift with red band, 10 reps
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'band_color' => 'red',
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(11, $suggestion->reps);
        $this->assertEquals('red', $suggestion->band_color);
    }

    public function test_banded_exercise_progression_band_changes_after_max_reps()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => 'resistance']);

        // Log a lift with red band, max reps (15)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 15,
            'band_color' => 'red',
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(8, $suggestion->reps); // Default reps on band change
        $this->assertEquals('blue', $suggestion->band_color); // Next harder band
    }

    public function test_banded_exercise_progression_handles_hardest_band()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => 'resistance']);

        // Log a lift with black band, max reps (15)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 15,
            'band_color' => 'black',
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(15, $suggestion->reps); // Reps should not reset if no harder band
        $this->assertEquals('black', $suggestion->band_color); // Stays on black band
    }

    public function test_banded_exercise_progression_with_assistance_type_reps_increase()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => 'assistance']);

        // Log a lift with red band (less assistance), 10 reps
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 10,
            'band_color' => 'red',
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(11, $suggestion->reps);
        $this->assertEquals('red', $suggestion->band_color);
    }

    public function test_banded_exercise_progression_with_assistance_type_band_changes()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => 'assistance']);

        // Log a lift with black band (most assistance), max reps (15)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 15,
            'band_color' => 'black',
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(8, $suggestion->reps); // Default reps on band change
        $this->assertEquals('green', $suggestion->band_color); // Next harder band (less assistance)
    }

    // Test fallbacks for non-banded exercises (existing logic)
    public function test_non_banded_exercise_uses_linear_progression()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => null]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(105, $suggestion->suggestedWeight); // Example linear progression
        $this->assertEquals(5, $suggestion->reps);
    }

    public function test_non_banded_exercise_uses_double_progression()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['band_type' => null]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 10,
        ]);

        $suggestion = $this->trainingProgressionService->getSuggestionDetails($user->id, $exercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(11, $suggestion->reps); // Reps increase by 1
        $this->assertEquals(100, $suggestion->suggestedWeight); // Weight stays same if reps < MAX_REPS
    }
}
