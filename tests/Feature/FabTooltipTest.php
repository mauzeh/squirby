<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabTooltipTest extends TestCase
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
    public function it_shows_fab_tooltip_on_lifts_page_without_connections()
    {
        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_on_lifts_page_with_connections()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_shows_fab_tooltip_on_foods_page_without_connections()
    {
        $response = $this->actingAs($this->user)->get(route('mobile-entry.foods'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_on_foods_page_with_connections()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('mobile-entry.foods'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_shows_fab_tooltip_on_measurements_page_without_connections()
    {
        $response = $this->actingAs($this->user)->get(route('mobile-entry.measurements'));

        $response->assertStatus(200);
        $response->assertSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_on_measurements_page_with_connections()
    {
        $this->user->follow($this->otherUser);

        $response = $this->actingAs($this->user)->get(route('mobile-entry.measurements'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_hides_fab_tooltip_when_user_has_followers_but_not_following()
    {
        // Someone follows the user, but user isn't following anyone
        $this->otherUser->follow($this->user);

        $response = $this->actingAs($this->user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        $response->assertDontSee('Connect with friends');
    }

    /** @test */
    public function it_always_shows_fab_button_regardless_of_connections()
    {
        // Without connections
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertStatus(200);
        $response->assertSee('fa-user-plus');

        // With connections
        $this->user->follow($this->otherUser);
        $response = $this->actingAs($this->user)->get(route('feed.index'));
        $response->assertStatus(200);
        $response->assertSee('fa-user-plus');
    }
}
