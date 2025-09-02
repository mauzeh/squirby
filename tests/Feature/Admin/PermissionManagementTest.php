<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->nonAdminUser = User::factory()->create();
    }

    /** @test */
    public function admin_can_view_permissions_index_page()
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.permissions.index'));
        $response->assertStatus(200);
        $response->assertSee('Permissions');
    }

    /** @test */
    public function user_without_permission_cannot_view_permissions_index_page()
    {
        $response = $this->actingAs($this->nonAdminUser)->get(route('admin.permissions.index'));
        $response->assertStatus(403);
    }
}
