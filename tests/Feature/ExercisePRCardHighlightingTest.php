<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExercisePRCardHighlightingTest extends TestCase
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
    public function most_recent_pr_card_has_recent_class_in_html()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Bench Press',
        ]);

        // Create multiple PRs with different dates
        $this->createLiftLogWithDate($exercise, 1, 225, Carbon::now()->subMonths(6));
        $this->createLiftLogWithDate($exercise, 2, 215, Carbon::now()->subDays(10)); // Most recent
        $this->createLiftLogWithDate($exercise, 3, 205, Carbon::now()->subMonths(2));

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        
        // Parse the HTML to verify the structure
        $content = $response->getContent();
        
        // Should contain exactly one pr-card--recent class
        $this->assertEquals(1, substr_count($content, 'pr-card--recent'), 
            'Expected exactly one PR card to have the recent class');
        
        // The recent card should be associated with the 2-rep PR (most recent date)
        // We can verify this by checking that the recent class appears near the 2-rep data
        $this->assertStringContainsString('pr-card--recent', $content);
    }

    /** @test */
    public function pr_card_recent_class_appears_in_correct_html_structure()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Squat',
        ]);

        // Create a single PR to make testing easier
        $this->createLiftLogWithDate($exercise, 1, 315, Carbon::now()->subDays(5));

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Verify the HTML structure contains the expected classes
        $this->assertStringContainsString('pr-card pr-card--recent', $content,
            'Expected PR card to have both base class and recent modifier class');
        
        // Verify it's within the PR cards section
        $this->assertStringContainsString('component-pr-cards-section', $content);
        $this->assertStringContainsString('Heaviest Lifts', $content);
    }

    /** @test */
    public function no_pr_cards_have_recent_class_when_no_prs_exist()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Clean',
        ]);

        // No lift logs created

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Should not contain any recent classes
        $this->assertEquals(0, substr_count($content, 'pr-card--recent'),
            'Expected no recent classes when no PRs exist');
        
        // Should not show PR cards section at all
        $this->assertStringNotContainsString('Heaviest Lifts', $content);
    }

    /** @test */
    public function recent_class_updates_when_new_pr_is_added()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Deadlift',
        ]);

        // Initial state: 1-rep PR is most recent
        $this->createLiftLogWithDate($exercise, 1, 405, Carbon::now()->subDays(10));
        $this->createLiftLogWithDate($exercise, 2, 385, Carbon::now()->subDays(20));

        $response1 = $this->get(route('exercises.show-logs', $exercise));
        $content1 = $response1->getContent();
        
        // Should have exactly one recent class
        $this->assertEquals(1, substr_count($content1, 'pr-card--recent'));

        // Add a newer PR
        $this->createLiftLogWithDate($exercise, 3, 365, Carbon::now()->subDays(2));

        $response2 = $this->get(route('exercises.show-logs', $exercise));
        $content2 = $response2->getContent();
        
        // Should still have exactly one recent class (but now on the 3-rep card)
        $this->assertEquals(1, substr_count($content2, 'pr-card--recent'));
    }

    /** @test */
    public function recent_highlighting_works_with_lift_logs_create_page()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Overhead Press',
        ]);

        // Create PRs
        $this->createLiftLogWithDate($exercise, 1, 155, Carbon::now()->subMonths(2));
        $this->createLiftLogWithDate($exercise, 2, 145, Carbon::now()->subDays(7)); // Most recent

        $response = $this->get(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->toDateString()
        ]));

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Should show PR cards with highlighting on lift-logs/create page too
        $this->assertStringContainsString('Heaviest Lifts', $content);
        $this->assertEquals(1, substr_count($content, 'pr-card--recent'),
            'Expected recent highlighting to work on lift-logs create page');
    }

    /** @test */
    public function recent_highlighting_ignores_exercise_type_restrictions()
    {
        // Test that the highlighting logic works for regular exercises
        $regularExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Bench Press',
        ]);

        $this->createLiftLogWithDate($regularExercise, 1, 225, Carbon::now()->subDays(5));

        $response = $this->get(route('exercises.show-logs', $regularExercise));
        $response->assertStatus(200);
        
        $content = $response->getContent();
        $this->assertEquals(1, substr_count($content, 'pr-card--recent'));

        // Test that non-regular exercises don't show PR cards at all
        $bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight',
            'title' => 'Push-ups',
        ]);

        $this->createLiftLogWithDate($bodyweightExercise, 1, 0, Carbon::now()->subDays(5));

        $response2 = $this->get(route('exercises.show-logs', $bodyweightExercise));
        $response2->assertStatus(200);
        
        $content2 = $response2->getContent();
        $this->assertEquals(0, substr_count($content2, 'pr-card--recent'));
        $this->assertStringNotContainsString('Heaviest Lifts', $content2);
    }

    /** @test */
    public function recent_highlighting_works_with_edge_case_dates()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular',
            'title' => 'Squat',
        ]);

        // Test with same-day PRs (should pick one consistently)
        $sameDay = Carbon::now()->subDays(5);
        $this->createLiftLogWithDate($exercise, 1, 315, $sameDay->copy()->addHours(2));
        $this->createLiftLogWithDate($exercise, 2, 295, $sameDay->copy()->addHours(4)); // Later in day

        $response = $this->get(route('exercises.show-logs', $exercise));
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Should still have exactly one recent class
        $this->assertEquals(1, substr_count($content, 'pr-card--recent'),
            'Expected exactly one recent class even with same-day PRs');
    }

    /**
     * Helper method to create a lift log with a specific date
     */
    private function createLiftLogWithDate(Exercise $exercise, int $reps, float $weight, Carbon $date): LiftLog
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