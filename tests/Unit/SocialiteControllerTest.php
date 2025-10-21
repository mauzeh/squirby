<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\SocialiteController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SocialiteController();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function redirect_to_google_returns_redirect_response()
    {
        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $response = $this->controller->redirectToGoogle();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function handle_google_callback_creates_new_user_successfully()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'athlete']);

        // Mock Google user
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('123456');
        $googleUser->shouldReceive('getName')->andReturn('Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('test@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        // Mock Auth
        Auth::shouldReceive('login')
            ->once()
            ->with(Mockery::type(User::class));

        $response = $this->controller->handleGoogleCallback();

        // Assert database has new user
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'google_id' => '123456',
        ]);

        // Assert user has athlete role
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('athlete'));

        // Assert redirect with success message
        $this->assertStringEndsWith('/lift-logs/mobile-entry', $response->getTargetUrl());
        $this->assertEquals('Welcome to our app! Thanks for trying us out. We\'re excited to help you track your fitness journey!', 
                           $response->getSession()->get('success'));
    }

    /** @test */
    public function handle_google_callback_links_existing_email_account()
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        // Mock Google user with same email
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('789012');
        $googleUser->shouldReceive('getName')->andReturn('Existing User');
        $googleUser->shouldReceive('getEmail')->andReturn('existing@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        // Mock Auth
        Auth::shouldReceive('login')
            ->once()
            ->with($existingUser);

        $response = $this->controller->handleGoogleCallback();

        // Assert Google ID was added
        $existingUser->refresh();
        $this->assertEquals('789012', $existingUser->google_id);

        // Assert existing user doesn't get athlete role assigned again
        // (This assumes the existing user doesn't have the role, or we're not changing their roles)

        // Assert redirect without welcome message
        $this->assertStringEndsWith('/lift-logs/mobile-entry', $response->getTargetUrl());
        $this->assertNull($response->getSession()->get('success'));
    }

    /** @test */
    public function handle_google_callback_logs_in_existing_google_user()
    {
        // Create existing user with Google ID
        $existingUser = User::factory()->create([
            'google_id' => '345678',
        ]);

        // Mock Google user
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('345678');
        $googleUser->shouldReceive('getName')->andReturn('Existing Google User');
        $googleUser->shouldReceive('getEmail')->andReturn($existingUser->email);

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        // Mock Auth
        Auth::shouldReceive('login')
            ->once()
            ->with($existingUser);

        $response = $this->controller->handleGoogleCallback();

        // Assert redirect without welcome message
        $this->assertStringEndsWith('/lift-logs/mobile-entry', $response->getTargetUrl());
        $this->assertNull($response->getSession()->get('success'));
    }

    /** @test */
    public function handle_google_callback_handles_exceptions_gracefully()
    {
        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth error'));

        // Mock Log
        \Log::shouldReceive('error')
            ->once()
            ->with(
                'Google OAuth failed: OAuth error',
                Mockery::type('array')
            );

        $response = $this->controller->handleGoogleCallback();

        // Assert redirect to login with error
        $this->assertEquals('/login', $response->getTargetUrl());
        $this->assertEquals('Google authentication failed.', 
                           $response->getSession()->get('error'));
    }

    /** @test */
    public function new_users_get_random_password()
    {
        // Mock Google user
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('random_pass_test');
        $googleUser->shouldReceive('getName')->andReturn('Random Pass User');
        $googleUser->shouldReceive('getEmail')->andReturn('randompass@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        // Mock Auth
        Auth::shouldReceive('login')
            ->once()
            ->with(Mockery::type(User::class));

        $this->controller->handleGoogleCallback();

        $user = User::where('email', 'randompass@example.com')->first();
        
        // Assert password exists and is hashed
        $this->assertNotNull($user->password);
        $this->assertStringStartsWith('$2y$', $user->password);
    }
}