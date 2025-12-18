<?php

namespace Tests\Feature\MobileEntry;

use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test for cancel button visibility in mobile-entry lifts page.
 *
 * This test ensures that the cancel button (red "×" button) in the exercise
 * selection list is properly hidden when the list is auto-expanded, and
 * properly shown when the list is manually expanded by the user.
 *
 * The cancel button should only appear when the user manually clicks "Log Now"
 * to expand the selection list. When the system auto-expands the list (e.g.,
 * for metrics-first users with no logs today), the cancel button should be
 * hidden since there's no "previous state" to return to.
 */
class CancelButtonVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that cancel button is hidden when list is auto-expanded via expand_selection parameter.
     *
     * When the user arrives at the page with expand_selection=true (or when the system
     * auto-expands for metrics-first users), the cancel button should not be rendered
     * because there's no collapsed state to return to.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_button_is_hidden_when_list_is_auto_expanded()
    {
        // Setup: Create a user and an exercise
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']);

        // Act: Visit the page with expand_selection=true
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => true]));

        // Assert: Page loads successfully
        $response->assertOk();

        // Assert: The list is expanded (has active class and expanded initial state)
        $response->assertSee('data-initial-state="expanded"', false);
        $response->assertSee('class="component-list-section active"', false);

        // Assert: The cancel button is NOT rendered in the HTML
        // The Blade template should not render the button at all when showCancelButton is false
        $response->assertDontSee('btn-cancel', false);
        $response->assertDontSee('Cancel and go back', false);
        $response->assertDontSee('<span class="cancel-icon">×</span>', false);
    }

    /**
     * Test that cancel button is shown when list is in default collapsed state.
     *
     * When the user arrives at the page normally (without expand_selection parameter),
     * the list should be collapsed by default, and when they click "Log Now" to expand it,
     * the cancel button should be available to collapse it again.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_button_is_shown_when_list_is_manually_expandable()
    {
        // Setup: Create a user and an exercise
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']);

        // Act: Visit the page without expand_selection parameter (default collapsed state)
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        // Assert: Page loads successfully
        $response->assertOk();

        // Assert: The list is collapsed by default (no active class, collapsed initial state)
        $response->assertDontSee('data-initial-state="expanded"', false);
        $response->assertSee('data-initial-state="collapsed"', false);

        // Assert: The cancel button IS rendered in the HTML
        // When the user clicks "Log Now", they should be able to cancel and return to collapsed state
        $response->assertSee('btn-cancel', false);
        $response->assertSee('Cancel and go back', false);
        $response->assertSee('<span class="cancel-icon">×</span>', false);
    }

    /**
     * Test that cancel button is hidden for metrics-first users with no logs today.
     *
     * Metrics-first users who haven't logged anything today should see the list
     * auto-expanded, and therefore should not see the cancel button.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_button_is_hidden_for_metrics_first_users_with_no_logs()
    {
        // Setup: Create a metrics-first user (with the preference enabled)
        $user = User::factory()->create([
            'metrics_first_logging_flow' => true
        ]);

        // Create an exercise but no lift logs for today
        Exercise::factory()->create(['title' => 'Bench Press']);

        // Act: Visit the page (should auto-expand for metrics-first users with no logs)
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        // Assert: Page loads successfully
        $response->assertOk();

        // Assert: The list is auto-expanded
        $response->assertSee('data-initial-state="expanded"', false);
        $response->assertSee('class="component-list-section active"', false);

        // Assert: The cancel button is NOT rendered
        $response->assertDontSee('btn-cancel', false);
        $response->assertDontSee('Cancel and go back', false);
    }

    /**
     * Test that cancel button is shown for metrics-first users who already have logs today.
     *
     * Metrics-first users who have already logged something today should see the list
     * in collapsed state by default, with the cancel button available.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_button_is_shown_for_metrics_first_users_with_existing_logs()
    {
        // Setup: Create a metrics-first user
        $user = User::factory()->create([
            'metrics_first_logging_flow' => true
        ]);

        // Create an exercise and a lift log for today
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        // Act: Visit the page (should NOT auto-expand since user has logs today)
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        // Assert: Page loads successfully
        $response->assertOk();

        // Assert: The list is collapsed by default
        $response->assertSee('data-initial-state="collapsed"', false);

        // Assert: The cancel button IS rendered
        $response->assertSee('btn-cancel', false);
        $response->assertSee('Cancel and go back', false);
    }

    /**
     * Test that cancel button visibility is independent of exercise count.
     *
     * The cancel button visibility should depend only on whether the list is
     * auto-expanded, not on how many exercises are available.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_button_visibility_is_independent_of_exercise_count()
    {
        // Setup: Create a user and multiple exercises
        $user = User::factory()->create();
        Exercise::factory()->count(10)->create();

        // Test 1: Auto-expanded with many exercises - no cancel button
        $responseExpanded = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => true]));
        $responseExpanded->assertOk();
        $responseExpanded->assertDontSee('btn-cancel', false);

        // Test 2: Collapsed with many exercises - has cancel button
        $responseCollapsed = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        $responseCollapsed->assertOk();
        $responseCollapsed->assertSee('btn-cancel', false);

        // Test 3: Auto-expanded with one exercise - no cancel button
        Exercise::query()->delete();
        Exercise::factory()->create(['title' => 'Single Exercise']);
        
        $responseSingleExpanded = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => true]));
        $responseSingleExpanded->assertOk();
        $responseSingleExpanded->assertDontSee('btn-cancel', false);
    }

    /**
     * Test that the component-filter-group has correct CSS class when cancel button is hidden.
     *
     * When the cancel button is hidden, the filter group should have the
     * 'component-filter-group--no-cancel' class to adjust the layout.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function filter_group_has_no_cancel_class_when_button_is_hidden()
    {
        // Setup: Create a user and an exercise
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']);

        // Act: Visit with auto-expanded list
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', ['expand_selection' => true]));

        // Assert: The filter group has the no-cancel class
        $response->assertOk();
        $response->assertSee('component-filter-group--no-cancel', false);
    }

    /**
     * Test that the component-filter-group does NOT have no-cancel class when button is shown.
     *
     * When the cancel button is shown, the filter group should NOT have the
     * 'component-filter-group--no-cancel' class.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function filter_group_does_not_have_no_cancel_class_when_button_is_shown()
    {
        // Setup: Create a user and an exercise
        $user = User::factory()->create();
        Exercise::factory()->create(['title' => 'Bench Press']);

        // Act: Visit with collapsed list (default)
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        // Assert: The filter group does NOT have the no-cancel class
        $response->assertOk();
        $response->assertDontSee('component-filter-group--no-cancel', false);
    }
}
