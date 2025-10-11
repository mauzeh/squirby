<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $athlete;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $athleteRole = Role::factory()->create(['name' => 'Athlete']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);

        $this->athlete = User::factory()->create();
        $this->athlete->roles()->attach($athleteRole);
    }

    public function test_admin_can_view_user_list()
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee($this->athlete->name);
    }

    public function test_non_admin_is_redirected_from_user_list()
    {
        $response = $this->actingAs($this->athlete)->get(route('users.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_edit_page_for_a_user()
    {
        $response = $this->actingAs($this->admin)->get(route('users.edit', $this->athlete));

        $response->assertStatus(200);
        $response->assertSee($this->athlete->name);
    }

    public function test_admin_can_assign_a_role_to_a_user()
    {
        $newRole = Role::factory()->create(['name' => 'New Role']);

        $response = $this->actingAs($this->admin)->put(route('users.update', $this->athlete), [
            'name' => $this->athlete->name,
            'email' => $this->athlete->email,
            'roles' => [$newRole->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertTrue($this->athlete->fresh()->hasRole('New Role'));
    }

    public function test_admin_can_unassign_a_role_from_a_user()
    {
        $roleToUnassign = Role::factory()->create(['name' => 'Role To Unassign']);
        $this->athlete->roles()->attach($roleToUnassign);

        $response = $this->actingAs($this->admin)->put(route('users.update', $this->athlete), [
            'name' => $this->athlete->name,
            'email' => $this->athlete->email,
            'roles' => [],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertFalse($this->athlete->fresh()->hasRole('Role To Unassign'));
    }

    public function test_admin_can_create_a_user()
    {
        $role = Role::factory()->create();
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $userData);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
        $this->assertTrue(User::where('email', 'newuser@example.com')->first()->hasRole($role->name));
    }

    public function test_admin_can_delete_a_user()
    {
        $userToDelete = User::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $userToDelete));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_admin_can_update_a_users_name_email_and_password()
    {
        $userToUpdate = User::factory()->create();
        $role = Role::factory()->create();
        $userToUpdate->roles()->attach($role);

        $updatedData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->put(route('users.update', $userToUpdate), $updatedData);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $userToUpdate->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
        $this->assertTrue(Hash::check('newpassword', $userToUpdate->fresh()->password));
    }

    public function test_password_confirmation_is_required_when_creating_a_user()
    {
        $role = Role::factory()->create();
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'wrongpassword',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $userData);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_must_be_at_least_8_characters_when_creating_a_user()
    {
        $role = Role::factory()->create();
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $userData);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_confirmation_is_required_when_updating_a_user()
    {
        $userToUpdate = User::factory()->create();
        $role = Role::factory()->create();
        $userToUpdate->roles()->attach($role);

        $updatedData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'newpassword',
            'password_confirmation' => 'wrongpassword',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->put(route('users.update', $userToUpdate), $updatedData);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_must_be_at_least_8_characters_when_updating_a_user()
    {
        $userToUpdate = User::factory()->create();
        $role = Role::factory()->create();
        $userToUpdate->roles()->attach($role);

        $updatedData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
            'roles' => [$role->id],
        ];

        $response = $this->actingAs($this->admin)->put(route('users.update', $userToUpdate), $updatedData);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_can_impersonate_a_user()
    {
        $response = $this->actingAs($this->admin)->get(route('users.impersonate', $this->athlete));

        $response->assertRedirect('/');
        $this->assertEquals(auth()->id(), $this->athlete->id);
    }

    public function test_non_admin_cannot_impersonate_a_user()
    {
        $response = $this->actingAs($this->athlete)->get(route('users.impersonate', $this->admin));

        $response->assertStatus(403);
    }

    public function test_switch_back_banner_is_visible_when_impersonating()
    {
        $this->actingAs($this->admin)->get(route('users.impersonate', $this->athlete));

        $response = $this->get(route('food-logs.index'));

        $response->assertSee('You are currently impersonating ' . $this->athlete->name);
        $response->assertSee('Switch Back');
    }

    public function test_user_can_switch_back_to_admin_account()
    {
        $this->actingAs($this->admin)->get(route('users.impersonate', $this->athlete));

        $response = $this->get(route('users.leave-impersonate'));

        $response->assertRedirect(route('users.index'));
        $this->assertEquals(auth()->id(), $this->admin->id);
    }

    public function test_new_user_has_access_to_global_exercises()
    {
        // Ensure global exercises exist (from seeder)
        $this->seed(\Database\Seeders\GlobalExercisesSeeder::class);
        
        $role = Role::factory()->create();
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->id],
        ];

        $this->actingAs($this->admin)->post(route('users.store'), $userData);

        $newUser = User::where('email', 'newuser@example.com')->first();

        // New users should not have personal exercises created
        $this->assertCount(0, $newUser->exercises);
        
        // But they should have access to global exercises
        $availableExercises = \App\Models\Exercise::availableToUser($newUser->id)->get();
        $this->assertGreaterThan(0, $availableExercises->count());
        
        // Verify global exercises exist and are accessible
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Back Squat']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Bench Press']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Deadlift']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Strict Press']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Power Clean']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Half-Kneeling DB Press']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Cyclist Squat (Barbell, Front Rack)']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Chin-Ups']);
        $this->assertDatabaseHas('exercises', ['user_id' => null, 'title' => 'Pull-Ups']);
    }
}
