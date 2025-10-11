<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseUserBadgeTest extends TestCase
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
        $this->user = User::factory()->create(['name' => 'John Doe']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function exercise_index_shows_global_badge_for_global_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Bench Press',
            'description' => 'Global exercise for bench press',
            'is_bodyweight' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Bench Press');
        $response->assertSee('Global'); // Should see the Global badge
    }

    /** @test */
    public function exercise_index_shows_user_name_badge_for_user_exercises()
    {
        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Squat',
            'description' => 'User-specific squat exercise',
            'is_bodyweight' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Personal Squat');
        $response->assertSee('You'); // Should see 'You' for the user's own exercise
    }

    /** @test */
    public function exercise_index_shows_different_user_names_for_different_exercises()
    {
        // Create exercises for different users
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Exercise',
            'description' => 'Global exercise',
            'is_bodyweight' => false,
        ]);

        $adminExercise = Exercise::create([
            'user_id' => $this->admin->id,
            'title' => 'Admin Exercise',
            'description' => 'Admin exercise',
            'is_bodyweight' => false,
        ]);

        // Admin viewing should see both global and their own exercises with appropriate badges
        $response = $this->actingAs($this->admin)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        
        // Should see global exercise with Everyone badge
        $response->assertSee('Global Exercise');
        $response->assertSee('Everyone');
        
        // Should see admin exercise with 'You' badge
        $response->assertSee('Admin Exercise');
        $response->assertSee('You');
    }

    /** @test */
    public function exercise_badges_have_correct_styling()
    {
        // Create both types of exercises
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

        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        
        // Check for Everyone badge with green background
        $response->assertSee('background-color: #4CAF50');
        $response->assertSee('Everyone');
        
        // Check for user badge with yellow background
        $response->assertSee('background-color: #FFC107');
        $response->assertSee('You');
    }
}