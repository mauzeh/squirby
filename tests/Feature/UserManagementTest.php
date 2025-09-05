<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_can_assign_and_unassign_roles_to_a_user()
    {
        $newRole = Role::factory()->create(['name' => 'New Role']);

        $response = $this->actingAs($this->admin)->put(route('users.update', $this->athlete), [
            'roles' => [$newRole->id],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertTrue($this->athlete->fresh()->hasRole('New Role'));

        $response = $this->actingAs($this->admin)->put(route('users.update', $this->athlete), [
            'roles' => [],
        ]);

        $this->assertFalse($this->athlete->fresh()->hasRole('New Role'));
    }
}
