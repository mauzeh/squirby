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

class SimpleWorkoutTest extends TestCase
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
    public function user_can_create_simple_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);

        // New flow: GET request shows form, no workout created yet
        $response = $this->actingAs($user)->get(route('workouts.create-simple'));

        $response->assertOk();
        $response->assertSee('Select exercises below to add them to your workout.');
        
        // Verify exercise list is configured to be expanded by default
        $response->assertViewHas('data', function ($data) {
            $components = $data['components'];
            foreach ($components as $component) {
                if (isset($component['type']) && $component['type'] === 'item-list') {
                    return isset($component['data']['initialState']) && 
                           $component['data']['initialState'] === 'expanded';
                }
            }
            return false;
        });
        
        // Verify NO workout was created yet
        $this->assertDatabaseMissing('workouts', [
            'user_id' => $user->id,
        ]);
        
        // Add first exercise - this creates the workout with default name
        $response = $this->actingAs($user)->get(route('simple-workouts.add-exercise-new', [
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect();
        
        // Verify a workout was created (name is generic for simple workouts)
        $workout = Workout::where('user_id', $user->id)->first();
        $this->assertNotNull($workout);
        $this->assertEquals('Workout', $workout->name); // Simple workouts use generic name
        $this->assertNull($workout->wod_syntax); // Simple workouts have null wod_syntax
        
        // Verify exercise was added
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_can_create_simple_workout_via_legacy_post()
    {
        $user = User::factory()->create();

        // Legacy flow still works for backwards compatibility
        $response = $this->actingAs($user)->post(route('workouts.store-simple'), [
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', [
            'user_id' => $user->id,
            'name' => 'Push Day',
            'description' => 'Upper body pushing exercises',
            'wod_syntax' => null, // Simple workouts have null wod_syntax
        ]);
    }

    /** @test */
    public function user_can_create_workout_by_creating_new_exercise()
    {
        $user = User::factory()->create();

        // New flow: GET request shows form, no workout created yet
        $response = $this->actingAs($user)->get(route('workouts.create-simple'));

        $response->assertOk();
        
        // Verify NO workout was created yet
        $this->assertDatabaseMissing('workouts', [
            'user_id' => $user->id,
        ]);
        
        // Create new exercise - this creates the workout with default name
        $response = $this->actingAs($user)->post(route('simple-workouts.create-exercise-new'), [
            'exercise_name' => 'Brand New Exercise',
        ]);

        $response->assertRedirect();
        
        // Verify workout was created with date-based fallback name (since new exercise has no intelligence)
        $workout = Workout::where('user_id', $user->id)->first();
        $this->assertNotNull($workout);
        $this->assertStringStartsWith('New Workout - ', $workout->name);
        $this->assertNull($workout->wod_syntax);
        
        // Verify exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'Brand New Exercise',
        ]);
        
        // Verify exercise was added to workout
        $exercise = Exercise::where('title', 'Brand New Exercise')->first();
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_can_add_exercise_to_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->get(route('simple-workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Exercise added!');
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_cannot_add_duplicate_exercise_to_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        // Add exercise first time
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Try to add same exercise again
        $response = $this->actingAs($user)->get(route('simple-workouts.add-exercise', [
            'workout' => $workout->id,
            'exercise' => $exercise->id,
        ]));

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('warning', 'Exercise already in workout.');
    }

    /** @test */
    public function user_can_create_new_exercise_and_add_to_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);

        $response = $this->actingAs($user)->post(route('simple-workouts.create-exercise', $workout->id), [
            'exercise_name' => 'New Custom Exercise',
        ]);

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Exercise created and added!');
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Custom Exercise',
        ]);
        
        $exercise = Exercise::where('title', 'New Custom Exercise')->first();
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    /** @test */
    public function user_can_move_exercise_up_in_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);
        
        $we1 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        
        $we2 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        // Move second exercise up
        $response = $this->actingAs($user)->get(route('simple-workouts.move-exercise', [
            'workout' => $workout->id,
            'exercise' => $we2->id,
            'direction' => 'up',
        ]));

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Exercise order updated!');
        
        // Check that orders were swapped
        $this->assertEquals(2, $we1->fresh()->order);
        $this->assertEquals(1, $we2->fresh()->order);
    }

    /** @test */
    public function user_can_move_exercise_down_in_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise1 = Exercise::factory()->create(['user_id' => null]);
        $exercise2 = Exercise::factory()->create(['user_id' => null]);
        
        $we1 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        
        $we2 = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);

        // Move first exercise down
        $response = $this->actingAs($user)->get(route('simple-workouts.move-exercise', [
            'workout' => $workout->id,
            'exercise' => $we1->id,
            'direction' => 'down',
        ]));

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Exercise order updated!');
        
        // Check that orders were swapped
        $this->assertEquals(2, $we1->fresh()->order);
        $this->assertEquals(1, $we2->fresh()->order);
    }

    /** @test */
    public function user_can_remove_exercise_from_simple_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $we = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('simple-workouts.remove-exercise', [
            'workout' => $workout->id,
            'exercise' => $we->id,
        ]));

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Exercise removed!');
        $this->assertDatabaseMissing('workout_exercises', [
            'id' => $we->id,
        ]);
    }

    /** @test */
    public function user_can_update_simple_workout_details()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old Name',
            'wod_syntax' => null,
        ]);

        $response = $this->actingAs($user)->put(route('workouts.update-simple', $workout->id), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
        $response->assertSessionHas('success', 'Workout updated!');
        
        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'name' => 'New Name',
            'wod_syntax' => null, // Should remain null
        ]);
    }

    /** @test */
    public function simple_workout_edit_shows_aliased_exercise_names()
    {
        $user = User::factory()->create();
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
            'wod_syntax' => null,
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        $response->assertSee('BP'); // Should see alias, not original name
    }

    /** @test */
    public function regular_user_cannot_edit_advanced_workout()
    {
        $user = User::factory()->create();
        $athleteRole = Role::where('name', 'Athlete')->first();
        $user->roles()->attach($athleteRole);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => '[[Bench Press]]: 3x8', // Advanced workout
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertRedirect(route('workouts.index'));
        $response->assertSessionHas('error', 'This workout uses advanced syntax and can only be edited by admins.');
    }

    /** @test */
    public function admin_can_edit_advanced_workout_via_simple_route()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => '[[Bench Press]]: 3x8', // Advanced workout
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        // Admin should be redirected to advanced editor
        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('info', 'This workout uses advanced syntax.');
    }

    /** @test */
    public function impersonator_can_edit_advanced_workout()
    {
        $user = User::factory()->create();
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => '[[Bench Press]]: 3x8', // Advanced workout
        ]);

        // Simulate impersonation
        session(['impersonator_id' => 999]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        // Impersonator should be redirected to advanced editor
        $response->assertRedirect(route('workouts.edit', $workout->id));
        $response->assertSessionHas('info', 'This workout uses advanced syntax.');
    }

    /** @test */
    public function user_cannot_edit_another_users_simple_workout()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user1->id,
            'wod_syntax' => null,
        ]);

        $response = $this->actingAs($user2)->get(route('workouts.edit-simple', $workout->id));

        $response->assertForbidden();
    }

    /** @test */
    public function workout_edit_table_uses_compact_mode_and_single_arrow_buttons()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Exercise 2']);
        $exercise3 = Exercise::factory()->create(['user_id' => null, 'title' => 'Exercise 3']);
        
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

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify table component structure
        $response->assertViewHas('data', function ($data) {
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
            
            // Should have 3 rows
            if (count($rows) !== 3) {
                return false;
            }
            
            // Check first row (not last): should have compact mode, play button, down arrow, and delete
            $firstRow = $rows[0];
            if (!isset($firstRow['compact']) || $firstRow['compact'] !== true) {
                return false;
            }
            if (count($firstRow['actions']) !== 3) { // play + down arrow + delete
                return false;
            }
            // First action should be play button
            if ($firstRow['actions'][0]['icon'] !== 'fa-play') {
                return false;
            }
            // Second action should be down arrow
            if ($firstRow['actions'][1]['icon'] !== 'fa-arrow-down') {
                return false;
            }
            // Verify redirect_to parameter is 'simple-workout'
            if (strpos($firstRow['actions'][0]['url'], 'redirect_to=simple-workout') === false) {
                return false;
            }
            
            // Check middle row (not last): should have compact mode, play button, down arrow, and delete
            $middleRow = $rows[1];
            if (!isset($middleRow['compact']) || $middleRow['compact'] !== true) {
                return false;
            }
            if (count($middleRow['actions']) !== 3) { // play + down arrow + delete
                return false;
            }
            // First action should be play button
            if ($middleRow['actions'][0]['icon'] !== 'fa-play') {
                return false;
            }
            // Second action should be down arrow
            if ($middleRow['actions'][1]['icon'] !== 'fa-arrow-down') {
                return false;
            }
            
            // Check last row: should have compact mode, play button, up arrow, and delete
            $lastRow = $rows[2];
            if (!isset($lastRow['compact']) || $lastRow['compact'] !== true) {
                return false;
            }
            if (count($lastRow['actions']) !== 3) { // play + up arrow + delete
                return false;
            }
            // First action should be play button
            if ($lastRow['actions'][0]['icon'] !== 'fa-play') {
                return false;
            }
            // Second action should be up arrow
            if ($lastRow['actions'][1]['icon'] !== 'fa-arrow-up') {
                return false;
            }
            
            return true;
        });
    }

    /** @test */
    public function workout_with_single_exercise_shows_no_arrow_buttons()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Single Exercise']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify table component structure
        $response->assertViewHas('data', function ($data) {
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
            
            // Single row should have compact mode and play + delete buttons (no arrows)
            $row = $rows[0];
            if (!isset($row['compact']) || $row['compact'] !== true) {
                return false;
            }
            if (count($row['actions']) !== 2) { // play + delete
                return false;
            }
            // First action should be play button
            if ($row['actions'][0]['icon'] !== 'fa-play') {
                return false;
            }
            // Second action should be delete (trash icon)
            if ($row['actions'][1]['icon'] !== 'fa-trash') {
                return false;
            }
            
            return true;
        });
    }

    /** @test */
    public function workout_edit_shows_play_button_for_each_exercise()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify play button exists and links to lift-logs/create
        $response->assertViewHas('data', function ($data) use ($exercise, $workout) {
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
            $row = $rows[0];
            
            // Check for play button
            $hasPlayButton = false;
            foreach ($row['actions'] as $action) {
                if ($action['icon'] === 'fa-play' && $action['type'] === 'link') {
                    $hasPlayButton = true;
                    // Verify URL contains exercise_id and workout_id
                    if (strpos($action['url'], 'exercise_id=' . $exercise->id) === false) {
                        return false;
                    }
                    if (strpos($action['url'], 'workout_id=' . $workout->id) === false) {
                        return false;
                    }
                }
            }
            
            return $hasPlayButton;
        });
    }

    /** @test */
    public function workout_edit_shows_helpful_message_when_exercise_not_logged()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Squat']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        $response->assertSee('Tap play to begin logging');
    }

    /** @test */
    public function workout_edit_shows_logged_data_when_exercise_logged_today()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Deadlift']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Log the exercise today
        $liftLog = \App\Models\LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => \Carbon\Carbon::now(),
        ]);

        \App\Models\LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 225,
            'reps' => 5,
            'set_number' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        // Should show the logged data instead of "Tap play to begin logging"
        $response->assertDontSee('Tap play to begin logging');
        $response->assertSee('225'); // Should see the weight
    }

    /** @test */
    public function workout_edit_hides_play_button_when_exercise_logged_today()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Squat']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Log the exercise today
        $liftLog = \App\Models\LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => \Carbon\Carbon::now(),
        ]);

        \App\Models\LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 315,
            'reps' => 3,
            'set_number' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify play button is hidden when exercise is logged
        $response->assertViewHas('data', function ($data) {
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
            $row = $rows[0];
            
            // Should only have delete button (no play button since it's logged)
            if (count($row['actions']) !== 1) {
                return false;
            }
            
            // The only action should be delete (trash icon)
            if ($row['actions'][0]['icon'] !== 'fa-trash') {
                return false;
            }
            
            return true;
        });
    }

    /** @test */
    public function workout_edit_shows_green_message_box_for_logged_exercises()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Log the exercise today
        $liftLog = \App\Models\LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => \Carbon\Carbon::now(),
        ]);

        \App\Models\LiftSet::create([
            'lift_log_id' => $liftLog->id,
            'weight' => 185,
            'reps' => 8,
            'set_number' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify green success message is present
        $response->assertViewHas('data', function ($data) {
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
            $row = $rows[0];
            
            // Should have messages array
            if (!isset($row['messages']) || !is_array($row['messages'])) {
                return false;
            }
            
            // Should have at least one message
            if (count($row['messages']) === 0) {
                return false;
            }
            
            $message = $row['messages'][0];
            
            // Message should be type 'success' (green)
            if ($message['type'] !== 'success') {
                return false;
            }
            
            // Message should have 'Completed:' prefix
            if ($message['prefix'] !== 'Completed:') {
                return false;
            }
            
            // Message text should contain the logged data (weight and reps)
            if (strpos($message['text'], '185') === false) {
                return false;
            }
            
            return true;
        });
    }

    /** @test */
    public function logging_exercise_from_simple_workout_redirects_back_to_workout_editor()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Deadlift']);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Submit a lift log with redirect_to=simple-workout
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 405,
            'reps' => 1,
            'rounds' => 1,
            'date' => \Carbon\Carbon::today()->toDateString(),
            'time' => '14:00',
            'redirect_to' => 'simple-workout',
            'workout_id' => $workout->id,
        ]);

        // Should redirect back to the simple workout editor
        $response->assertRedirect(route('workouts.edit-simple', $workout->id));
    }
}
