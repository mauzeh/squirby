<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function complete_new_user_signup_workflow()
    {
        // Step 1: Visit login page
        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
        $loginResponse->assertSee('Sign in with Google');

        // Step 2: Click Google sign-in (redirect to Google)
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $redirectResponse = $this->get(route('auth.google'));
        $redirectResponse->assertRedirect();

        // Step 3: Google callback with new user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('new_user_123');
        $googleUser->shouldReceive('getName')->andReturn('New User');
        $googleUser->shouldReceive('getEmail')->andReturn('newuser@example.com');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $callbackResponse = $this->get(route('auth.google.callback'));

        // Step 4: Verify user creation and redirect
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'google_id' => 'new_user_123',
        ]);

        $callbackResponse->assertRedirect('/mobile-entry/lifts');
        $callbackResponse->assertSessionHas('success');

        // Step 5: Verify user is authenticated
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertAuthenticatedAs($user);
    }
}