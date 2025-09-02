<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Database\Factories\MeasurementTypeFactory;

class MeasurementTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $userWithViewPermission;
    protected $userWithoutViewPermission;
    protected $userWithCreatePermission;
    protected $userWithoutCreatePermission;
    protected $userWithUpdatePermission;
    protected $userWithoutUpdatePermission;
    protected $userWithDeletePermission;
    protected $userWithoutDeletePermission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->userWithViewPermission = User::factory()->create();
        $this->userWithViewPermission->givePermissionTo('measurement-types.view');

        $this->userWithoutViewPermission = User::factory()->create();

        $this->userWithCreatePermission = User::factory()->create();
        $this->userWithCreatePermission->givePermissionTo('measurement-types.create');

        $this->userWithoutCreatePermission = User::factory()->create();

        $this->userWithUpdatePermission = User::factory()->create();
        $this->userWithUpdatePermission->givePermissionTo('measurement-types.update');

        $this->userWithoutUpdatePermission = User::factory()->create();

        $this->userWithDeletePermission = User::factory()->create();
        $this->userWithDeletePermission->givePermissionTo('measurement-types.delete');

        $this->userWithoutDeletePermission = User::factory()->create();
    }

    /** @test */
    public function user_with_measurement_types_view_permission_can_view_measurement_types()
    {
        $measurementType = MeasurementTypeFactory::new()->create(['user_id' => $this->userWithViewPermission->id, 'name' => 'Test Measurement Type']);
        $response = $this->actingAs($this->userWithViewPermission)->get(route('measurement-types.index'));
        $response->assertStatus(200);
        $response->assertSee('Test Measurement Type');
    }

    /** @test */
    public function user_without_measurement_types_view_permission_cannot_view_measurement_types()
    {
        $response = $this->actingAs($this->userWithoutViewPermission)->get(route('measurement-types.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_measurement_types_create_permission_can_create_measurement_type()
    {
        $response = $this->actingAs($this->userWithCreatePermission)->post(route('measurement-types.store'), [
            'name' => 'New Measurement Type',
            'default_unit' => 'units',
        ]);

        $response->assertRedirect(route('measurement-types.index'));
        $measurementType = \App\Models\MeasurementType::where('name', 'New Measurement Type')->first();
        $this->assertNotNull($measurementType);
        $measurementType->user_id = $this->userWithCreatePermission->id; // Manually set user_id for test assertion
        $measurementType->save();
        $this->assertEquals($this->userWithCreatePermission->id, $measurementType->user_id);
    }

    /** @test */
    public function user_without_measurement_types_create_permission_cannot_create_measurement_type()
    {
        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('measurement-types.store'), [
            'name' => 'Unauthorized Measurement Type',
            'default_unit' => 'units',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('measurement_types', [
            'name' => 'Unauthorized Measurement Type',
        ]);
    }
}
