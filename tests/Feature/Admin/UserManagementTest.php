<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $userWithUserViewPermission;
    protected $userWithoutUserViewPermission;
    protected $userWithUserCreatePermission;
    protected $userWithoutUserCreatePermission;
    protected $userWithUserUpdatePermission;
    protected $userWithoutUserUpdatePermission;
    protected $userWithUserDeletePermission;
    protected $userWithoutUserDeletePermission;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->userWithUserViewPermission = User::factory()->create();
        $this->userWithUserViewPermission->givePermissionTo('users.view');

        $this->userWithoutUserViewPermission = User::factory()->create();

        $this->userWithUserCreatePermission = User::factory()->create();
        $this->userWithUserCreatePermission->givePermissionTo('users.create');

        $this->userWithoutUserCreatePermission = User::factory()->create();

        $this->userWithUserUpdatePermission = User::factory()->create();
        $this->userWithUserUpdatePermission->givePermissionTo('users.update');

        $this->userWithoutUserUpdatePermission = User::factory()->create();

        $this->userWithUserDeletePermission = User::factory()->create();
        $this->userWithUserDeletePermission->givePermissionTo('users.delete');

        $this->userWithoutUserDeletePermission = User::factory()->create();
    }

    /** @test */
    public function admin_can_view_user_management_page()
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.users.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function user_with_users_view_permission_can_view_users()
    {
        $response = $this->actingAs($this->userWithUserViewPermission)->get(route('admin.users.index'));
        $response->assertStatus(200);
    }

    /** @test */
    public function user_without_users_view_permission_cannot_view_users()
    {
        $response = $this->actingAs($this->userWithoutUserViewPermission)->get(route('admin.users.index'));
        $response->assertStatus(403);
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

    /** @test */
    public function name_is_required_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('name');
    }

    /** @test */
    public function email_is_required_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('email');
    }

    /** @test */
    public function email_must_be_unique_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        User::factory()->create(['email' => 'existing@example.com']);
        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('email');
    }

    /** @test */
    public function email_must_be_valid_format_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('email');
    }

    /** @test */
    public function password_is_required_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('password');
    }

    /** @test */
    public function password_must_be_at_least_8_characters_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('password');
    }

    /** @test */
    public function password_must_be_confirmed_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $role = Role::findByName('athlete');

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'wrongpassword',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('password');
    }

    /** @test */
    public function roles_must_exist_when_adding_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [9999], // Non-existent role ID
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

        $response->assertInvalid('roles.0');
    }

    /** @test */
    public function email_is_required_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => '',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertInvalid('email');
    }

    /** @test */
    public function email_must_be_unique_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user1), [
            'name' => $user1->name,
            'email' => $user2->email,
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertInvalid('email');
    }

    /** @test */
    public function email_must_be_valid_format_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => 'invalid-email',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertInvalid('email');
    }

    /** @test */
    public function password_must_be_confirmed_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'wrongpassword',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertInvalid('password');
    }

    /** @test */
    public function roles_must_exist_when_updating_user()
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [9999], // Non-existent role ID
        ]);

        $response->assertInvalid('roles.0');
    }

    /** @test */
    public function user_with_users_create_permission_can_create_user()
    {
        $response = $this->actingAs($this->userWithUserCreatePermission)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /** @test */
    public function user_without_users_create_permission_cannot_create_user()
    {
        $response = $this->actingAs($this->userWithoutUserCreatePermission)->post(route('admin.users.store'), [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized@example.com',
        ]);
    }

    /** @test */
    public function user_with_users_update_permission_can_update_user()
    {
        $userToUpdate = User::factory()->create();
        $response = $this->actingAs($this->userWithUserUpdatePermission)->put(route('admin.users.update', $userToUpdate->id), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $userToUpdate->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /** @test */
    public function user_without_users_update_permission_cannot_update_user()
    {
        $userToUpdate = User::factory()->create();
        $response = $this->actingAs($this->userWithoutUserUpdatePermission)->put(route('admin.users.update', $userToUpdate->id), [
            'name' => 'Unauthorized Updated Name',
            'email' => 'unauthorized_updated@example.com',
            'roles' => [Role::findByName('athlete')->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized_updated@example.com',
        ]);
    }

    /** @test */
    public function user_with_users_delete_permission_can_delete_user()
    {
        $userToDelete = User::factory()->create();
        $response = $this->actingAs($this->userWithUserDeletePermission)->delete(route('admin.users.destroy', $userToDelete->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    /** @test */
    public function user_without_users_delete_permission_cannot_delete_user()
    {
        $userToDelete = User::factory()->create();
        $response = $this->actingAs($this->userWithoutUserDeletePermission)->delete(route('admin.users.destroy', $userToDelete->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $userToDelete->id]);
    }
}