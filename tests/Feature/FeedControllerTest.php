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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
    public function it_only_shows_prs_for_exercises_with_show_in_feed_enabled()
    {
        $exerciseWithFeed = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Squat',
            'show_in_feed' => true
        ]);
        
        $exerciseWithoutFeed = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Curl',
            'show_in_feed' => false
        ]);
        
        $liftLog1 = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        $liftLog2 = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // PR for exercise with show_in_feed enabled
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exerciseWithFeed->id,
            'lift_log_id' => $liftLog1->id,
            'achieved_at' => now()->subDays(1),
        ]);

        // PR for exercise with show_in_feed disabled
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exerciseWithoutFeed->id,
            'lift_log_id' => $liftLog2->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see exercise with show_in_feed enabled
        $response->assertSee('Squat');
        // Should NOT see exercise with show_in_feed disabled
        $response->assertDontSee('Curl');
    }

    /** @test */
    public function it_filters_own_prs_by_show_in_feed()
    {
        $exerciseWithFeed = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Deadlift',
            'show_in_feed' => true
        ]);
        
        $exerciseWithoutFeed = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Tricep Extension',
            'show_in_feed' => false
        ]);
        
        $liftLog1 = LiftLog::factory()->create(['user_id' => $this->user->id]);
        $liftLog2 = LiftLog::factory()->create(['user_id' => $this->user->id]);

        // PR for exercise with show_in_feed enabled
        PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseWithFeed->id,
            'lift_log_id' => $liftLog1->id,
            'achieved_at' => now()->subDays(1),
        ]);

        // PR for exercise with show_in_feed disabled
        PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exerciseWithoutFeed->id,
            'lift_log_id' => $liftLog2->id,
            'achieved_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see own PR with show_in_feed enabled
        $response->assertSee('Deadlift');
        // Should NOT see own PR with show_in_feed disabled
        $response->assertDontSee('Tricep Extension');
    }

    /** @test */
    public function it_only_shows_prs_from_followed_users()
    {
        $followedUser = User::factory()->create();
        $unfollowedUser = User::factory()->create();
        
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        // Follow the other user so they appear in the list
        $this->user->follow($this->otherUser);
        
        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        $response->assertSee('Users');
        $response->assertSee($this->otherUser->name);
    }

    /** @test */
    public function it_excludes_current_user_from_users_list()
    {
        // Follow the other user so they appear in the list
        $this->user->follow($this->otherUser);
        
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true, 'title' => 'Deadlift']);
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
        
        // Only followed user should be visible for regular users
        $response->assertSee('Followed User');
        $response->assertDontSee('Unfollowed User');
        
        // Check that the followed user has the "Following" label
        $response->assertSee('Following');
        
        // Should NOT see "Not following" label (no unfollowed users shown)
        $response->assertDontSee('Not following');
    }

    /** @test */
    public function it_groups_followed_users_before_unfollowed_users()
    {
        // Make user an admin to see all users
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);
        
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
    public function it_shows_only_followed_users_for_regular_users()
    {
        $followedUser = User::factory()->create(['name' => 'Followed User']);
        $unfollowedUser = User::factory()->create(['name' => 'Unfollowed User']);
        
        // Follow only one user
        $this->user->follow($followedUser);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        
        // Should only see followed user
        $response->assertSee('Followed User');
        $response->assertDontSee('Unfollowed User');
    }

    /** @test */
    public function it_shows_all_users_for_admin()
    {
        // Make user an admin
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);
        
        $followedUser = User::factory()->create(['name' => 'Followed User']);
        $unfollowedUser = User::factory()->create(['name' => 'Unfollowed User']);
        
        // Follow only one user
        $this->user->follow($followedUser);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        
        // Admin should see both users
        $response->assertSee('Followed User');
        $response->assertSee('Unfollowed User');
        $response->assertSee('Following');
        $response->assertSee('Not following');
    }

    /** @test */
    public function it_shows_all_users_for_impersonated_users()
    {
        $followedUser = User::factory()->create(['name' => 'Followed User']);
        $unfollowedUser = User::factory()->create(['name' => 'Unfollowed User']);
        
        // Follow only one user
        $this->user->follow($followedUser);
        
        // Simulate impersonation
        session(['impersonator_id' => 999]);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        
        // Impersonated user should see both users
        $response->assertSee('Followed User');
        $response->assertSee('Unfollowed User');
        $response->assertSee('Following');
        $response->assertSee('Not following');
    }

    /** @test */
    public function it_groups_prs_by_user_and_date()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true, 'title' => 'Squat']);
        $exercise2 = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true, 'title' => 'Bench Press']);
        
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
        // Should only see the user's name once (grouped in one card)
        $this->assertEquals(1, substr_count($response->getContent(), $this->otherUser->name));
    }

    /** @test */
    public function it_shows_new_badge_for_prs_within_24_hours()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create an old PR (more than 24 hours ago)
        $oldLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $oldLiftLog->id,
            'achieved_at' => now()->subHours(30),
        ]);
        
        // Mark the PR as read
        $pr->markAsReadBy($this->user);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should NOT see NEW badge (PR is marked as read)
        $response->assertDontSee('NEW');
        // Should NOT see the pr-card-new class
        $response->assertDontSee('pr-card-new');
    }

    /** @test */
    public function it_auto_marks_feed_as_read_on_view()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a PR
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);
        
        // Verify PR is not read yet
        $this->assertFalse($pr->isReadBy($this->user));

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        
        // Verify PR is now marked as read
        $this->assertTrue($pr->isReadBy($this->user));
    }

    /** @test */
    public function it_hides_new_badge_after_viewing_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a PR 12 hours ago
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        // First visit - should see NEW badge (PR is unread)
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertSee('NEW');
        
        // Mark PR as read (simulating what happens after viewing)
        $pr->markAsReadBy($this->user);

        // Second visit - should NOT see NEW badge
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertDontSee('NEW');
    }

    /** @test */
    /** @test */
    public function it_allows_user_to_give_high_five_to_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        
        // Check that names are returned with "You" for current user and correct verb
        $responseData = $response->json();
        $this->assertArrayHasKey('names', $responseData);
        $this->assertArrayHasKey('verb', $responseData);
        $this->assertStringContainsString('You', $responseData['names']);
        $this->assertEquals('love', $responseData['verb']); // "You love" not "You loves"
        
        $this->assertDatabaseHas('pr_high_fives', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
    }

    /** @test */
    public function it_returns_correct_count_when_removing_high_five_via_ajax()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        // Should see the names displayed with proper grammar (plural "love")
        $response->assertSee('high-five-names');
        $response->assertSee('Alice');
        $response->assertSee('Bob');
        $response->assertSee('and');
        $response->assertSee('love'); // Plural verb for multiple people
        $response->assertSee('this!');
        $response->assertSee('2'); // Count should show 2
    }

    /** @test */
    public function it_formats_single_high_five_name_correctly()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $response->assertSee('love'); // Plural verb for multiple people
        $response->assertSee('this!');
    }

    /** @test */
    public function it_does_not_show_high_five_display_for_own_prs_with_no_high_fives()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
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
        // Should also see the names with singular verb (only one person)
        $response->assertSee('David');
        $response->assertSee('loves'); // Singular verb for single person
        $response->assertSee('this!');
    }

    /** @test */
    public function it_requires_authentication_for_high_five()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->post(route('feed.toggle-high-five', $pr));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_shows_badge_count_for_new_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a recent PR (within 24 hours)
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
        // Should see the badge with count
        $response->assertSee('menu-badge');
        $response->assertSee('>1<', false); // Badge count of 1
    }

    /** @test */
    public function it_does_not_show_badge_when_no_new_prs()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create an old PR (more than 24 hours ago)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(30),
        ]);
        
        // View the feed to mark the PR as read
        $this->actingAs($this->user)->get(route('feed.index'));
        
        // Now view the feed again - should not show badge since all PRs are read
        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should NOT see the badge
        $response->assertDontSee('menu-badge');
    }

    /** @test */
    public function it_shows_badge_count_for_multiple_user_date_groups()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create PRs for different dates
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog1->id,
            'achieved_at' => now()->subHours(12),
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog2->id,
            'achieved_at' => now()->subHours(36), // Different day
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see badge with count of 2 (two different dates)
        $response->assertSee('menu-badge');
        $response->assertSee('>2<', false); // Badge count of 2
    }

    /** @test */
    public function it_shows_all_prs_as_new_for_first_time_users()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create PRs at different times within 7 days
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog1->id,
            'achieved_at' => now()->subHours(12), // Recent
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog2->id,
            'achieved_at' => now()->subDays(3), // Older but within 7 days
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see badge with count of 2 (all PRs are new for first-time user)
        $response->assertSee('menu-badge');
        $response->assertSee('>2<', false); // Badge count of 2
    }

    /** @test */
    public function it_clears_badge_after_viewing_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
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

        // First visit - should see badge
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertSee('menu-badge');

        // Second visit - should NOT see badge (PRs marked as read after first visit)
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertDontSee('menu-badge');
    }

    /** @test */
    public function it_shows_own_prs_as_new_when_recent()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Create own PR (recent)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see own PR WITH NEW badge
        $response->assertSee('You');
        $response->assertSee('NEW');
        $response->assertSee('pr-card-new');
    }

    /** @test */
    public function it_counts_own_prs_in_badge()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Make user follow someone so Feed menu is visible
        $this->user->following()->attach($this->otherUser->id);
        
        // Create own PR (recent)
        $ownLiftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $ownLiftLog->id,
            'achieved_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see badge (own PRs now count)
        $response->assertSee('menu-badge');
        $response->assertSee('1'); // Badge count
    }

    /** @test */
    public function it_hides_feed_menu_when_not_following_anyone()
    {
        // User is not following anyone
        $this->assertEquals(0, $this->user->following()->count());
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        // Should NOT see Feed menu item
        $response->assertDontSee('id="feed-nav-link"', false);
    }

    /** @test */
    public function it_shows_feed_menu_when_following_someone()
    {
        // Follow another user
        $this->user->follow($this->otherUser);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        // Should see Feed menu item
        $response->assertSee('id="feed-nav-link"', false);
    }

    /** @test */
    public function it_shows_feed_menu_when_impersonating_even_if_not_following()
    {
        // User is not following anyone
        $this->assertEquals(0, $this->user->following()->count());
        
        // Simulate impersonation
        session(['impersonator_id' => 999]);
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        // Should see Feed menu item when impersonating
        $response->assertSee('id="feed-nav-link"', false);
    }

    /** @test */
    public function it_shows_feed_menu_for_admins_even_if_not_following()
    {
        // Make user an admin
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);
        
        // User is not following anyone
        $this->assertEquals(0, $this->user->following()->count());
        
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        // Should see Feed menu item for admins
        $response->assertSee('id="feed-nav-link"', false);
    }

    /** @test */
    public function it_allows_user_to_add_comment_to_pr()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('feed.store-comment', $pr), [
                'comment' => 'Great work!',
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('pr_comments', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Great work!',
        ]);
    }

    /** @test */
    public function it_returns_json_for_ajax_comment_request()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('feed.store-comment', $pr), [
                'comment' => 'Nice PR!',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('html', $responseData);
        $this->assertStringContainsString('Nice PR!', $responseData['html']);
        $this->assertStringContainsString('pr-comment', $responseData['html']);
    }

    /** @test */
    public function it_validates_comment_is_required()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('feed.store-comment', $pr), [
                'comment' => '',
            ]);

        $response->assertSessionHasErrors('comment');
    }

    /** @test */
    public function it_validates_comment_max_length()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('feed.store-comment', $pr), [
                'comment' => str_repeat('a', 1001), // Over 1000 chars
            ]);

        $response->assertSessionHasErrors('comment');
    }

    /** @test */
    public function it_allows_user_to_delete_own_comment()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $comment = \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Test comment',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('feed.delete-comment', $comment));

        $response->assertRedirect();
        
        $this->assertDatabaseMissing('pr_comments', [
            'id' => $comment->id,
        ]);
    }

    /** @test */
    public function it_prevents_user_from_deleting_others_comments()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $comment = \App\Models\PRComment::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Other user comment',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('feed.delete-comment', $comment));

        $response->assertStatus(403);
        
        $this->assertDatabaseHas('pr_comments', [
            'id' => $comment->id,
        ]);
    }

    /** @test */
    public function it_allows_admin_to_delete_any_comment()
    {
        // Make user an admin
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->user->roles()->attach($adminRole);

        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $comment = \App\Models\PRComment::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Other user comment',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('feed.delete-comment', $comment));

        $response->assertRedirect();
        
        $this->assertDatabaseMissing('pr_comments', [
            'id' => $comment->id,
        ]);
    }

    /** @test */
    public function it_returns_json_for_ajax_delete_comment_request()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $comment = \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Test comment',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('feed.delete-comment', $comment));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /** @test */
    public function it_shows_comments_on_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Add comment
        \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Awesome lift!',
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Awesome lift!');
        $response->assertSee('pr-comment');
    }

    /** @test */
    public function it_shows_comment_author_as_you_for_own_comments()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Add comment
        \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'My comment',
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('My comment');
        // Should see "You" as the author
        $response->assertSee('pr-comment-author">You</strong>', false);
    }

    /** @test */
    public function it_shows_delete_button_only_for_own_comments()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Add own comment
        \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'My comment',
        ]);

        // Add other user's comment
        \App\Models\PRComment::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Other comment',
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        // Should see both comments
        $response->assertSee('My comment');
        $response->assertSee('Other comment');
        // Should see delete button (at least one)
        $response->assertSee('pr-comment-delete-btn');
    }

    /** @test */
    public function it_requires_authentication_for_commenting()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $response = $this->post(route('feed.store-comment', $pr), [
            'comment' => 'Test',
        ]);
        
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_requires_authentication_for_deleting_comments()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
        ]);

        $comment = \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Test comment',
        ]);

        $response = $this->delete(route('feed.delete-comment', $comment));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_shows_comments_sorted_by_creation_time()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        $liftLog = LiftLog::factory()->create(['user_id' => $this->otherUser->id]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subHours(1),
        ]);

        // Follow the other user
        $this->user->follow($this->otherUser);

        // Add comments in specific order
        $comment1 = \App\Models\PRComment::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
            'comment' => 'First comment',
            'created_at' => now()->subMinutes(10),
        ]);

        $comment2 = \App\Models\PRComment::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $pr->id,
            'comment' => 'Second comment',
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        
        // Get the response content
        $content = $response->getContent();
        
        // Find positions of each comment in the HTML
        $posFirst = strpos($content, 'First comment');
        $posSecond = strpos($content, 'Second comment');
        
        // First comment should appear before second comment
        $this->assertLessThan($posSecond, $posFirst, 'Comments should be sorted by creation time');
    }

    /** @test */
    public function it_clears_read_status_when_unfollowing_user()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create a PR from the other user
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $pr = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $liftLog->id,
            'achieved_at' => now()->subDays(2),
        ]);
        
        // View the feed (marks PR as read)
        $this->actingAs($this->user)->get(route('feed.index'));
        
        // In tests, terminating callbacks execute after the response
        // Verify PR is marked as read
        $this->assertDatabaseHas('personal_record_reads', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
        
        // Unfollow the user
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\App\Http\Middleware\LogActivity::class)
            ->delete(route('feed.users.unfollow', $this->otherUser));
        $response->assertRedirect();
        
        // Verify PR is no longer marked as read
        $this->assertDatabaseMissing('personal_record_reads', [
            'user_id' => $this->user->id,
            'personal_record_id' => $pr->id,
        ]);
        
        // Re-follow the user
        $this->user->follow($this->otherUser);
        
        // View feed again - PR should show as unread since read status was cleared
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertSee('NEW');
    }

    /** @test */
    public function it_prioritizes_unread_prs_in_feed()
    {
        $exercise = Exercise::factory()->create(['user_id' => null, 'show_in_feed' => true]);
        
        // Follow the other user
        $this->user->follow($this->otherUser);
        
        // Create an older PR (3 days ago)
        $olderLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $olderPR = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $olderLiftLog->id,
            'achieved_at' => now()->subDays(3),
        ]);
        
        // Create a newer PR (1 day ago)
        $newerLiftLog = LiftLog::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $newerPR = PersonalRecord::factory()->create([
            'user_id' => $this->otherUser->id,
            'exercise_id' => $exercise->id,
            'lift_log_id' => $newerLiftLog->id,
            'achieved_at' => now()->subDays(1),
        ]);
        
        // View feed to mark both as read
        $this->actingAs($this->user)->get(route('feed.index'));
        
        // Verify both are marked as read
        $this->assertDatabaseHas('personal_record_reads', [
            'user_id' => $this->user->id,
            'personal_record_id' => $olderPR->id,
        ]);
        $this->assertDatabaseHas('personal_record_reads', [
            'user_id' => $this->user->id,
            'personal_record_id' => $newerPR->id,
        ]);
        
        // Manually mark the older PR as unread by deleting its read record
        \App\Models\PersonalRecordRead::where('user_id', $this->user->id)
            ->where('personal_record_id', $olderPR->id)
            ->delete();
        
        // View feed again
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        
        // Get the response content
        $content = $response->getContent();
        
        // Find positions of the PR IDs in the HTML (they appear in data attributes)
        $olderPRPosition = strpos($content, 'data-pr-id="' . $olderPR->id . '"');
        $newerPRPosition = strpos($content, 'data-pr-id="' . $newerPR->id . '"');
        
        // If data-pr-id doesn't exist, try finding the lift log IDs
        if ($olderPRPosition === false || $newerPRPosition === false) {
            $olderPRPosition = strpos($content, 'lift-log-' . $olderLiftLog->id);
            $newerPRPosition = strpos($content, 'lift-log-' . $newerLiftLog->id);
        }
        
        // Verify the older (unread) PR appears before the newer (read) PR
        $this->assertNotFalse($olderPRPosition, 'Older PR should be present in feed');
        $this->assertNotFalse($newerPRPosition, 'Newer PR should be present in feed');
        $this->assertLessThan($newerPRPosition, $olderPRPosition, 
            'Unread PR should appear before read PR in the feed');
    }

    /** @test */
    public function it_shows_fab_tooltip_for_users_without_connections()
    {
        // User has no connections
        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
        $response->assertSee('fab-tooltip');
    }

    /** @test */
    public function it_hides_fab_tooltip_for_users_with_following()
    {
        // User follows someone
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_for_users_with_followers()
    {
        // Someone follows the user
        $this->otherUser->follow($this->user);

        $response = $this->actingAs($this->user)->get(route('feed.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_shows_fab_tooltip_on_users_page_without_connections()
    {
        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_on_users_page_with_connections()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('feed.users'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_shows_fab_tooltip_on_notifications_page_without_connections()
    {
        $response = $this->actingAs($this->user)->get(route('feed.notifications'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_on_notifications_page_with_connections()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('feed.notifications'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }
}
