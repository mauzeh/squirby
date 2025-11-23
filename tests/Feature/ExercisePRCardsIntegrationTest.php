<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExercisePRCardsIntegrationTest extends TestCase
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
    public function exercise_logs_page_shows_pr_cards_for_barbell_exercise()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Back Squat',
        ]);

        // Create lift logs with different rep ranges
        $this->createLiftLog($exercise, 1, 242);
        $this->createLiftLog($exercise, 2, 235);
        $this->createLiftLog($exercise, 3, 230);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        $response->assertSee('Heaviest Lifts');
        $response->assertSee('1 × 1');
        $response->assertSee('242');
        $response->assertSee('1 × 2');
        $response->assertSee('235');
        $response->assertSee('1 × 3');
        $response->assertSee('230');
    }

    /** @test */
    public function exercise_logs_page_shows_calculator_grid()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Bench Press',
        ]);

        // Create a 1-rep max lift log
        $this->createLiftLog($exercise, 1, 200);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        $response->assertSee('1-Rep Max Percentages');
        $response->assertSee('100%');
        $response->assertSee('95%');
        $response->assertSee('90%');
        $response->assertSee('85%');
        $response->assertSee('80%');
        $response->assertSee('75%');
        $response->assertSee('70%');
        $response->assertSee('65%');
        $response->assertSee('60%');
        $response->assertSee('55%');
        $response->assertSee('50%');
        $response->assertSee('45%');
    }

    /** @test */
    public function exercise_logs_page_does_not_show_pr_features_for_dumbbell()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'dumbbell',
            'title' => 'Dumbbell Press',
        ]);

        // Create lift logs
        $this->createLiftLog($exercise, 1, 50);
        $this->createLiftLog($exercise, 2, 45);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        $response->assertDontSee('Heaviest Lifts');
        $response->assertDontSee('1-Rep Max Percentages');
    }

    /** @test */
    public function exercise_logs_page_does_not_show_pr_features_for_cardio()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'cardio',
            'title' => 'Running',
        ]);

        // Create lift logs
        $this->createLiftLog($exercise, 1, 0);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        $response->assertDontSee('Heaviest Lifts');
        $response->assertDontSee('1-Rep Max Percentages');
    }

    /** @test */
    public function pr_cards_display_correct_weights_from_database()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Deadlift',
        ]);

        // Create lift logs with specific weights
        $this->createLiftLog($exercise, 1, 315);
        $this->createLiftLog($exercise, 2, 295);
        $this->createLiftLog($exercise, 3, 275);

        // Create additional logs with lower weights to ensure we get the max
        $this->createLiftLog($exercise, 1, 300);
        $this->createLiftLog($exercise, 2, 280);
        $this->createLiftLog($exercise, 3, 260);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should show the highest weights
        $response->assertSee('315');
        $response->assertSee('295');
        $response->assertSee('275');
        // The PR cards should show the maximum weights, not the lower ones
        // Note: Lower weights may appear in the lift log table, but the PR cards show the max
    }

    /** @test */
    public function calculator_grid_shows_accurate_percentages()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Overhead Press',
        ]);

        // Create a 1-rep max of 100 lbs for easy calculation
        $this->createLiftLog($exercise, 1, 100);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Check for calculated percentages (100% = 100, 95% = 95, 90% = 90, etc.)
        $response->assertSee('100');
        $response->assertSee('95');
        $response->assertSee('90');
        $response->assertSee('85');
        $response->assertSee('80');
        $response->assertSee('75');
        $response->assertSee('70');
        $response->assertSee('65');
        $response->assertSee('60');
        $response->assertSee('55');
        $response->assertSee('50');
        $response->assertSee('45');
    }

    /** @test */
    public function pr_cards_handle_missing_rep_ranges_gracefully()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Front Squat',
        ]);

        // Only create a 1-rep max, no 2 or 3 rep data
        $this->createLiftLog($exercise, 1, 225);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        $response->assertSee('Heaviest Lifts');
        $response->assertSee('1 × 1');
        $response->assertSee('225');
        $response->assertSee('1 × 2');
        $response->assertSee('1 × 3');
        // Should show "—" for missing data
        $response->assertSee('—');
    }

    /** @test */
    public function page_works_normally_when_no_lift_logs_exist()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Clean and Jerk',
        ]);

        // No lift logs created

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should not show PR features when no data exists
        $response->assertDontSee('Heaviest Lifts');
        $response->assertDontSee('1-Rep Max Percentages');
        // Should show empty message
        $response->assertSee('No lift logs found for this exercise.');
    }

    /**
     * Helper method to create a lift log with a specific rep count and weight
     */
    private function createLiftLog(Exercise $exercise, int $reps, float $weight): LiftLog
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()->subDays(rand(1, 30)),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => $reps,
            'weight' => $weight,
        ]);

        return $liftLog;
    }

    /** @test */
    public function exercise_logs_page_shows_estimated_calculator_grid_when_no_low_rep_tests()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Deadlift',
        ]);

        // Create lift logs with only higher rep ranges (no 1-3 rep tests)
        $log = LiftLog::factory()->create([
            'exercise_id' => $exercise->id,
            'user_id' => $this->user->id,
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $log->id,
            'weight' => 315,
            'reps' => 5,
        ]);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should show estimated calculator grid
        $response->assertSee('1-Rep Max Percentages (Estimated)');
        $response->assertSee('Est. 1RM');
        
        // Should show helpful note about performing low rep tests
        $response->assertSee('This 1-rep max is estimated based on your previous lifts');
        $response->assertSee('test your actual 1, 2, or 3 rep max');
        
        // Should NOT show PR cards since there are no 1-3 rep tests
        $response->assertDontSee('Heaviest Lifts');
        $response->assertDontSee('1 × 1');
        $response->assertDontSee('1 × 2');
        $response->assertDontSee('1 × 3');
    }

    /** @test */
    public function exercise_logs_page_shows_actual_pr_cards_when_low_rep_tests_exist()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Squat',
        ]);

        // Create both low rep tests and higher rep sets
        $this->createLiftLog($exercise, 1, 405);
        $this->createLiftLog($exercise, 5, 365);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should show actual PR cards
        $response->assertSee('Heaviest Lifts');
        $response->assertSee('1 × 1');
        $response->assertSee('405');
        
        // Should show non-estimated calculator grid without the note
        $response->assertSee('1-Rep Max Percentages');
        $response->assertDontSee('1-Rep Max Percentages (Estimated)');
        $response->assertDontSee('This 1-rep max is estimated based on your previous lifts');
    }

    /** @test */
    public function exercise_logs_page_shows_warning_when_pr_data_is_stale()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Overhead Press',
        ]);

        // Create a PR that's 4 months old (stale)
        $this->createLiftLogWithDate($exercise, 1, 185, Carbon::now()->subMonths(4));

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should show PR cards with the old data
        $response->assertSee('Heaviest Lifts');
        $response->assertSee('185');
        
        // Should show warning about stale data
        $response->assertSee('Your max lift data is over 3 months old');
        $response->assertSee('Consider retesting your 1, 2, or 3 rep max');
    }

    /** @test */
    public function exercise_logs_page_does_not_show_warning_when_pr_data_is_recent()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Front Squat',
        ]);

        // Create a PR that's 2 months old (recent)
        $this->createLiftLogWithDate($exercise, 1, 225, Carbon::now()->subMonths(2));

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Should show PR cards
        $response->assertSee('Heaviest Lifts');
        $response->assertSee('225');
        
        // Should NOT show warning about stale data
        $response->assertDontSee('Your max lift data is over 3 months old');
    }

    protected function createLiftLogWithDate(Exercise $exercise, int $reps, float $weight, Carbon $date): LiftLog
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $date,
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => $reps,
            'weight' => $weight,
        ]);

        return $liftLog;
    }
}
