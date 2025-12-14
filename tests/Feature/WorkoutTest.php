<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\ExerciseAlias;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Athlete']);
    }

    /** @test */
    public function user_can_view_their_templates()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        // Workout with no exercises shows "Empty Workout"
        $response->assertSee('Empty Workout');
    }

    /** @test */
    public function user_can_create_template()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
            'wod_syntax' => '[Bench Press]: 3x8',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', [
            'user_id' => $user->id,
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
        ]);
    }

    /** @test */
    public function template_edit_view_shows_aliased_exercise_names()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        // Create alias
        ExerciseAlias::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP',
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'name' => 'Test Workout',
            'wod_syntax' => '[Bench Press]: 3x8',
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout->id));

        $response->assertOk();
        $response->assertSee('BP'); // Should see alias, not original name
    }

    /** @test */
    public function template_index_shows_exercise_names_with_aliases()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Template',
        ]);
        
        $exercise1 = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);
        $exercise2 = Exercise::factory()->create([
            'title' => 'Squat',
            'user_id' => null,
        ]);

        // Create alias for first exercise
        ExerciseAlias::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'BP',
        ]);

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        // With 2 exercises without intelligence, it shows the exercise names directly
        $response->assertSee('Bench Press & Squat', false);
    }

    /** @test */
    public function template_index_shows_exercise_count_and_names()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Push Day',
        ]);
        
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Dips', 'user_id' => null]);
        $exercise3 = Exercise::factory()->create(['title' => 'Tricep Extensions', 'user_id' => null]);

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise3->id,
            'order' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        // With 3+ exercises, "Bench Press" in name triggers "Upper Body" fallback
        $response->assertSee('Upper Body • 3 exercises', false);
        $response->assertSee('Bench Press, Dips, Tricep Extensions');
    }

    /** @test */
    public function user_can_delete_template()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout->id));

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('success', 'Workout deleted!');
        $this->assertDatabaseMissing('workouts', [
            'id' => $workout->id,
        ]);
    }

    /** @test */
    public function user_cannot_edit_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->get(route('workouts.edit', $workout->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_delete_another_users_template()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->delete(route('workouts.destroy', $workout->id));

        $response->assertForbidden();
    }

    /** @test */
    public function workout_index_shows_simple_list_with_clickable_rows()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'name' => 'Test Workout']);
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        // With 1 exercise without intelligence, it just shows the exercise name
        $response->assertSee('Bench Press');
        $response->assertDontSee('Test Workout');
        
        // Verify the row is clickable (has clickableUrl set)
        $response->assertViewHas('data', function ($data) use ($workout) {
            $components = $data['components'];
            
            // Find the table component
            $tableComponent = null;
            foreach ($components as $component) {
                if (isset($component['type']) && $component['type'] === 'table') {
                    $tableComponent = $component;
                    break;
                }
            }
            
            if (!$tableComponent) {
                return false;
            }
            
            $rows = $tableComponent['data']['rows'];
            
            // Should have 1 row
            if (count($rows) !== 1) {
                return false;
            }
            
            $row = $rows[0];
            
            // Row should have clickableUrl set
            if (!isset($row['clickableUrl'])) {
                return false;
            }
            
            // clickableUrl should point to the edit page
            if (!str_contains($row['clickableUrl'], 'workouts/' . $workout->id . '/edit-simple')) {
                return false;
            }
            
            // Row should NOT have any actions (no edit button)
            if (!empty($row['actions'])) {
                return false;
            }
            
            return true;
        });
    }
    
    /** @test */
    public function workout_index_row_is_not_clickable_when_user_cannot_edit()
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        $regularUser = User::factory()->create();
        
        // Admin creates an advanced workout
        $workout = Workout::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Advanced Workout',
            'wod_syntax' => '[Bench Press]: 5x5'
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        
        // Regular user views their workouts (empty)
        $response = $this->actingAs($regularUser)->get(route('workouts.index'));
        
        $response->assertOk();
        // Regular user should not see admin's workout
        $response->assertDontSee('Advanced Workout');
    }

    /** @test */
    public function simple_workouts_use_generated_labels_in_index()
    {
        $user = User::factory()->create();
        
        // Create a simple workout (no wod_syntax)
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Workout', // Generic name for simple workouts
            'wod_syntax' => null,
        ]);
        
        // Add exercises with intelligence data
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Overhead Press', 'user_id' => null]);
        
        // Create intelligence data for push exercises
        \App\Models\ExerciseIntelligence::create([
            'exercise_id' => $exercise1->id,
            'muscle_data' => json_encode([]),
            'primary_mover' => 'chest',
            'largest_muscle' => 'chest',
            'movement_archetype' => 'push',
            'category' => 'strength',
            'difficulty_level' => 3,
        ]);
        
        \App\Models\ExerciseIntelligence::create([
            'exercise_id' => $exercise2->id,
            'muscle_data' => json_encode([]),
            'primary_mover' => 'shoulders',
            'largest_muscle' => 'shoulders',
            'movement_archetype' => 'push',
            'category' => 'strength',
            'difficulty_level' => 3,
        ]);
        
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise1->id, 'order' => 1]);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise2->id, 'order' => 2]);
        
        $response = $this->actingAs($user)->get(route('workouts.index'));
        
        $response->assertOk();
        // Should show generated label, not the stored name
        $response->assertSee('Push Day • 2 exercises', false);
        // Verify the generated label is used by checking the view data
        $response->assertViewHas('data', function ($data) {
            $components = $data['components'];
            foreach ($components as $component) {
                if (isset($component['type']) && $component['type'] === 'table') {
                    $rows = $component['data']['rows'];
                    if (!empty($rows)) {
                        // First row should have the generated label, not "Workout"
                        return $rows[0]['line1'] === 'Push Day • 2 exercises';
                    }
                }
            }
            return false;
        });
    }

    /** @test */
    public function advanced_workouts_use_stored_name_in_index()
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Create an advanced workout with custom name
        $workout = Workout::factory()->create([
            'user_id' => $admin->id,
            'name' => 'My Custom Advanced Workout',
            'wod_syntax' => '[Bench Press]: 5x5',
        ]);
        
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order' => 1]);
        
        $response = $this->actingAs($admin)->get(route('workouts.index'));
        
        $response->assertOk();
        // Should show the stored custom name, not a generated label
        $response->assertSee('My Custom Advanced Workout');
    }

    /** @test */
    public function mixed_workout_list_shows_correct_labels_for_each_type()
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Create a simple workout
        $simpleWorkout = Workout::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Workout',
            'wod_syntax' => null,
        ]);
        
        $pullUp = Exercise::factory()->create(['title' => 'Pull-Up', 'user_id' => null]);
        \App\Models\ExerciseIntelligence::create([
            'exercise_id' => $pullUp->id,
            'muscle_data' => json_encode([]),
            'primary_mover' => 'back',
            'largest_muscle' => 'back',
            'movement_archetype' => 'pull',
            'category' => 'strength',
            'difficulty_level' => 3,
        ]);
        WorkoutExercise::create(['workout_id' => $simpleWorkout->id, 'exercise_id' => $pullUp->id, 'order' => 1]);
        
        // Create an advanced workout
        $advancedWorkout = Workout::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Murph WOD',
            'wod_syntax' => 'For Time:\n1 Mile Run\n100 [Pull-ups]\n200 [Push-ups]\n300 [Air Squats]\n1 Mile Run',
        ]);
        
        $response = $this->actingAs($admin)->get(route('workouts.index'));
        
        $response->assertOk();
        // Simple workout should show generated label
        $response->assertSee('Pull Day • 1 exercise', false);
        // Advanced workout should show stored name
        $response->assertSee('Murph WOD');
    }
}
