<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test exercise autocomplete returns user's exercises
     */
    public function test_exercise_autocomplete_returns_users_exercises(): void
    {
        $user = User::factory()->create();
        
        // Create exercises for this user
        Exercise::factory()->create(['title' => 'Back Squat', 'user_id' => $user->id]);
        Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => $user->id]);
        Exercise::factory()->create(['title' => 'Deadlift', 'user_id' => $user->id]);
        
        // Create exercise for another user (should not appear)
        $otherUser = User::factory()->create();
        Exercise::factory()->create(['title' => 'Other Exercise', 'user_id' => $otherUser->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJsonCount(3);
        
        $exercises = $response->json();
        $this->assertContains('Back Squat', $exercises);
        $this->assertContains('Bench Press', $exercises);
        $this->assertContains('Deadlift', $exercises);
        $this->assertNotContains('Other Exercise', $exercises);
    }

    /**
     * Test exercise autocomplete includes global exercises
     */
    public function test_exercise_autocomplete_includes_global_exercises(): void
    {
        $user = User::factory()->create();
        
        // Create global exercises
        Exercise::factory()->create(['title' => 'Global Squat', 'user_id' => null]);
        Exercise::factory()->create(['title' => 'Global Press', 'user_id' => null]);
        
        // Create user exercise
        Exercise::factory()->create(['title' => 'User Exercise', 'user_id' => $user->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJsonCount(3);
        
        $exercises = $response->json();
        $this->assertContains('Global Squat', $exercises);
        $this->assertContains('Global Press', $exercises);
        $this->assertContains('User Exercise', $exercises);
    }

    /**
     * Test exercise autocomplete returns alphabetically sorted
     */
    public function test_exercise_autocomplete_returns_sorted_alphabetically(): void
    {
        $user = User::factory()->create();
        
        Exercise::factory()->create(['title' => 'Zebra Exercise', 'user_id' => $user->id]);
        Exercise::factory()->create(['title' => 'Apple Exercise', 'user_id' => $user->id]);
        Exercise::factory()->create(['title' => 'Mango Exercise', 'user_id' => $user->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200);
        
        $exercises = $response->json();
        $this->assertEquals(['Apple Exercise', 'Mango Exercise', 'Zebra Exercise'], $exercises);
    }

    /**
     * Test exercise autocomplete removes duplicates
     */
    public function test_exercise_autocomplete_removes_duplicates(): void
    {
        $user = User::factory()->create();
        
        // Create exercises with same title
        Exercise::factory()->create(['title' => 'Squat', 'user_id' => $user->id]);
        Exercise::factory()->create(['title' => 'Press', 'user_id' => $user->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJsonCount(2);
        
        $exercises = $response->json();
        $this->assertContains('Squat', $exercises);
        $this->assertContains('Press', $exercises);
    }

    /**
     * Test exercise autocomplete requires authentication
     */
    public function test_exercise_autocomplete_requires_authentication(): void
    {
        $response = $this->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(401);
    }

    /**
     * Test exercise autocomplete returns empty array when no exercises
     */
    public function test_exercise_autocomplete_returns_empty_array_when_no_exercises(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJson([]);
    }

    /**
     * Test exercise autocomplete respects user preferences for global exercises
     */
    public function test_exercise_autocomplete_respects_global_exercise_preference(): void
    {
        // User with global exercises disabled
        $user = User::factory()->create(['show_global_exercises' => false]);
        
        // Create global exercise
        Exercise::factory()->create(['title' => 'Global Exercise', 'user_id' => null]);
        
        // Create user exercise
        Exercise::factory()->create(['title' => 'User Exercise', 'user_id' => $user->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJsonCount(1);
        
        $exercises = $response->json();
        $this->assertContains('User Exercise', $exercises);
        $this->assertNotContains('Global Exercise', $exercises);
    }

    /**
     * Test exercise autocomplete includes soft-deleted exercises that user has logged
     */
    public function test_exercise_autocomplete_excludes_soft_deleted_exercises(): void
    {
        $user = User::factory()->create();
        
        // Create and soft delete an exercise
        $exercise = Exercise::factory()->create(['title' => 'Deleted Exercise', 'user_id' => $user->id]);
        $exercise->delete();
        
        // Create active exercise
        Exercise::factory()->create(['title' => 'Active Exercise', 'user_id' => $user->id]);
        
        $response = $this->actingAs($user)->getJson('/api/exercises/autocomplete');
        
        $response->assertStatus(200)
            ->assertJsonCount(1);
        
        $exercises = $response->json();
        $this->assertContains('Active Exercise', $exercises);
        $this->assertNotContains('Deleted Exercise', $exercises);
    }
}
