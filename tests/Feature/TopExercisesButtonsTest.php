<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopExercisesButtonsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['show_global_exercises' => false]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function more_button_does_not_appear_when_user_has_no_exercises_and_global_visibility_off()
    {
        // User has no personal exercises and global visibility is off
        $response = $this->get(route('lift-logs.index'));
        $response->assertStatus(200);
        $response->assertDontSee('More...');
    }

    /** @test */
    public function more_button_does_not_appear_when_user_has_fewer_exercises_than_display_limit()
    {
        // Create only 2 personal exercises for the user
        Exercise::factory()->count(2)->create(['user_id' => $this->user->id]);
        
        $response = $this->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertDontSee('More...');
    }

    /** @test */
    public function more_button_appears_when_user_has_more_exercises_than_display_limit()
    {
        // Create 7 personal exercises (more than the 5 display limit)
        Exercise::factory()->count(7)->create(['user_id' => $this->user->id]);
        
        $response = $this->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertSee('More...');
    }

    /** @test */
    public function more_button_appears_with_global_exercises_when_visibility_enabled()
    {
        // Enable global exercise visibility
        $this->user->update(['show_global_exercises' => true]);
        
        // Create some global exercises (user_id = null)
        Exercise::factory()->count(7)->create(['user_id' => null]);
        
        $response = $this->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertSee('More...');
    }
}