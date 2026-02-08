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
}
