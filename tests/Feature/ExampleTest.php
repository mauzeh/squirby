<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\App;
use App\Models\User;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the root URL redirects correctly based on authentication status.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Test unauthenticated redirection
        $response = $this->get('/');
        $response->assertRedirect('/login');

        // Test authenticated redirection
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('mobile-entry.lifts'));
    }
}
