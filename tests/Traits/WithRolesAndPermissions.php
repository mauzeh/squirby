<?php

namespace Tests\Traits;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

trait WithRolesAndPermissions
{
    protected function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    protected function createUserWithPermission(string $permissionName): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissionName);
        return $user;
    }

    protected function createUserWithoutPermission(string $permissionName): User
    {
        return User::factory()->create();
    }

    protected function createRole(string $roleName): Role
    {
        return Role::create(['name' => $roleName]);
    }

    protected function createPermission(string $permissionName): Permission
    {
        return Permission::create(['name' => $permissionName]);
    }

    protected function assignPermissionsToRole(Role $role, array $permissions)
    {
        $role->syncPermissions($permissions);
    }
}
