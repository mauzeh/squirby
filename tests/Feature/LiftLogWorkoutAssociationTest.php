<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\LiftLog;

class LiftLogWorkoutAssociationTest extends TestCase
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
    public function lift_log_stores_workout_id_when_logged_through_simple_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Create a simple workout with the exercise
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        // Log the exercise through the workout
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test lift',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
            'workout_id' => $workout->id,
            'redirect_to' => 'simple-workout',
        ]);

        // Verify the lift log was created with the workout_id
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
        ]);
    }

    /** @test */
    public function lift_log_has_null_workout_id_when_logged_outside_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);

        // Log the exercise without a workout context
        $response = $this->actingAs($user)->post(route('lift-logs.store'), [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test lift',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
            'redirect_to' => 'mobile-entry-lifts',
        ]);

        // Verify the lift log was created without a workout_id
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_id' => null,
        ]);
    }

    /** @test */
    public function lift_log_can_access_workout_relationship()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Workout',
            'wod_syntax' => null,
        ]);
        
        $liftLog = LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'logged_at' => now(),
        ]);

        // Test the relationship
        $this->assertNotNull($liftLog->workout);
        $this->assertEquals('Test Workout', $liftLog->workout->name);
        $this->assertEquals($workout->id, $liftLog->workout->id);
    }

    /** @test */
    public function workout_id_is_set_to_null_when_workout_is_deleted()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        $liftLog = LiftLog::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'logged_at' => now(),
        ]);

        // Delete the workout
        $workout->delete();

        // Verify the lift log still exists but workout_id is null
        $liftLog->refresh();
        $this->assertNull($liftLog->workout_id);
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'workout_id' => null,
        ]);
    }

    /** @test */
    public function simple_workout_play_button_includes_workout_id_in_url()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'wod_syntax' => null,
        ]);
        
        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit-simple', $workout->id));

        $response->assertOk();
        
        // Verify the play button URL includes workout_id
        $response->assertViewHas('data', function ($data) use ($workout, $exercise) {
            $components = $data['components'];
            
            // Find the table component
            foreach ($components as $component) {
                if (isset($component['type']) && $component['type'] === 'table') {
                    $rows = $component['data']['rows'];
                    $row = $rows[0];
                    
                    // Find the play button action
                    foreach ($row['actions'] as $action) {
                        if ($action['icon'] === 'fa-play') {
                            // Check that the URL contains workout_id parameter
                            return strpos($action['url'], 'workout_id=' . $workout->id) !== false;
                        }
                    }
                }
            }
            
            return false;
        });
    }
}
