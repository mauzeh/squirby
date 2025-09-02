<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');

        $this->nonAdminUser = User::factory()->create();
    }

    /** @test */
    public function admin_can_view_roles_index_page()
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.roles.index'));
        $response->assertStatus(200);
        $response->assertSee('Roles');
    }

    /** @test */
    public function user_without_permission_cannot_view_roles_index_page()
    {
        $response = $this->actingAs($this->nonAdminUser)->get(route('admin.roles.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_a_role()
    {
        $this->actingAs($this->adminUser);

        $roleData = [
            'name' => 'new_role',
            'permissions' => Permission::pluck('id')->toArray(),
        ];

        $response = $this->post(route('admin.roles.store'), $roleData);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'new_role']);
    }

    /** @test */
    public function user_without_permission_cannot_create_a_role()
    {
        $this->actingAs($this->nonAdminUser);

        $roleData = [
            'name' => 'unauthorized_role',
        ];

        $response = $this->post(route('admin.roles.store'), $roleData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'unauthorized_role']);
    }

    /** @test */
    public function admin_can_update_a_role()
    {
        $this->actingAs($this->adminUser);
        $role = Role::create(['name' => 'role_to_update']);
        $permission = Permission::create(['name' => 'test_permission']);
        $role->givePermissionTo($permission);

        $updatedRoleData = [
            'name' => 'updated_role',
            'permissions' => [Permission::findByName('daily-logs.view')->id],
        ];

        $response = $this->put(route('admin.roles.update', $role->id), $updatedRoleData);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'updated_role']);
        $updatedRole = Role::findByName('updated_role');
        $this->assertTrue($updatedRole->hasPermissionTo('daily-logs.view'));
        $this->assertFalse($updatedRole->hasPermissionTo('test_permission'));
    }

    /** @test */
    public function user_without_permission_cannot_update_a_role()
    {
        $this->actingAs($this->nonAdminUser);
        $role = Role::create(['name' => 'another_role_to_update']);

        $updatedRoleData = [
            'name' => 'unauthorized_updated_role',
            'permissions' => [],
        ];

        $response = $this->put(route('admin.roles.update', $role->id), $updatedRoleData);

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['name' => 'another_role_to_update']);
        $this->assertDatabaseMissing('roles', ['name' => 'unauthorized_updated_role']);
    }

    /** @test */
    public function admin_can_delete_a_role()
    {
        $this->actingAs($this->adminUser);
        $role = Role::create(['name' => 'role_to_delete']);

        $response = $this->delete(route('admin.roles.destroy', $role->id));

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['name' => 'role_to_delete']);
    }

    /** @test */
    public function user_without_permission_cannot_delete_a_role()
    {
        $this->actingAs($this->nonAdminUser);
        $role = Role::create(['name' => 'unauthorized_role_to_delete']);

        $response = $this->delete(route('admin.roles.destroy', $role->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['name' => 'unauthorized_role_to_delete']);
    }

    /** @test */
    public function admin_can_assign_permissions_to_role_when_updating()
    {
        $this->actingAs($this->adminUser);
        $role = Role::create(['name' => 'role_with_permissions']);
        $permission1 = Permission::create(['name' => 'permission_to_assign_1']);
        $permission2 = Permission::create(['name' => 'permission_to_assign_2']);

        $updatedRoleData = [
            'name' => 'role_with_permissions',
            'permissions' => [$permission1->id, $permission2->id],
        ];

        $response = $this->put(route('admin.roles.update', $role->id), $updatedRoleData);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertTrue($role->fresh()->hasPermissionTo('permission_to_assign_1'));
        $this->assertTrue($role->fresh()->hasPermissionTo('permission_to_assign_2'));
    }

    /** @test */
    public function user_without_permission_cannot_assign_permissions_to_role_when_updating()
    {
        $this->actingAs($this->nonAdminUser);
        $role = Role::create(['name' => 'role_without_assign_permission']);
        $permission = Permission::create(['name' => 'permission_not_assigned']);

        $updatedRoleData = [
            'name' => 'role_without_assign_permission',
            'permissions' => [$permission->id],
        ];

        $response = $this->put(route('admin.roles.update', $role->id), $updatedRoleData);

        $response->assertStatus(403);
        $this->assertFalse($role->fresh()->hasPermissionTo('permission_not_assigned'));
    }
}
