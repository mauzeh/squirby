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
}
