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
    public function recommendations_index_shows_filter_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee('Filter Recommendations');
        $response->assertSee('Movement Pattern:');
        $response->assertSee('Difficulty Level:');
        $response->assertSee('Number of Recommendations:');
        
        // Check filter options are present
        $response->assertSee('All Patterns');
        $response->assertSee('Push');
        $response->assertSee('Pull');
        $response->assertSee('Squat');
        $response->assertSee('All Levels');
        $response->assertSee('Level 1');
        $response->assertSee('Level 5');
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
        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index', [
                'movement_archetype' => 'invalid_archetype',
                'difficulty_level' => 10,
                'count' => 50
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['movement_archetype', 'difficulty_level', 'count']);
    }

    /** @test */
    public function recommendations_api_returns_json_response()
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
            ->getJson(route('recommendations.api'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'recommendations' => [
                '*' => [
                    'exercise' => [
                        'id',
                        'title',
                        'description',
                        'is_bodyweight',
                        'band_type'
                    ],
                    'intelligence' => [
                        'movement_archetype',
                        'category',
                        'difficulty_level',
                        'primary_mover',
                        'largest_muscle',
                        'recovery_hours',
                        'muscle_data'
                    ],
                    'score',
                    'reasoning'
                ]
            ],
            'count',
            'filters'
        ]);
        
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function recommendations_api_applies_filters()
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
            ->getJson(route('recommendations.api', [
                'movement_archetype' => 'pull',
                'difficulty_level' => 4
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'filters' => [
                'movement_archetype' => 'pull',
                'difficulty_level' => 4
            ]
        ]);

        $recommendations = $response->json('recommendations');
        foreach ($recommendations as $recommendation) {
            $this->assertEquals('pull', $recommendation['intelligence']['movement_archetype']);
            $this->assertEquals(4, $recommendation['intelligence']['difficulty_level']);
        }
    }

    /** @test */
    public function recommendations_api_validates_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('recommendations.api', [
                'movement_archetype' => 'invalid',
                'difficulty_level' => 0,
                'count' => 25
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movement_archetype', 'difficulty_level', 'count']);
    }

    /** @test */
    public function recommendations_api_handles_errors_gracefully()
    {
        // Force an error by deleting all exercises
        Exercise::query()->delete();
        ExerciseIntelligence::query()->delete();

        $response = $this->actingAs($this->user)
            ->getJson(route('recommendations.api'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'recommendations' => [],
            'count' => 0
        ]);
    }

    /** @test */
    public function get_filters_api_returns_available_options()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.recommendations.filters'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'movement_archetypes',
            'difficulty_levels',
            'categories'
        ]);

        $response->assertJsonFragment([
            'push' => 'Pushing movements (bench press, overhead press, push-ups)',
            'pull' => 'Pulling movements (rows, pull-ups, deadlifts)',
            'squat' => 'Knee-dominant lower body movements (squats, lunges)',
            'hinge' => 'Hip-dominant movements (deadlifts, hip thrusts, good mornings)',
            'carry' => 'Loaded carries and holds (farmer\'s walks, suitcase carries)',
            'core' => 'Core-specific movements (planks, crunches, Russian twists)'
        ]);

        $response->assertJsonFragment([
            1 => 'Beginner',
            2 => 'Novice',
            3 => 'Intermediate',
            4 => 'Advanced',
            5 => 'Expert'
        ]);
    }

    /** @test */
    public function recommendations_require_authentication()
    {
        $response = $this->get(route('recommendations.index'));
        $response->assertRedirect(route('login'));

        $response = $this->getJson(route('recommendations.api'));
        $response->assertStatus(401);

        $response = $this->getJson(route('api.recommendations.filters'));
        $response->assertStatus(401);
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
        $response->assertSee('Log This Exercise');
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
            ->getJson(route('recommendations.api'));

        $response->assertStatus(200);
        
        $recommendations = $response->json('recommendations');
        $recommendedExerciseIds = array_column(array_column($recommendations, 'exercise'), 'id');

        // User-specific exercise should NOT be recommended
        $this->assertNotContains($userExercise->id, $recommendedExerciseIds);
        
        // Global exercise without intelligence should NOT be recommended
        $this->assertNotContains($globalExerciseNoIntelligence->id, $recommendedExerciseIds);
        
        // Only global exercises with intelligence should be recommended
        foreach ($recommendations as $recommendation) {
            $exercise = Exercise::find($recommendation['exercise']['id']);
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

        // API should also handle gracefully
        $apiResponse = $this->actingAs($this->user)
            ->getJson(route('recommendations.api'));

        $apiResponse->assertStatus(200);
        $apiResponse->assertJson([
            'success' => true,
            'recommendations' => [],
            'count' => 0
        ]);
    }
}