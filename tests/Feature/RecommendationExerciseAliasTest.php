<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\ExerciseAlias;

class RecommendationExerciseAliasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        // Create an exercise with intelligence data
        $this->exercise = Exercise::factory()->create([
            'title' => 'Barbell Bench Press',
            'user_id' => null, // Global exercise
        ]);

        ExerciseIntelligence::factory()->create([
            'exercise_id' => $this->exercise->id,
            'movement_archetype' => 'push',
            'difficulty_level' => 3,
            'primary_mover' => 'chest',
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
    }

    /** @test */
    public function recommendations_display_exercise_alias_when_user_has_one()
    {
        // Create a lift log so the recommendation engine has data
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => \Carbon\Carbon::now()->subDays(10),
        ]);

        // Create an alias for the exercise
        ExerciseAlias::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'My Custom Bench Press'
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        // Should see the alias, not the original title
        $response->assertSee('My Custom Bench Press');
        $response->assertDontSee('Barbell Bench Press');
    }

    /** @test */
    public function recommendations_display_original_title_when_no_alias_exists()
    {
        // Create a lift log so the recommendation engine has data
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => \Carbon\Carbon::now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('recommendations.index'));

        $response->assertStatus(200);
        
        // Should see the original title
        $response->assertSee('Barbell Bench Press');
    }

    /** @test */
    public function recommendations_display_different_aliases_for_different_users()
    {
        $user2 = User::factory()->create();

        // Create lift logs for both users
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => \Carbon\Carbon::now()->subDays(10),
        ]);

        \App\Models\LiftLog::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => \Carbon\Carbon::now()->subDays(10),
        ]);

        // Create different aliases for each user
        ExerciseAlias::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'User 1 Bench Press'
        ]);

        ExerciseAlias::create([
            'user_id' => $user2->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'User 2 Bench Press'
        ]);

        // User 1 should see their alias
        $response1 = $this->actingAs($this->user)
            ->get(route('recommendations.index'));
        $response1->assertSee('User 1 Bench Press');
        $response1->assertDontSee('User 2 Bench Press');

        // User 2 should see their alias
        $response2 = $this->actingAs($user2)
            ->get(route('recommendations.index'));
        $response2->assertSee('User 2 Bench Press');
        $response2->assertDontSee('User 1 Bench Press');
    }
}
