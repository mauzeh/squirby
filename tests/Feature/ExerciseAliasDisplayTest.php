<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;

class ExerciseAliasDisplayTest extends TestCase
{
    use RefreshDatabase;



    public function test_lift_log_table_shows_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5
        ]);

        $response = $this->actingAs($user)
            ->get(route('lift-logs.index'));

        $response->assertStatus(200)
            ->assertSee('BP');
    }

    public function test_lift_log_charts_use_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5
        ]);

        $response = $this->actingAs($user)
            ->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200)
            ->assertSee('BP');
    }



    public function test_lift_log_index_shows_aliases_for_multiple_exercises()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Back Squat', 'user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'BP'
        ]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'Squat'
        ]);
        
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 100,
            'reps' => 5
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 200,
            'reps' => 5
        ]);

        $response = $this->actingAs($user)
            ->get(route('lift-logs.index'));

        $response->assertStatus(200)
            ->assertSee('BP')
            ->assertSee('Squat');
    }



    public function test_different_users_see_their_own_aliases_in_lift_logs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Bench'
        ]);
        
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 100,
            'reps' => 5
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 100,
            'reps' => 5
        ]);

        // User 1 sees "BP"
        $response1 = $this->actingAs($user1)
            ->get(route('lift-logs.index'));
        $response1->assertStatus(200)
            ->assertSee('BP');

        // User 2 sees "Bench"
        $response2 = $this->actingAs($user2)
            ->get(route('lift-logs.index'));
        $response2->assertStatus(200)
            ->assertSee('Bench');
    }

    public function test_exercise_show_logs_page_displays_alias_in_title()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::now()
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5
        ]);

        $response = $this->actingAs($user)
            ->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200)
            ->assertSee('BP');
    }
}
