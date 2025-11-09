<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use Carbon\Carbon;

class ExerciseSelectionAliasDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_lift_log_index_displays_exercise_aliases_in_top_buttons()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        // Create lift logs to make it a top exercise
        LiftLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);

        $response = $this->actingAs($user)
            ->get(route('lift-logs.index'));

        $response->assertStatus(200)
            ->assertSee('BP');
    }

    public function test_program_create_form_displays_exercise_aliases_in_dropdown()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);

        $response = $this->actingAs($user)
            ->get(route('programs.create'));

        $response->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press');
    }

    public function test_top_exercises_buttons_display_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        // Create lift logs to make it a top exercise
        LiftLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);

        $response = $this->actingAs($user)
            ->get(route('lift-logs.index'));

        $response->assertStatus(200)
            ->assertSee('BP');
    }

    public function test_mobile_entry_exercise_list_displays_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);

        $response = $this->actingAs($user)
            ->get(route('mobile-entry.lifts'));

        $response->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press');
    }

    public function test_different_users_see_their_own_aliases_in_program_forms()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        
        // User 1 has alias "BP"
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);

        // User 2 has alias "Bench"
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Bench'
        ]);

        // User 1 sees "BP" in program create form
        $response1 = $this->actingAs($user1)
            ->get(route('programs.create'));
        $response1->assertStatus(200)
            ->assertSee('BP')
            ->assertDontSee('Bench Press')
            ->assertDontSee('Bench');

        // User 2 sees "Bench" in program create form
        $response2 = $this->actingAs($user2)
            ->get(route('programs.create'));
        $response2->assertStatus(200)
            ->assertSee('Bench')
            ->assertDontSee('Bench Press')
            ->assertDontSee('BP');
    }
}
