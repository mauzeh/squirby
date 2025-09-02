<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @test */
    public function admin_can_view_user_management_page()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function password_must_be_at_least_8_characters_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '123',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function roles_are_required_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors('roles');
    }

    /** @test */
    public function name_is_required_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => '',
            'email' => $user->email,
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function admin_can_add_new_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);

        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue($newUser->hasRole('athlete'));
    }

    /** @test */
    public function admin_sees_success_message_after_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Another User',
            'email' => 'anotheruser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success', 'User created successfully.');
    }

    /** @test */
    public function roles_are_required_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $userData = [
            'name' => 'User Without Role',
            'email' => 'no_role_user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // 'roles' is intentionally omitted
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);
        $response->assertSessionHasErrors('roles');
        $response->assertInvalid('roles');
    }

    /** @test */
    public function admin_sees_success_message_after_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();
        $role = Role::findByName('athlete');

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'roles' => [$role->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success', 'User updated successfully.');
    }

    /** @test */
    public function admin_sees_success_message_after_deleting_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success', 'User deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}