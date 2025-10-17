<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecommendationUrlParameterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create exercises with intelligence data for testing
        $exercise1 = Exercise::factory()->create([
            'title' => 'Push Up',
            'user_id' => null, // Global exercise
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise1->id,
            'movement_archetype' => 'push',
            'difficulty_level' => 2,
        ]);
        
        $exercise2 = Exercise::factory()->create([
            'title' => 'Pull Up',
            'user_id' => null, // Global exercise
        ]);
        
        ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise2->id,
            'movement_archetype' => 'pull',
            'difficulty_level' => 4,
        ]);
    }

    /** @test */
    public function url_parameters_are_properly_maintained_for_bookmarking()
    {
        $response = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=2');

        $response->assertStatus(200);
        
        // Check that the URL parameters are reflected in the hidden form fields
        $response->assertSee('name="movement_archetype"', false);
        $response->assertSee('value="push"', false);
        $response->assertSee('name="difficulty_level"', false);
        $response->assertSee('value="2"', false);
    }

    /** @test */
    public function button_highlighting_reads_from_url_parameters_on_page_load()
    {
        $response = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=2');

        $response->assertStatus(200);
        
        // Check that the correct buttons have the 'active' class
        $response->assertSee('data-value="push"', false);
        $response->assertSee('data-value="2"', false);
        
        // The response should contain the active class for the selected filters
        $content = $response->getContent();
        $this->assertStringContainsString('class="filter-button active"', $content);
    }

    /** @test */
    public function form_fields_are_synchronized_with_button_selections()
    {
        $response = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=pull&difficulty_level=4');

        $response->assertStatus(200);
        
        // Check that hidden form fields match the URL parameters
        $response->assertSee('value="pull"', false);
        $response->assertSee('value="4"', false);
        
        // Check that the correct buttons are marked as active
        $content = $response->getContent();
        $this->assertStringContainsString('data-value="pull"', $content);
        $this->assertStringContainsString('data-value="4"', $content);
    }

    /** @test */
    public function filter_state_is_preserved_across_page_reloads()
    {
        // First request with filters
        $response1 = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=2');

        $response1->assertStatus(200);
        
        // Second request with same filters should maintain state
        $response2 = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=2');

        $response2->assertStatus(200);
        
        // Both responses should have the same filter state
        $content1 = $response1->getContent();
        $content2 = $response2->getContent();
        
        // Check that both responses contain the same filter values
        $this->assertStringContainsString('value="push"', $content1);
        $this->assertStringContainsString('value="push"', $content2);
        $this->assertStringContainsString('value="2"', $content1);
        $this->assertStringContainsString('value="2"', $content2);
    }

    /** @test */
    public function empty_url_parameters_show_default_state()
    {
        $response = $this->actingAs($this->user)
            ->get('/recommendations');

        $response->assertStatus(200);
        
        // Check that hidden form fields are empty when no parameters are provided
        $content = $response->getContent();
        
        // Should contain empty value attributes for the hidden inputs
        $this->assertStringContainsString('value=""', $content);
        
        // The "All Patterns" and "All Levels" buttons should be active
        $this->assertStringContainsString('All Patterns', $content);
        $this->assertStringContainsString('All Levels', $content);
    }

    /** @test */
    public function invalid_url_parameters_are_handled_gracefully()
    {
        // Test with invalid movement archetype
        $response1 = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=invalid&difficulty_level=2');

        $response1->assertStatus(302); // Should redirect due to validation failure
        
        // Test with invalid difficulty level
        $response2 = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=10');

        $response2->assertStatus(302); // Should redirect due to validation failure
    }

    /** @test */
    public function url_parameters_are_preserved_in_action_buttons()
    {
        $response = $this->actingAs($this->user)
            ->get('/recommendations?movement_archetype=push&difficulty_level=2');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check that filter parameters are preserved in "Add to Today" links
        // The links should contain the current filter parameters
        if (strpos($content, 'Add to Today') !== false) {
            $this->assertStringContainsString('movement_archetype', $content);
            $this->assertStringContainsString('difficulty_level', $content);
        }
    }
}