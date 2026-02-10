<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_connection_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('profile.generate-connection-token'));

        $response->assertRedirect(route('profile.edit'));
        
        $user->refresh();
        $this->assertNotNull($user->connection_token);
        $this->assertNotNull($user->connection_token_expires_at);
        $this->assertEquals(6, strlen($user->connection_token));
    }

    public function test_connection_token_is_displayed_on_profile_page(): void
    {
        $user = User::factory()->create();
        $user->generateConnectionToken();

        $this->actingAs($user);

        $response = $this->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('Connect with Friends');
        $response->assertSee($user->connection_token);
    }

    public function test_user_can_connect_via_valid_token(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        $token = $user1->generateConnectionToken();

        $this->actingAs($user2);

        $response = $this->post(route('profile.connect-via-token', ['token' => $token]));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        
        // Check mutual follow
        $this->assertTrue($user2->isFollowing($user1));
        $this->assertTrue($user1->isFollowing($user2));
    }

    public function test_cannot_connect_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('profile.connect-via-token', ['token' => '999999']));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error', 'Invalid or expired connection code.');
    }

    public function test_cannot_connect_with_expired_token(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token = $user1->generateConnectionToken();
        
        // Manually expire the token
        $user1->update(['connection_token_expires_at' => now()->subMinutes(1)]);

        $this->actingAs($user2);

        $response = $this->post(route('profile.connect-via-token', ['token' => $token]));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error', 'Invalid or expired connection code.');
    }

    public function test_cannot_connect_with_self(): void
    {
        $user = User::factory()->create();
        $token = $user->generateConnectionToken();

        $this->actingAs($user);

        $response = $this->post(route('profile.connect-via-token', ['token' => $token]));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error', 'You cannot connect with yourself.');
    }

    public function test_get_valid_connection_token_returns_existing_valid_token(): void
    {
        $user = User::factory()->create();
        
        $token1 = $user->getValidConnectionToken();
        $token2 = $user->getValidConnectionToken();

        $this->assertEquals($token1, $token2);
    }

    public function test_get_valid_connection_token_generates_new_token_when_expired(): void
    {
        $user = User::factory()->create();
        
        $token1 = $user->generateConnectionToken();
        
        // Expire the token
        $user->update(['connection_token_expires_at' => now()->subMinutes(1)]);
        
        $token2 = $user->getValidConnectionToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_connection_creates_mutual_follow(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        $token = $user1->generateConnectionToken();

        $this->actingAs($user2);
        $this->post(route('profile.connect-via-token', ['token' => $token]));

        // Both users should follow each other
        $this->assertTrue($user1->isFollowing($user2));
        $this->assertTrue($user2->isFollowing($user1));
        
        // Check counts
        $this->assertEquals(1, $user1->following()->count());
        $this->assertEquals(1, $user1->followers()->count());
        $this->assertEquals(1, $user2->following()->count());
        $this->assertEquals(1, $user2->followers()->count());
    }

    public function test_connecting_twice_does_not_create_duplicates(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token = $user1->generateConnectionToken();

        $this->actingAs($user2);
        $this->post(route('profile.connect-via-token', ['token' => $token]));
        
        // Generate new token and connect again
        $token2 = $user1->generateConnectionToken();
        $this->post(route('profile.connect-via-token', ['token' => $token2]));

        // Should still only have 1 follow relationship each way
        $this->assertEquals(1, $user1->following()->count());
        $this->assertEquals(1, $user2->following()->count());
    }
}
