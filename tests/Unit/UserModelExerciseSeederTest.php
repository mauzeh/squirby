<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelExerciseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_model_does_not_create_exercises_on_user_creation()
    {
        // Verify no exercises exist initially
        $this->assertEquals(0, Exercise::count());

        // Create a new user
        $user = User::factory()->create();

        // Verify no exercises were created
        $this->assertEquals(0, Exercise::count());
        
        // Verify the user has no exercises
        $this->assertEquals(0, $user->exercises()->count());
    }

    /** @test */
    public function user_factory_does_not_create_exercises()
    {
        // Create multiple users
        $users = User::factory()->count(3)->create();

        // Verify no exercises were created for any user
        $this->assertEquals(0, Exercise::count());
        
        foreach ($users as $user) {
            $this->assertEquals(0, $user->exercises()->count());
        }
    }
}