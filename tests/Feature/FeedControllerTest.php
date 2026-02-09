<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PersonalRecord;
use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /** @test */
    public function it_displays_pr_feed_page()
    {
        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('PR Feed');
    }

    /** @test */
    public function it_shows_prs_from_last_7_days_only()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Recent PR (within 7 days)
        $recentPR = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subDays(3),
        ]);

        // Old PR (more than 7 days ago)
        $oldPR = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see recent PR's exercise
        $response->assertSee($exercise->title);
    }

    /** @test */
    public function it_only_shows_prs_from_followed_users()
    {
        $followedUser = User::factory()->create();
        $unfollowedUser = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $followedLiftLog = LiftLog::factory()->create(['user_id' => $followedUser->id]);
        $unfollowedLiftLog = LiftLog::factory()->create(['user_id' => $unfollowedUser->id]);

        // Follow only one user
        $this->user->follow($followedUser);

        // Create PRs for both users
        $followedPR = PersonalRecord::factory()->create([
            'user_id' => $followedUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $followedLiftLog->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $unfollowedPR = PersonalRecord::factory()->create([
            'user_id' => $unfollowedUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $unfollowedLiftLog->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see followed user's name
        $response->assertSee($followedUser->name);
        // Should NOT see unfollowed user's name
        $response->assertDontSee($unfollowedUser->name);
    }

    /** @test */
    public function it_shows_own_prs_in_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);

        // Create PR for current user
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see "You" instead of user's name
        $response->assertSee('You');
        $response->assertSee($exercise->title);
    }

    /** @test */
    public function it_shows_empty_message_when_no_prs()
    {
        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('No PRs in the last 7 days');
    }

    /** @test */
    public function it_displays_users_list_page()
    {
        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        $response->assertSee('Users');
        $response->assertSee($this->otherUser->name);
    }

    /** @test */
    public function it_excludes_current_user_from_users_list()
    {
        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        $response->assertDontSee($this->user->name);
        $response->assertSee($this->otherUser->name);
    }

    /** @test */
    public function it_displays_user_profile_page()
    {
        $response = $this->actingAs($this->user)->get(route('feed.users.show', $this->otherUser));

        $response->assertStatus(200);
        $response->assertSee($this->otherUser->name);
        $response->assertSee('Follow');
    }

    /** @test */
    public function it_shows_user_stats_on_profile()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        PersonalRecord::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.users.show', $this->otherUser));

        $response->assertStatus(200);
        $response->assertSee('PRs');
        $response->assertSee('Followers');
        $response->assertSee('Following');
    }

    /** @test */
    public function it_shows_recent_prs_on_user_profile()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Deadlift']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.users.show', $this->otherUser));

        $response->assertStatus(200);
        $response->assertSee('Recent PRs');
        $response->assertSee('Deadlift');
    }

    /** @test */
    public function it_does_not_show_follow_button_on_own_profile()
    {
        $response = $this->actingAs($this->user)->get(route('feed.users.show', $this->user));

        $response->assertStatus(200);
        // Check that the form buttons don't exist
        $response->assertDontSee('type="submit"');
    }

    /** @test */
    public function it_allows_user_to_follow_another_user()
    {
        $response = $this->actingAs($this->user)
            ->post(route('feed.users.follow', $this->otherUser));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertTrue($this->user->isFollowing($this->otherUser));
    }

    /** @test */
    public function it_allows_user_to_unfollow_another_user()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)
            ->delete(route('feed.users.unfollow', $this->otherUser));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertFalse($this->user->isFollowing($this->otherUser));
    }

    /** @test */
    public function it_prevents_user_from_following_themselves()
    {
        $response = $this->actingAs($this->user)
            ->post(route('feed.users.follow', $this->user));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $this->assertFalse($this->user->isFollowing($this->user));
    }

    /** @test */
    public function it_shows_unfollow_button_when_already_following()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)
            ->get(route('feed.users.show', $this->otherUser));

        $response->assertStatus(200);
        // Check for the submit button with Unfollow text
        $response->assertSee('Unfollow', false);
        // Verify it's a form submission button
        $response->assertSee('type="submit"', false);
    }

    /** @test */
    public function it_requires_authentication_for_feed_pages()
    {
        $this->get(route('feed.index'))->assertRedirect(route('login'));
        $this->get(route('feed.users'))->assertRedirect(route('login'));
        $this->get(route('feed.users.show', $this->otherUser))->assertRedirect(route('login'));
    }

    /** @test */
    public function it_categorizes_users_by_follow_status()
    {
        $followedUser = User::factory()->create(['name' => 'Followed User']);
        $unfollowedUser = User::factory()->create(['name' => 'Unfollowed User']);
        
        // Follow one user
        $this->user->follow($followedUser);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        
        // Both users should be visible
        $response->assertSee('Followed User');
        $response->assertSee('Unfollowed User');
        
        // Check that the followed user has the "Following" label
        $response->assertSee('Following');
        
        // Check that the unfollowed user has the "Not following" label
        $response->assertSee('Not following');
    }

    /** @test */
    public function it_groups_followed_users_before_unfollowed_users()
    {
        // Create users with names that would be out of order alphabetically
        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);
        $userC = User::factory()->create(['name' => 'Charlie']);
        
        // Follow Bob (middle alphabetically)
        $this->user->follow($userB);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        
        // Get the response content
        $content = $response->getContent();
        
        // Find positions of each user name in the HTML
        $posAlice = strpos($content, 'Alice');
        $posBob = strpos($content, 'Bob');
        $posCharlie = strpos($content, 'Charlie');
        
        // Bob (followed) should appear before Alice and Charlie (not followed)
        $this->assertLessThan($posAlice, $posBob, 'Followed user Bob should appear before unfollowed user Alice');
        $this->assertLessThan($posCharlie, $posBob, 'Followed user Bob should appear before unfollowed user Charlie');
    }

    /** @test */
    public function it_groups_prs_by_user_and_date()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Squat']);
        $exercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create two lift logs for the same user on the same day
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => now()->subHours(2),
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => now()->subHours(1),
        ]);
        
        // Create PRs for both lift logs
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise1->id,
            'lift_log_id' => $liftLog1->id,
            'achieved_at' => now()->subHours(2),
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise2->id,
            'lift_log_id' => $liftLog2->id,
            'achieved_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see both exercises
        $response->assertSee('Squat');
        $response->assertSee('Bench Press');
        // Should see "2 PRs" in the card header (counting exercises, not total PR badges)
        $response->assertSee('2 PRs');
        // Should only see the user's name once (grouped in one card)
        $this->assertEquals(1, substr_count($response->getContent(), $this->otherUser->name));
    }

    /** @test */
    public function it_shows_new_badge_for_prs_within_24_hours()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a recent PR (within 24 hours)
        $recentLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $recentLiftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);
        
        // Create an old PR (more than 24 hours ago)
        $oldLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $oldLiftLog->id,
            'achieved_at' => now()->subHours(30),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see NEW badge for recent PR
        $response->assertSee('NEW');
        // Should see the pr-card-new class
        $response->assertSee('pr-card-new');
    }

    /** @test */
    public function it_does_not_show_new_badge_for_old_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create an old PR (more than 24 hours ago)
        $oldLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $oldLiftLog->id,
            'achieved_at' => now()->subHours(30),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should NOT see NEW badge
        $response->assertDontSee('NEW');
        // Should NOT see the pr-card-new class
        $response->assertDontSee('pr-card-new');
    }

    /** @test */
    public function it_shows_mark_as_read_button_when_new_prs_exist()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a recent PR
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Mark all as read');
    }

    /** @test */
    public function it_marks_feed_as_read()
    {
        $this->assertNull($this->user->last_feed_viewed_at);

        $response = $this->actingAs($this->user)
            ->post(route('feed.mark-read'));

        $response->assertRedirect(route('feed.index'));
        
        $this->user->refresh();
        $this->assertNotNull($this->user->last_feed_viewed_at);
    }

    /** @test */
    public function it_hides_new_badge_after_marking_as_read()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a PR 12 hours ago
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        // First visit - should see NEW badge
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertSee('NEW');

        // Mark as read
        $this->actingAs($this->user)->post(route('feed.mark-read'));

        // Second visit - should NOT see NEW badge
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertDontSee('NEW');
        $response->assertDontSee('Mark all as read');
    }

    /** @test */
    public function it_shows_reset_button_for_admins()
    {
        // Create admin role
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);
        
        // Set last viewed to future so all PRs appear as "read"
        $this->user->update(['last_feed_viewed_at' => now()->addDay()]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Reset to unread');
    }

    /** @test */
    public function it_shows_mark_as_read_instead_of_reset_when_new_prs_exist()
    {
        // Create admin role
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);
        
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a recent PR
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Mark all as read');
        $response->assertDontSee('Reset to unread');
    }

    /** @test */
    public function it_allows_admin_to_reset_to_unread()
    {
        // Create admin role
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);

        // Mark as read first
        $this->user->update(['last_feed_viewed_at' => now()]);
        $this->assertNotNull($this->user->last_feed_viewed_at);

        // Reset to unread
        $response = $this->actingAs($this->user)
            ->post(route('feed.reset-read'));

        $response->assertRedirect(route('feed.index'));
        
        $this->user->refresh();
        $this->assertNull($this->user->last_feed_viewed_at);
    }

    /** @test */
    public function it_prevents_non_admin_from_resetting_to_unread()
    {
        // Mark as read first
        $this->user->update(['last_feed_viewed_at' => now()]);

        // Try to reset as non-admin
        $response = $this->actingAs($this->user)
            ->post(route('feed.reset-read'));

        $response->assertStatus(403);
        
        $this->user->refresh();
        $this->assertNotNull($this->user->last_feed_viewed_at);
    }

    /** @test */
    public function it_allows_user_to_give_high_five_to_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('feed.toggle-high-five', $pr));

        $response->assertRedirect();
        
        $this->assertDatabaseHas('pr_high_fives', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_allows_user_to_remove_high_five_from_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        // Give high five first
        $this->user->highFivePR($pr);
        
        $this->assertDatabaseHas('pr_high_fives', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);

        // Remove high five
        $response = $this->actingAs($this->user)
            ->post(route('feed.toggle-high-five', $pr));

        $response->assertRedirect();
        
        $this->assertDatabaseMissing('pr_high_fives', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_returns_json_for_ajax_high_five_request()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('feed.toggle-high-five', $pr));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'highFived' => true,
            'count' => 1,
        ]);
        
        $this->assertDatabaseHas('pr_high_fives', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_returns_correct_count_when_removing_high_five_via_ajax()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        // Give high five first
        $this->user->highFivePR($pr);

        // Remove via AJAX
        $response = $this->actingAs($this->user)
            ->postJson(route('feed.toggle-high-five', $pr));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'highFived' => false,
            'count' => 0,
        ]);
    }

    /** @test */
    public function it_returns_correct_count_with_multiple_high_fives()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        // Other users give high fives
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user2->highFivePR($pr);
        $user3->highFivePR($pr);

        // Current user gives high five via AJAX
        $response = $this->actingAs($this->user)
            ->postJson(route('feed.toggle-high-five', $pr));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'highFived' => true,
            'count' => 3,
        ]);
    }

    /** @test */
    public function it_shows_high_five_count_on_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Give high five
        $this->user->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('high-five-count');
        $response->assertSee('1'); // Count should be visible
    }

    /** @test */
    public function it_shows_high_five_count_for_own_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Other user gives high five
        $this->otherUser->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see the high five display (not button) for own PRs
        $response->assertSee('high-five-display');
        $response->assertSee('1'); // Count should be visible
        // Should NOT see the interactive button
        $response->assertDontSee('toggle-high-five');
    }

    /** @test */
    public function it_shows_names_of_users_who_high_fived_own_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Multiple users give high fives
        $user2 = User::factory()->create(['name' => 'Alice']);
        $user3 = User::factory()->create(['name' => 'Bob']);
        $user2->highFivePR($pr);
        $user3->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see the names displayed with proper grammar
        $response->assertSee('high-five-names');
        $response->assertSee('Alice');
        $response->assertSee('Bob');
        $response->assertSee('and');
        $response->assertSee('loves');
        $response->assertSee('this!');
        $response->assertSee('2'); // Count should show 2
    }

    /** @test */
    public function it_formats_single_high_five_name_correctly()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        $user2 = User::factory()->create(['name' => 'Charlie']);
        $user2->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Charlie');
        $response->assertSee('loves');
        $response->assertSee('this!');
        // Should see "Charlie loves this!" without "and"
        $response->assertSee('Charlie</strong> loves&nbsp;this!', false);
    }

    /** @test */
    public function it_formats_three_or_more_high_five_names_with_commas()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        $user2 = User::factory()->create(['name' => 'Alice']);
        $user3 = User::factory()->create(['name' => 'Bob']);
        $user4 = User::factory()->create(['name' => 'Charlie']);
        $user2->highFivePR($pr);
        $user3->highFivePR($pr);
        $user4->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Alice');
        $response->assertSee('Bob');
        $response->assertSee('Charlie');
        $response->assertSee('and');
        $response->assertSee('loves');
        $response->assertSee('this!');
    }

    /** @test */
    public function it_does_not_show_high_five_display_for_own_prs_with_no_high_fives()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->user->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should not see high five display when there are no high fives
        $response->assertDontSee('high-five-display');
        $response->assertDontSee('toggle-high-five');
    }

    /** @test */
    public function it_shows_names_for_other_users_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Third user gives high five
        $user3 = User::factory()->create(['name' => 'David']);
        $user3->highFivePR($pr);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see the interactive button
        $response->assertSee('high-five-btn');
        // Should also see the names
        $response->assertSee('David');
        $response->assertSee('loves');
        $response->assertSee('this!');
    }

    /** @test */
    public function it_requires_authentication_for_high_five()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->post(route('feed.toggle-high-five', $pr));
        $response->assertRedirect(route('login'));
    }
}
