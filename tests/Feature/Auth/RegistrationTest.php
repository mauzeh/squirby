<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('mobile-entry.lifts', absolute: false));
    }

    public function test_new_users_have_correct_exercise_preferences(): void
    {
        // First seed the database with required data
        $this->seed(\Database\Seeders\UnitSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\IngredientSeeder::class);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        
        $user = auth()->user();
        
        // Verify exercise preferences are set correctly
        $this->assertTrue($user->show_global_exercises);
        $this->assertTrue($user->show_extra_weight);
        $this->assertFalse($user->prefill_suggested_values); // OFF for new users
        $this->assertTrue($user->show_recommended_exercises);
        $this->assertTrue($user->metrics_first_logging_flow);
    }
}
