<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // create permissions
        $permissions = [
            'view-dashboard',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.assign',
            'daily-logs.view',
            'daily-logs.create',
            'daily-logs.update',
            'daily-logs.delete',
            'meals.view',
            'meals.create',
            'meals.update',
            'meals.delete',
            'ingredients.view',
            'ingredients.create',
            'ingredients.update',
            'ingredients.delete',
            'workouts.view',
            'workouts.create',
            'workouts.update',
            'workouts.delete',
            'exercises.view',
            'exercises.create',
            'exercises.update',
            'exercises.delete',
            'measurement-logs.view',
            'measurement-logs.create',
            'measurement-logs.update',
            'measurement-logs.delete',
            'measurement-types.view',
            'measurement-types.create',
            'measurement-types.update',
            'measurement-types.delete',
            'impersonate.start',
            'impersonate.stop',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // create roles and assign created permissions

        $role = Role::create(['name' => 'Athlete'])
            ->givePermissionTo([
                'workouts.view',
                'workouts.create',
                'workouts.update',
                'workouts.delete',
                'exercises.view',
                'exercises.create',
                'exercises.update',
                'exercises.delete',
            ]);

        $role = Role::create(['name' => 'Nutrition Ninja'])
            ->givePermissionTo([
                'daily-logs.view',
                'daily-logs.create',
                'daily-logs.update',
                'daily-logs.delete',
                'meals.view',
                'meals.create',
                'meals.update',
                'meals.delete',
                'ingredients.view',
                'ingredients.create',
                'ingredients.update',
                'ingredients.delete',
                'measurement-logs.view',
                'measurement-logs.create',
                'measurement-logs.update',
                'measurement-logs.delete',
                'measurement-types.view',
                'measurement-types.create',
                'measurement-types.update',
                'measurement-types.delete',
            ]);

        $role = Role::create(['name' => 'Admin']);
        $role->givePermissionTo(Permission::all());
    }
}