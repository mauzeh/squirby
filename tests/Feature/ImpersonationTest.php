<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $userToImpersonate;
    protected $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->givePermissionTo('impersonate.start');

        $this->userToImpersonate = User::factory()->create();

        $this->nonAdminUser = User::factory()->create();
    }

    /** @test */
    public function admin_can_impersonate_another_user()
    {
        $response = $this->actingAs($this->adminUser)->get(route('impersonate', $this->userToImpersonate->id));

        $response->assertRedirect('/'); // Redirect to root after impersonation
        $response->assertSessionHas('impersonated_by', $this->adminUser->id);
        $this->assertEquals($this->userToImpersonate->id, auth()->user()->id);
    }

    /** @test */
    public function impersonated_user_can_stop_impersonating()
    {
        // First, impersonate the user
        $this->actingAs($this->adminUser)->get(route('impersonate', $this->userToImpersonate->id));

        // Now, as the impersonated user, stop impersonating
        $response = $this->actingAs($this->userToImpersonate)->get(route('impersonate.leave'));

        $response->assertRedirect('/'); // Redirect to root after leaving impersonation
        $response->assertSessionMissing('impersonated_by');
        $this->assertEquals($this->adminUser->id, auth()->user()->id);
    }

    /** @test */
    public function non_impersonating_user_cannot_stop_impersonating()
    {
        $response = $this->actingAs($this->nonAdminUser)->get(route('impersonate.leave'));

        $response->assertStatus(403); // Should return 403 Forbidden
        $response->assertSessionMissing('impersonated_by');
        $this->assertEquals($this->nonAdminUser->id, auth()->user()->id);
    }
}
