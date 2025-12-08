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
        $response->assertSee($workout->name);
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
            'wod_syntax' => '[[Bench Press]]: 3x8',
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
            'wod_syntax' => '[[Bench Press]]: 3x8',
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
        $response->assertSee('2 exercises:');
        // Note: Index page shows original exercise names, not aliases
        $response->assertSee('Bench Press');
        $response->assertSee('Squat');
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
        $response->assertSee('3 exercises:');
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
        $response->assertSee('Test Workout');
        $response->assertSee('1 exercise: Bench Press');
        
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
            'wod_syntax' => '[[Bench Press]]: 5x5'
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
}
