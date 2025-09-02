<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Database\Factories\MeasurementLogFactory;
use Database\Factories\MeasurementTypeFactory;
use Illuminate\Support\Facades\Auth;

class MeasurementLogManagementTest extends TestCase
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
        $this->userWithViewPermission->givePermissionTo('measurement-logs.view');

        $this->userWithoutViewPermission = User::factory()->create();

        $this->userWithCreatePermission = User::factory()->create();
        $this->userWithCreatePermission->givePermissionTo('measurement-logs.create');

        $this->userWithoutCreatePermission = User::factory()->create();

        $this->userWithUpdatePermission = User::factory()->create();
        $this->userWithUpdatePermission->givePermissionTo('measurement-logs.update');

        $this->userWithoutUpdatePermission = User::factory()->create();

        $this->userWithDeletePermission = User::factory()->create();
        $this->userWithDeletePermission->givePermissionTo('measurement-logs.delete');

        $this->userWithoutDeletePermission = User::factory()->create();
    }

    /** @test */
    public function user_with_measurement_logs_view_permission_can_view_measurement_logs()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog = MeasurementLogFactory::new()->create(['user_id' => $this->userWithViewPermission->id, 'measurement_type_id' => $measurementType->id]);
        $response = $this->actingAs($this->userWithViewPermission)->get(route('measurement-logs.index'));
        $response->assertStatus(200);
        $response->assertSee($measurementLog->value);
    }

    /** @test */
    public function user_without_measurement_logs_view_permission_cannot_view_measurement_logs()
    {
        $response = $this->actingAs($this->userWithoutViewPermission)->get(route('measurement-logs.index'));
        $response->assertStatus(403);
    }
}
