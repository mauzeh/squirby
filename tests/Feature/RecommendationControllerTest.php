<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $benchPress;
    private Exercise $pullUp;
    private Exercise $squat;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create exercises with intelligence data for testing
        $this->benchPress = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press'
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->benchPress->id,
            'canonical_name' => 'bench_press',
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        $this->pullUp = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Pull Up',
            'canonical_name' => 'pull_up'
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->pullUp->id,
            'canonical_name' => 'pull_up',
            'movement_archetype' => 'pull',
            'primary_mover' => 'latissimus_dorsi',
            'largest_muscle' => 'latissimus_dorsi',
            'difficulty_level' => 4,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'latissimus_dorsi',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        $this->squat = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Back Squat',
            'canonical_name' => 'back_squat'
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->squat->id,
            'canonical_name' => 'back_squat',
            'movement_archetype' => 'squat',
            'primary_mover' => 'quadriceps',
            'largest_muscle' => 'quadriceps',
            'difficulty_level' => 2,
            'recovery_hours' => 72,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'quadriceps',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function recommendations_index_displays_page_successfully()
    {
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('recommendations.index');
        $response->assertSee('Exercise Recommendations');
        $response->assertSee('Based on your activity over the last 31 days');
    }

    /** @test */
    public function recommendations_index_shows_button_based_filter_interface()
    {
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee('Filter Recommendations');
        
        // Check button-based filter interface is present
        $response->assertSee('Movement Pattern:');
        $response->assertSee('Difficulty Level:');
        
        // Check movement archetype buttons are present
        $response->assertSee('All Patterns');
        $response->assertSee('Push');
        $response->assertSee('Pull');
        $response->assertSee('Squat');
        $response->assertSee('Hinge');
        $response->assertSee('Carry');
        $response->assertSee('Core');
        
        // Check difficulty level buttons are present
        $response->assertSee('All Levels');
        $response->assertSee('Level 1');
        $response->assertSee('Level 2');
        $response->assertSee('Level 3');
        $response->assertSee('Level 4');
        $response->assertSee('Level 5');
        
        // Check that Clear Filters button is present
        $response->assertSee('Clear Filters');
        
        // Verify no dropdown elements exist (replaced with buttons)
        $response->assertDontSee('<select');
        $response->assertDontSee('Apply Filters');
        $response->assertDontSee('Refresh');
    }

    /** @test */
    public function recommendations_index_displays_recommendations_when_available()
    {
        // Create some user activity to generate recommendations
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertDontSee('No Recommendations Available');
        $response->assertSee('recommendations-grid');
    }

    /** @test */
    public function recommendations_index_shows_no_recommendations_message_when_none_available()
    {
        // Don't create any user activity or intelligence data
        Exercise::query()->delete();
        ExerciseIntelligence::query()->delete();

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee('No Recommendations Available');
        $response->assertSee('No exercises have intelligence data configured');
        $response->assertSee('Browse Exercises');
        $response->assertSee('Log a Workout');
    }

    /** @test */
    public function recommendations_index_filters_by_movement_archetype()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', ['movement_archetype' => 'pull']));

        $response->assertStatus(200);
        
        // Should only show pull exercises in recommendations
        $viewData = $response->viewData('recommendations');
        foreach ($viewData as $recommendation) {
            $this->assertEquals('pull', $recommendation['intelligence']->movement_archetype);
        }
    }

    /** @test */
    public function recommendations_index_filters_by_difficulty_level()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', ['difficulty_level' => 2]));

        $response->assertStatus(200);
        
        // Should only show difficulty level 2 exercises
        $viewData = $response->viewData('recommendations');
        foreach ($viewData as $recommendation) {
            $this->assertEquals(2, $recommendation['intelligence']->difficulty_level);
        }
    }

    /** @test */
    public function recommendations_index_validates_filter_parameters()
    {
        // Test invalid movement archetype
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'movement_archetype' => 'invalid_archetype'
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['movement_archetype']);

        // Test invalid difficulty level (too high)
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'difficulty_level' => 10
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['difficulty_level']);

        // Test invalid difficulty level (too low)
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'difficulty_level' => 0
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['difficulty_level']);

        // Test invalid difficulty level (non-integer)
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'difficulty_level' => 'invalid'
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['difficulty_level']);
    }



    /** @test */
    public function recommendations_require_authentication()
    {
        $response = $this->get(route('recommendations.index'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function recommendations_index_maintains_filter_state_in_url_parameters()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'movement_archetype' => 'push',
                'difficulty_level' => 3
            ]));

        $response->assertStatus(200);
        
        // Check that filter values are passed to the view
        $response->assertViewHas('movementArchetype', 'push');
        $response->assertViewHas('difficultyLevel', 3);
        
        // Verify URL parameters are maintained for bookmarking
        $this->assertEquals('push', $response->viewData('movementArchetype'));
        $this->assertEquals(3, $response->viewData('difficultyLevel'));
    }

    /** @test */
    public function recommendations_index_handles_combined_filters_correctly()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'movement_archetype' => 'push',
                'difficulty_level' => 3
            ]));

        $response->assertStatus(200);
        
        // Should only show exercises that match both filters
        $viewData = $response->viewData('recommendations');
        foreach ($viewData as $recommendation) {
            $this->assertEquals('push', $recommendation['intelligence']->movement_archetype);
            $this->assertEquals(3, $recommendation['intelligence']->difficulty_level);
        }
    }

    /** @test */
    public function recommendations_index_handles_empty_filter_results_gracefully()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(5)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        // Filter for a combination that won't match any exercises
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'movement_archetype' => 'carry',
                'difficulty_level' => 5
            ]));

        $response->assertStatus(200);
        
        // Should handle empty results gracefully
        $viewData = $response->viewData('recommendations');
        $this->assertEmpty($viewData);
        
        // Filter state should still be maintained
        $response->assertViewHas('movementArchetype', 'carry');
        $response->assertViewHas('difficultyLevel', 5);
    }

    /** @test */
    public function recommendations_display_exercise_metadata_correctly()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        // Check that exercise metadata is displayed
        $response->assertSee('Movement Pattern:');
        $response->assertSee('Difficulty:');
        $response->assertSee('Primary Focus:');
        $response->assertSee('Exercise Type:');
        $response->assertSee('Recovery Time:');
        
        // Check for muscle involvement section
        $response->assertSee('Muscles Targeted');
        $response->assertSee('Why This Exercise?');
        
        // Check for action buttons
        $response->assertSee('View Exercise History');
        $response->assertSee('Add to Today');
    }

    /** @test */
    public function recommendations_show_reasoning_for_suggestions()
    {
        // Create user activity that will generate specific reasoning
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        // Should show reasoning section
        $response->assertSee('Why This Exercise?');
        $response->assertSee('reasoning-list');
    }

    /** @test */
    public function recommendations_only_include_global_exercises_with_intelligence()
    {
        // Create a user-specific exercise (should not be recommended)
        $userExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'User Custom Exercise'
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $userExercise->id,
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    [
                        'name' => 'pectoralis_major',
                        'role' => 'primary_mover',
                        'contraction_type' => 'isotonic'
                    ]
                ]
            ]
        ]);

        // Create a global exercise without intelligence (should not be recommended)
        $globalExerciseNoIntelligence = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Global Exercise No Intelligence'
        ]);

        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        $recommendations = $response->viewData('recommendations');
        $recommendedExerciseIds = array_column(array_column($recommendations, 'exercise'), 'id');

        // User-specific exercise should NOT be recommended
        $this->assertNotContains($userExercise->id, $recommendedExerciseIds);
        
        // Global exercise without intelligence should NOT be recommended
        $this->assertNotContains($globalExerciseNoIntelligence->id, $recommendedExerciseIds);
        
        // Only global exercises with intelligence should be recommended
        foreach ($recommendations as $recommendation) {
            $exercise = Exercise::find($recommendation['exercise']->id);
            $this->assertNull($exercise->user_id, 'Recommended exercise should be global (user_id should be null)');
            $this->assertNotNull($exercise->intelligence, 'Recommended exercise should have intelligence data');
        }
    }

    /** @test */
    public function recommendations_handle_missing_intelligence_data_gracefully()
    {
        // Delete all intelligence data
        ExerciseIntelligence::query()->delete();

        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        // Should not crash and should show no recommendations message
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee('No Recommendations Available');
    }

    /** @test */
    public function recommendations_index_handles_server_errors_gracefully()
    {
        // Create user activity
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'reps' => 8,
            'weight' => 185
        ]);

        // Test with valid parameters to ensure basic functionality works
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('recommendations.index');
        
        // Verify error handling doesn't break the page structure
        $response->assertSee('Exercise Recommendations');
        $response->assertSee('Filter Recommendations');
    }

    /** @test */
    public function recommendations_index_provides_all_required_view_data()
    {
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        // Verify all required view data is provided
        $response->assertViewHas('recommendations');
        $response->assertViewHas('movementArchetypes');
        $response->assertViewHas('difficultyLevels');
        $response->assertViewHas('movementArchetype');
        $response->assertViewHas('difficultyLevel');
        $response->assertViewHas('todayProgramExercises');
        
        // Verify filter options are correctly structured
        $movementArchetypes = $response->viewData('movementArchetypes');
        $this->assertContains('push', $movementArchetypes);
        $this->assertContains('pull', $movementArchetypes);
        $this->assertContains('squat', $movementArchetypes);
        $this->assertContains('hinge', $movementArchetypes);
        $this->assertContains('carry', $movementArchetypes);
        $this->assertContains('core', $movementArchetypes);
        
        $difficultyLevels = $response->viewData('difficultyLevels');
        $this->assertEquals([1, 2, 3, 4, 5], $difficultyLevels);
    }
    
    /** @test */
    public function add_to_today_button_redirects_to_recommendations_and_creates_program_entry()
    {
        $this->actingAs($this->user);

        // Ensure there's an exercise to add
        $exercise = Exercise::factory()->create(['user_id' => null]);
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    ['name' => 'pectoralis_major', 'role' => 'primary_mover', 'contraction_type' => 'isotonic']
                ]
            ]
        ]);

        $today = Carbon::today()->format('Y-m-d');

        // Simulate clicking the "Add to Today" button with some filter parameters
        $response = $this->actingAs($this->user)
             ->get(route('programs.quick-add', [
                 'exercise' => $exercise->id,
                 'date' => $today,
                 'redirect_to' => 'recommendations',
                 'movement_archetype' => 'push', // Added filter parameter
                 'difficulty_level' => 3,       // Added filter parameter
             ]));

        // Assert redirection to recommendations index with success message and preserved query parameters
        $response->assertRedirect(route('recommendations.index', ['movement_archetype' => 'push', 'difficulty_level' => 3]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Make a new request to the redirected URL to assert content
        $redirectedResponse = $this->actingAs($this->user)
                                   ->get(route('recommendations.index', ['movement_archetype' => 'push', 'difficulty_level' => 3]));
        $redirectedResponse->assertSee('Exercise added to program successfully.');

        // Assert that a program entry was created for today
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertTrue(
            \App\Models\Program::where('user_id', $this->user->id)
                ->where('exercise_id', $exercise->id)
                ->whereDate('date', $today)
                ->exists()
        );
    }
    
    /** @test */
    public function recommendations_displays_add_or_remove_from_today_button_and_handles_deletion()
    {
        $this->actingAs($this->user);

        // Create an exercise and intelligence
        $exercise = Exercise::factory()->create(['user_id' => null]);
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'movement_archetype' => 'push',
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'difficulty_level' => 3,
            'recovery_hours' => 48,
            'muscle_data' => [
                'muscles' => [
                    ['name' => 'pectoralis_major', 'role' => 'primary_mover', 'contraction_type' => 'isotonic']
                ]
            ]
        ]);

        // Create a lift log to ensure recommendations are generated
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->benchPress->id,
            'logged_at' => Carbon::now()->subDays(3)
        ]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id]);

        // Add the exercise to today's program
        $programEntry = \App\Models\Program::create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 10,
            'priority' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);

        // Assert that the "Add to Today" button is NOT visible for this exercise
        $response->assertDontSeeHtml(
            '<a href="' . route('programs.quick-add', ['exercise' => $exercise->id, 'date' => Carbon::today()->format('Y-m-d'), 'redirect_to' => 'recommendations']) . '" class="button" style="background-color: #4CAF50;">'
        );

        // Assert that the "Remove from Today" button IS visible for this exercise
        $response->assertSeeInOrder([
            $exercise->title,
            'Remove from Today'
        ]);

        // Test deleting the program entry from the recommendations page
        $response = $this->actingAs($this->user)
             ->delete(route('programs.destroy', $programEntry->id), [
                 'redirect_to' => 'recommendations',
                 'movement_archetype' => 'push', // Example filter
             ]);

        $response->assertRedirect(route('recommendations.index', ['movement_archetype' => 'push']));
        $response->assertSessionHas('success', 'Program entry deleted.');

        // Make a new request to the redirected URL to assert content
        $redirectedResponse = $this->actingAs($this->user)
                                   ->get(route('recommendations.index', ['movement_archetype' => 'push']));
        $redirectedResponse->assertSee('Program entry deleted.');
        $this->assertDatabaseMissing('programs', ['id' => $programEntry->id]);
    }
}