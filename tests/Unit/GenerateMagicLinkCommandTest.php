<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\MagicLoginToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class GenerateMagicLinkCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_a_magic_link_with_25_uses_remaining()
    {
        // 1. Create a User
        $user = User::factory()->create();

        // 2. Call the Command
        $this->artisan('app:generate-magic-link', ['userId' => $user->id])
             ->assertExitCode(0);

        // 3. Assert MagicLoginToken Creation and uses_remaining value
        $token = MagicLoginToken::where('user_id', $user->id)->first();

        $this->assertNotNull($token, 'MagicLoginToken was not created.');
        $this->assertEquals(25, $token->uses_remaining, 'uses_remaining is not 25.');
    }

    /** @test */
    public function it_returns_an_error_if_user_not_found()
    {
        $this->artisan('app:generate-magic-link', ['userId' => 999])
             ->assertExitCode(0) // Commands usually exit with 0 even on error for display
             ->expectsOutput('User not found.');

        $this->assertDatabaseCount('magic_login_tokens', 0);
    }
}
