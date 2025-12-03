<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_history_page_with_exercises_that_have_logs()
    {
        $user = User::factory()->create();
        
        // Create exercises
        $exerciseWithLogs = Exercise::factory()->create(['title' => 'Bench Press']);
        $exerciseWithoutLogs = Exercise::factory()->create(['title' => 'Squat']);
        
        // Create lift logs only for the first exercise
        LiftLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'exercise_id' => $exerciseWithLogs->id,
        ]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Should see the exercise with logs
        $response->assertSee('Bench Press');
        
        // Should NOT see the exercise without logs
        $response->assertDontSee('Squat');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_correct_result_count_for_each_exercise()
    {
        $user = User::factory()->create();
        
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press']);
        $exercise2 = Exercise::factory()->create(['title' => 'Deadlift']);
        
        // Create 5 logs for exercise 1
        LiftLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
        ]);
        
        // Create 1 log for exercise 2
        LiftLog::factory()->count(1)->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
        ]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Should see correct counts
        $response->assertSee('5 results');
        $response->assertSee('1 result');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_only_shows_exercises_with_logs_for_current_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $exercise1 = Exercise::factory()->create(['title' => 'User 1 Exercise']);
        $exercise2 = Exercise::factory()->create(['title' => 'User 2 Exercise']);
        
        // User 1 has logs for exercise 1
        LiftLog::factory()->count(2)->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise1->id,
        ]);
        
        // User 2 has logs for exercise 2
        LiftLog::factory()->count(3)->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise2->id,
        ]);
        
        // User 1 should only see their exercise
        $response1 = $this->actingAs($user1)->get(route('lift-logs.index'));
        $response1->assertStatus(200);
        $response1->assertSee('User 1 Exercise');
        $response1->assertDontSee('User 2 Exercise');
        
        // User 2 should only see their exercise
        $response2 = $this->actingAs($user2)->get(route('lift-logs.index'));
        $response2->assertStatus(200);
        $response2->assertSee('User 2 Exercise');
        $response2->assertDontSee('User 1 Exercise');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_empty_message_when_user_has_no_logs()
    {
        $user = User::factory()->create();
        
        // Create an exercise but no logs
        Exercise::factory()->create(['title' => 'Bench Press']);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);

        // Assert parts of the message to avoid issues with special characters
        $response->assertSee('This page will come alive with your training history', false);
        $response->assertSee('Let&#039;s get started!', false); // Check for HTML entity of apostrophe

        $response->assertSee('Log Now');
        $response->assertSee(route('mobile-entry.lifts', ['expand_selection' => true]), false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_links_to_exercise_show_logs_pages()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        LiftLog::factory()->count(2)->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Should include link to show-logs page
        $expectedUrl = route('exercises.show-logs', $exercise);
        $response->assertSee($expectedUrl, false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_exercises_in_alphabetical_order()
    {
        $user = User::factory()->create();
        
        $exerciseZ = Exercise::factory()->create(['title' => 'Zebra Lift']);
        $exerciseA = Exercise::factory()->create(['title' => 'Apple Lift']);
        $exerciseM = Exercise::factory()->create(['title' => 'Mango Lift']);
        
        // Create logs for all exercises
        LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exerciseZ->id]);
        LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exerciseA->id]);
        LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exerciseM->id]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Get the response content
        $content = $response->getContent();
        
        // Find positions of each exercise name
        $posA = strpos($content, 'Apple Lift');
        $posM = strpos($content, 'Mango Lift');
        $posZ = strpos($content, 'Zebra Lift');
        
        // Assert they appear in alphabetical order
        $this->assertLessThan($posM, $posA, 'Apple Lift should appear before Mango Lift');
        $this->assertLessThan($posZ, $posM, 'Mango Lift should appear before Zebra Lift');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_exercise_aliases_in_display()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        // Create an alias for this user
        $exercise->aliases()->create([
            'user_id' => $user->id,
            'alias_name' => 'BP',
        ]);
        
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Should see the alias, not the original title
        $response->assertSee('BP');
        $response->assertDontSee('Bench Press');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_search_filter_placeholder()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Tap to search...');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_flexible_component_system()
    {
        $user = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        $response = $this->actingAs($user)->get(route('lift-logs.index'));
        
        $response->assertStatus(200);
        
        // Should use the flexible view
        $response->assertViewIs('mobile-entry.flexible');
        
        // Should have components data
        $response->assertViewHas('data');
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        $this->assertIsArray($data['components']);
    }
}
