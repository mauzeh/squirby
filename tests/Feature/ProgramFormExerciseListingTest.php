<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProgramFormExerciseListingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $adminRole = Role::create(['name' => 'Admin']);
        
        // Create users
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function program_create_form_shows_global_exercises_for_regular_user()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Bench Press',
            'description' => 'Global exercise for bench press',
            'is_bodyweight' => false,
        ]);

        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Squat',
            'description' => 'User-specific squat exercise',
            'is_bodyweight' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('programs.create'));

        $response->assertStatus(200);
        
        // Check that both global and user exercises are available in the form
        $response->assertSee('Global Bench Press');
        $response->assertSee('User Squat');
    }

    /** @test */
    public function program_edit_form_shows_global_exercises_for_regular_user()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Deadlift',
            'description' => 'Global exercise for deadlift',
            'is_bodyweight' => false,
        ]);

        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Press',
            'description' => 'User-specific press exercise',
            'is_bodyweight' => false,
        ]);

        // Create a program entry
        $program = \App\Models\Program::create([
            'user_id' => $this->user->id,
            'exercise_id' => $userExercise->id,
            'date' => now(),
            'sets' => 3,
            'reps' => 10,
            'priority' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('programs.edit', $program));

        $response->assertStatus(200);
        
        // Check that both global and user exercises are available in the form
        $response->assertSee('Global Deadlift');
        $response->assertSee('User Press');
    }

    /** @test */
    public function lift_log_index_shows_global_exercises_for_regular_user()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Pull-ups',
            'description' => 'Global exercise for pull-ups',
            'is_bodyweight' => true,
        ]);

        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Dips',
            'description' => 'User-specific dips exercise',
            'is_bodyweight' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('lift-logs.index'));

        $response->assertStatus(200);
        
        // Check that both global and user exercises are available
        $response->assertSee('Global Pull-ups');
        $response->assertSee('User Dips');
    }

    /** @test */
    public function lift_log_mobile_entry_shows_global_exercises_for_regular_user()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Rows',
            'description' => 'Global exercise for rows',
            'is_bodyweight' => false,
        ]);

        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Curls',
            'description' => 'User-specific curls exercise',
            'is_bodyweight' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Check that both global and user exercises are available
        $response->assertSee('Global Rows');
        $response->assertSee('User Curls');
    }

    /** @test */
    public function user_cannot_see_other_users_exercises_in_program_form()
    {
        $otherUser = User::factory()->create();
        
        // Create exercises for different users
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Exercise',
            'description' => 'Global exercise',
            'is_bodyweight' => false,
        ]);

        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Exercise',
            'description' => 'User exercise',
            'is_bodyweight' => false,
        ]);

        $otherUserExercise = Exercise::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Exercise',
            'description' => 'Other user exercise',
            'is_bodyweight' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('programs.create'));

        $response->assertStatus(200);
        
        // Check that user can see global and their own exercises
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        
        // Check that user cannot see other user's exercises
        $response->assertDontSee('Other User Exercise');
    }
}