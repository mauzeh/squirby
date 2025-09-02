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
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/');

        // We are redirecting to the daily logs index route
        $response->assertStatus(200);
    }
}
