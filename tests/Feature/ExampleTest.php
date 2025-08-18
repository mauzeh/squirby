<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // We are redirecting to the daily logs index route
        $response->assertRedirect(route('daily-logs.index'));
        $response->assertStatus(302);
    }
}
