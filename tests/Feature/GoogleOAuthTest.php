<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_redirects_to_google_oauth()
    {
        // Mock the redirect response
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
    }

    /** @test */
    public function it_creates_new_user_on_first_google_signin()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_123');
        $googleUser->shouldReceive('getName')->andReturn('John Doe');
        $googleUser->shouldReceive('getEmail')->andReturn('john@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'google_id' => 'google_123',
        ]);

        // Assert user has athlete role
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('Athlete'));

        // Assert user is logged in
        $this->assertAuthenticated();

        // Assert welcome message for new user
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionHas('success', 'Welcome to our app! Thanks for trying us out. We\'re excited to help you track your fitness journey!');
    }

    /** @test */
    public function it_logs_in_existing_user_with_google_id()
    {
        // Create existing user with Google ID
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'google_id' => 'google_456',
        ]);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_456');
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

        $response = $this->get(route('auth.google.callback'));

        // Assert user is logged in as existing user
        $this->assertAuthenticatedAs($existingUser);

        // Assert no welcome message for existing user
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionMissing('success');
    }

    /** @test */
    public function it_links_google_account_to_existing_email()
    {
        // Create existing user without Google ID
        $existingUser = User::factory()->create([
            'email' => 'link@example.com',
            'name' => 'Link User',
            'google_id' => null,
        ]);

        // Mock Google user data with same email
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_789');
        $googleUser->shouldReceive('getName')->andReturn('Link User');
        $googleUser->shouldReceive('getEmail')->andReturn('link@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert Google ID was added to existing user
        $existingUser->refresh();
        $this->assertEquals('google_789', $existingUser->google_id);

        // Assert user is logged in
        $this->assertAuthenticatedAs($existingUser);

        // Assert no welcome message (not a new user)
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionMissing('success');
    }

    /** @test */
    public function it_handles_google_oauth_failure()
    {
        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth failed'));

        $response = $this->get(route('auth.google.callback'));

        // Assert redirect to login with error
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Google authentication failed.');

        // Assert user is not logged in
        $this->assertGuest();
    }

    /** @test */
    public function it_prevents_duplicate_users_with_same_email()
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'email' => 'duplicate@example.com',
            'name' => 'Original User',
            'google_id' => null,
        ]);

        // Mock Google user with same email but different name
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_999');
        $googleUser->shouldReceive('getName')->andReturn('Different Name');
        $googleUser->shouldReceive('getEmail')->andReturn('duplicate@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert only one user exists with this email
        $this->assertEquals(1, User::where('email', 'duplicate@example.com')->count());

        // Assert the existing user was updated with Google ID
        $existingUser->refresh();
        $this->assertEquals('google_999', $existingUser->google_id);
        $this->assertEquals('Original User', $existingUser->name); // Name should not change

        // Assert user is logged in
        $this->assertAuthenticatedAs($existingUser);
    }

    /** @test */
    public function it_generates_random_password_for_new_google_users()
    {
        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_password_test');
        $googleUser->shouldReceive('getName')->andReturn('Password Test');
        $googleUser->shouldReceive('getEmail')->andReturn('password@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $this->get(route('auth.google.callback'));

        $user = User::where('email', 'password@example.com')->first();
        
        // Assert password is not null and is hashed
        $this->assertNotNull($user->password);
        $this->assertTrue(password_verify('test', $user->password) === false); // Should not be 'test'
        $this->assertStringStartsWith('$2y$', $user->password); // Should be bcrypt hash
    }

    /** @test */
    public function it_assigns_athlete_role_to_new_users()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_role_test');
        $googleUser->shouldReceive('getName')->andReturn('Role Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('roletest@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created with athlete role
        $user = User::where('email', 'roletest@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Athlete'));
        $this->assertFalse($user->hasRole('admin'));

        $response->assertRedirect('/mobile-entry/lifts');
    }

    /** @test */
    public function it_logs_oauth_errors_for_debugging()
    {
        // Mock logger
        \Log::shouldReceive('error')
            ->once()
            ->with(
                'Google OAuth failed: Test exception',
                Mockery::type('array')
            );

        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Google authentication failed.');
    }
}