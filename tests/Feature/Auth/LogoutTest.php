<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_logout_via_post_request()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /** @test */
    public function user_can_logout_via_get_request()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->get('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /** @test */
    public function guest_cannot_access_logout_routes()
    {
        $response = $this->post('/logout');
        $response->assertRedirect('/login');

        $response = $this->get('/logout');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function logout_invalidates_session()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);
        $sessionId = session()->getId();
        
        $this->post('/logout');
        
        // Start a new request to check session
        $this->get('/');
        $this->assertNotEquals($sessionId, session()->getId());
    }

    /** @test */
    public function logout_clears_authentication()
    {
        $user = User::factory()->create([
            'remember_token' => 'test_token'
        ]);
        
        $this->actingAs($user);
        
        $this->post('/logout');
        
        // Just verify user is logged out, remember token behavior may vary
        $this->assertGuest();
    }
}