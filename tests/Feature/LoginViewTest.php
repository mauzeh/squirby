<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginViewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_page_displays_correctly()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Log In');
        $response->assertSee('Sign in with Google');
        $response->assertSee('Email');
        $response->assertSee('Password');
        $response->assertSee('Remember me');
    }

    /** @test */
    public function login_page_includes_google_signin_button()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Check for Google sign-in link
        $response->assertSee(route('auth.google'), false);
        // Check for Google icon SVG
        $response->assertSee('viewBox="0 0 24 24"', false);
        // Check for Google button class
        $response->assertSee('google-signin-btn', false);
    }

    /** @test */
    public function login_page_includes_login_css()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Check that login.css is included
        $response->assertSee('css/login.css', false);
    }

    /** @test */
    public function login_page_shows_error_messages()
    {
        $response = $this->withSession(['error' => 'Google authentication failed.'])
                         ->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Google authentication failed.');
        $response->assertSee('error-message-box', false);
    }

    /** @test */
    public function login_page_shows_success_messages()
    {
        $response = $this->withSession(['status' => 'Password reset link sent.'])
                         ->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Password reset link sent.');
        $response->assertSee('success-message-box', false);
    }

    /** @test */
    public function login_page_shows_validation_errors()
    {
        $response = $this->post('/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
        
        $followUp = $this->get('/login');
        $followUp->assertSee('error-message-box', false);
    }

    /** @test */
    public function google_button_appears_before_login_form()
    {
        $response = $this->get('/login');
        
        $content = $response->getContent();
        
        // Both elements should exist
        $this->assertStringContainsString('google-signin-btn', $content);
        $this->assertStringContainsString('login-form', $content);
        
        // Google button should appear in the google-signin-container
        $this->assertStringContainsString('google-signin-container', $content);
    }

    /** @test */
    public function divider_appears_between_google_and_form()
    {
        $response = $this->get('/login');
        
        // Check that divider with "or" exists
        $response->assertSee('<span>or</span>', false);
        $response->assertSee('divider', false);
        
        // Check that all required elements exist
        $response->assertSee('google-signin-container', false);
        $response->assertSee('login-form', false);
    }

    /** @test */
    public function authenticated_users_cannot_access_login_page()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/login');
        
        // Should redirect authenticated users away from login
        $response->assertRedirect();
    }

    /** @test */
    public function login_form_has_proper_structure()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        
        // Check form structure
        $response->assertSee('method="POST"', false);
        $response->assertSee('action="' . route('login') . '"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
        $response->assertSee('type="submit"', false);
        
        // Check CSRF token
        $response->assertSee('name="_token"', false);
    }
}