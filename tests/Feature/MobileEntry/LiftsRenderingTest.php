<?php

namespace Tests\Feature\MobileEntry;

use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test for rendering of the mobile-entry lifts page.
 *
 * This test specifically targets a rendering bug where an extra curly brace '}'
 * was appearing after the "Completed!" message for logged lift items.
 * It ensures that the HTML output for logged items is correct and free of this bug.
 */
class LiftsRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a logged lift item's completion message does not render with an extra curly brace.
     *
     * This test simulates a user logging a lift and then visiting the mobile entry page.
     * It asserts that the "Completed!" message, which serves as a prefix for logged items,
     * is rendered correctly without any extraneous characters.
     *
     * The bug specifically involved an extra '}' character appearing after "Completed!".
     * This test acts as a regression test to prevent this bug from reoccurring.
     *
     * @return void
     */
    public function logged_lift_item_does_not_render_extra_curly_brace()
    {
        // 1. Setup: Create the necessary data to simulate a logged lift.
        //    A user is needed to authenticate the request.
        //    An exercise is needed for the lift log.
        //    A LiftLog entry is crucial as the bug occurred when displaying *logged* items.
        //    A LiftSet is added to ensure the LiftLog is complete and would be displayed.
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(), // Logged for today's date
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 10,
        ]);

        // 2. Act: Simulate the user visiting the mobile-entry lifts page.
        //    The `actingAs($user)` method authenticates the user for the request.
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        // 3. Assert: Verify the page content.
        //    `assertOk()` ensures the page loaded successfully (HTTP 200).
        $response->assertOk();
        //    `assertSee('Bench Press')` confirms the exercise title is present,
        //    indicating the logged item is being rendered.
        $response->assertSee('Bench Press');

        //    `assertDontSee('Completed!}', false)` is the primary assertion for the bug.
        //    It checks that the exact buggy string (including the extra '}') is NOT present.
        //    The `false` argument is important as it tells Laravel to search the unescaped HTML.
        $response->assertDontSee('Completed!}', false);

        //    `assertSee('<span class="message-prefix">Completed!</span>', false)` confirms
        //    that the *correct* HTML structure for the completion message is present.
        //    This ensures the message is rendered as expected after the fix.
        $response->assertSee('<span class="message-prefix">Completed!</span>', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_expanded_list_and_correct_message_when_expand_selection_is_true()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']); // Need at least one exercise
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => true]));
        
        $response->assertOk();
        
        // Assert the correct contextual message is displayed
        $response->assertSee(config('mobile_entry_messages.contextual_help.pick_exercise'), false);
        $response->assertDontSee(config('mobile_entry_messages.contextual_help.getting_started'), false);
        
        // Assert the "Log Now" button is not present
        $response->assertDontSee('Log Now'); // The button itself
        $response->assertDontSee('<div class="component-button-section">', false); // The button's containing section, which would be hidden
        
        // Assert the item list is expanded
        // The initialState is set via a data attribute which is then picked up by JS
        $response->assertSee('<section class="component-list-section active" aria-label="Item selection list" data-initial-state="expanded">', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_collapsed_list_and_default_message_when_expand_selection_is_false()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']); // Need at least one exercise
        
        // Test with expand_selection explicitly false
        $responseFalse = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => false]));
        $responseFalse->assertOk();
        $responseFalse->assertSee('to begin!', false);
        $responseFalse->assertDontSee(config('mobile_entry_messages.contextual_help.pick_exercise'), false);
        $responseFalse->assertSee('Log Now');
        $responseFalse->assertSee('<div class="component-button-section"', false);
        $responseFalse->assertDontSee('<div class="component-list-section" data-initial-state="expanded"', false);

        // Test with expand_selection absent
        $responseAbsent = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        $responseAbsent->assertOk();

        $responseAbsent->assertSee('to begin!', false);
        $responseAbsent->assertDontSee(config('mobile_entry_messages.contextual_help.pick_exercise'), false);
        $responseAbsent->assertSee('Log Now');
        $responseAbsent->assertSee('<div class="component-button-section"', false);
        $responseAbsent->assertDontSee('<div class="component-list-section" data-initial-state="expanded"', false);
    }
}
