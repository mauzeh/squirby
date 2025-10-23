<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function should_show_global_exercises_returns_true_when_preference_is_true()
    {
        $user = User::factory()->create(['show_global_exercises' => true]);
        
        $this->assertTrue($user->shouldShowGlobalExercises());
    }

    /** @test */
    public function should_show_global_exercises_returns_false_when_preference_is_false()
    {
        $user = User::factory()->create(['show_global_exercises' => false]);
        
        $this->assertFalse($user->shouldShowGlobalExercises());
    }

    /** @test */
    public function should_show_global_exercises_handles_null_gracefully()
    {
        $user = User::factory()->create();
        
        // Simulate null value by unsetting the attribute after creation
        $user->show_global_exercises = null;
        
        $this->assertTrue($user->shouldShowGlobalExercises());
    }

    /** @test */
    public function should_show_global_exercises_returns_true_for_new_user_with_default()
    {
        $user = User::factory()->create();
        
        // Default should be true based on migration
        $this->assertTrue($user->shouldShowGlobalExercises());
    }
}