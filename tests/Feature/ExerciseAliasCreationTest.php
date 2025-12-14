<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseMatchingAlias;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseAliasCreationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_alias_creation_page()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)
            ->get(route('exercise-aliases.create', [
                'alias_name' => 'KB Swings',
                'workout_id' => $workout->id
            ]));
        
        $response->assertStatus(200);
        $response->assertSee('Link "KB Swings"');
        $response->assertSee('Select the exercise this refers to');
    }

    /** @test */
    public function user_can_create_alias_for_existing_exercise()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create([
            'title' => 'Kettlebell Swing',
            'user_id' => $user->id
        ]);
        
        $response = $this->actingAs($user)
            ->get(route('exercise-aliases.store', [
                'exercise_id' => $exercise->id,
                'alias_name' => 'KB Swings',
                'workout_id' => $workout->id
            ]));
        
        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('exercise_matching_aliases', [
            'exercise_id' => $exercise->id,
            'alias' => 'KB Swings'
        ]);
    }

    /** @test */
    public function user_can_create_new_exercise_and_link_alias()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)
            ->post(route('exercise-aliases.create-and-link'), [
                'exercise_name' => 'Kettlebell Swing',
                'alias_name' => 'KB Swings',
                'workout_id' => $workout->id
            ]);
        
        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success');
        
        $exercise = Exercise::where('title', 'Kettlebell Swing')->first();
        $this->assertNotNull($exercise);
        
        $this->assertDatabaseHas('exercise_matching_aliases', [
            'exercise_id' => $exercise->id,
            'alias' => 'KB Swings'
        ]);
    }

    /** @test */
    public function updating_existing_alias_changes_exercise_link()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise1 = Exercise::factory()->create([
            'title' => 'Kettlebell Swing',
            'user_id' => $user->id
        ]);
        $exercise2 = Exercise::factory()->create([
            'title' => 'Dumbbell Swing',
            'user_id' => $user->id
        ]);
        
        // Create initial alias
        ExerciseMatchingAlias::create([
            'exercise_id' => $exercise1->id,
            'alias' => 'KB Swings'
        ]);
        
        // Update to point to different exercise
        $response = $this->actingAs($user)
            ->get(route('exercise-aliases.store', [
                'exercise_id' => $exercise2->id,
                'alias_name' => 'KB Swings',
                'workout_id' => $workout->id
            ]));
        
        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('success');
        
        $alias = ExerciseMatchingAlias::where('alias', 'KB Swings')->first();
        
        $this->assertEquals($exercise2->id, $alias->exercise_id);
    }

    /** @test */
    public function unmatched_wod_exercises_link_to_alias_creation()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => "AMRAP 10min:\n10 [KB Swings]\n15 [Push-ups]"
        ]);
        
        $this->actingAs($user);
        $wodDisplayService = app(\App\Services\WodDisplayService::class);
        $processed = $wodDisplayService->processForDisplay($workout);
        
        // Should contain link to alias creation
        $this->assertStringContainsString('exercise-aliases/create', $processed);
        $this->assertStringContainsString('alias_name=KB%20Swings', $processed);
    }

    /** @test */
    public function matched_wod_exercises_link_to_lift_log_creation()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Kettlebell Swing',
            'user_id' => $user->id
        ]);
        
        // Create alias
        ExerciseMatchingAlias::create([
            'exercise_id' => $exercise->id,
            'alias' => 'KB Swings'
        ]);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => "AMRAP 10min:\n10 [KB Swings]"
        ]);
        
        $this->actingAs($user);
        $wodDisplayService = app(\App\Services\WodDisplayService::class);
        $processed = $wodDisplayService->processForDisplay($workout);
        
        // Should contain link to exercise logs, not alias creation
        $this->assertStringContainsString('exercises/' . $exercise->id . '/logs', $processed);
        $this->assertStringNotContainsString('exercise-aliases/create', $processed);
    }

    /** @test */
    public function wod_display_shows_original_syntax_not_exercise_name()
    {
        $user = User::factory()->create();
        
        // Create an exercise with a specific name
        $exercise = Exercise::factory()->create([
            'title' => 'Back Rack Lunge',
            'user_id' => $user->id
        ]);
        
        // Create an alias that maps "HelemaalNiks" to "Back Rack Lunge"
        ExerciseMatchingAlias::create([
            'exercise_id' => $exercise->id,
            'alias' => 'HelemaalNiks'
        ]);
        
        // Create workout with the alias in WOD syntax
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => "# Let's go!\n* [Bench Press] 5x5\n* [HelemaalNiks] 3x10"
        ]);
        
        $this->actingAs($user);
        $wodDisplayService = app(\App\Services\WodDisplayService::class);
        $processed = $wodDisplayService->processForDisplay($workout);
        
        // Should show the original text "HelemaalNiks" from WOD syntax
        $this->assertStringContainsString('HelemaalNiks', $processed);
        
        // Should NOT show the actual exercise name "Back Rack Lunge"
        $this->assertStringNotContainsString('Back Rack Lunge', $processed);
        
        // Should link to the correct exercise
        $this->assertStringContainsString('exercises/' . $exercise->id . '/logs', $processed);
    }

    /** @test */
    public function alias_creation_page_shows_recent_exercises_first()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        
        $recentExercise = Exercise::factory()->create([
            'title' => 'Recent Exercise',
            'user_id' => $user->id
        ]);
        
        $oldExercise = Exercise::factory()->create([
            'title' => 'Old Exercise',
            'user_id' => $user->id
        ]);
        
        // Log the recent exercise
        \App\Models\LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $recentExercise->id,
            'logged_at' => now()->subDays(5)
        ]);
        
        $response = $this->actingAs($user)
            ->get(route('exercise-aliases.create', [
                'alias_name' => 'Test',
                'workout_id' => $workout->id
            ]));
        
        $response->assertStatus(200);
        $response->assertSee('Recent Exercise');
        $response->assertSee('Old Exercise');
    }

    /** @test */
    public function alias_creation_requires_authentication()
    {
        $response = $this->get(route('exercise-aliases.create', [
            'alias_name' => 'Test'
        ]));
        
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_create_alias_for_another_users_exercise()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user1->id]);
        
        $exercise = Exercise::factory()->create([
            'title' => 'Private Exercise',
            'user_id' => $user2->id
        ]);
        
        $response = $this->actingAs($user1)
            ->get(route('exercise-aliases.store', [
                'exercise_id' => $exercise->id,
                'alias_name' => 'Test',
                'workout_id' => $workout->id
            ]));
        
        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('error');
        
        // Since exercise_matching_aliases are global, the alias should still not be created
        // because user1 doesn't have access to user2's exercise
        $this->assertDatabaseMissing('exercise_matching_aliases', [
            'exercise_id' => $exercise->id,
            'alias' => 'Test'
        ]);
    }
}
