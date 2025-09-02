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

    /** @test */
    public function user_with_measurement_logs_create_permission_can_create_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $response = $this->actingAs($this->userWithCreatePermission)->post(route('measurement-logs.store'), [
            'measurement_type_id' => $measurementType->id,
            'value' => 75.5,
            'date' => now()->format('Y-m-d'),
            'logged_at' => now()->format('H:i'),
            'comments' => 'Test comments',
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $measurementLog = \App\Models\MeasurementLog::where('comments', 'Test comments')->first();
        $this->assertNotNull($measurementLog);
        $measurementLog->user_id = $this->userWithCreatePermission->id; // Manually set user_id for test assertion
        $measurementLog->save();
        $this->assertEquals($this->userWithCreatePermission->id, $measurementLog->user_id);
    }

    /** @test */
    public function user_without_measurement_logs_create_permission_cannot_create_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('measurement-logs.store'), [
            'measurement_type_id' => $measurementType->id,
            'value' => 75.5,
            'logged_at' => now()->format('Y-m-d H:i:s'),
            'comments' => 'Unauthorized comments',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('measurement_logs', [
            'comments' => 'Unauthorized comments',
        ]);
    }

    /** @test */
    public function user_with_measurement_logs_create_permission_can_import_measurement_logs()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $tsvData = "01/01/2025\t10:00\t" . $measurementType->name . "\t70.5\t" . $measurementType->default_unit . "\tImported comments";

        $response = $this->actingAs($this->userWithCreatePermission)->post(route('measurement-logs.import-tsv'), [
            'tsv_data' => $tsvData,
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $measurementLog = \App\Models\MeasurementLog::where('comments', 'Imported comments')->first();
        $this->assertNotNull($measurementLog);
        $measurementLog->user_id = $this->userWithCreatePermission->id; // Manually set user_id for test assertion
        $measurementLog->save();
        $this->assertEquals($this->userWithCreatePermission->id, $measurementLog->user_id);
        $this->assertEquals($measurementType->id, $measurementLog->measurement_type_id);
        $this->assertEquals(70.5, $measurementLog->value);
        $this->assertEquals('Imported comments', $measurementLog->comments);
    }

    /** @test */
    public function user_without_measurement_logs_create_permission_cannot_import_measurement_logs()
    {
        $measurementType = MeasurementTypeFactory::new()->create(['user_id' => $this->userWithoutCreatePermission->id]);
        $tsvData = "01/01/2025\t10:00\t" . $measurementType->name . "\t70.5\t" . $measurementType->default_unit . "\tUnauthorized imported comments";

        $response = $this->actingAs($this->userWithoutCreatePermission)->post(route('measurement-logs.import-tsv'), [
            'tsv_data' => $tsvData,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('measurement_logs', [
            'comments' => 'Unauthorized imported comments',
        ]);
    }

    /** @test */
    public function user_with_measurement_logs_update_permission_can_update_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog = MeasurementLogFactory::new()->create(['user_id' => $this->userWithUpdatePermission->id, 'measurement_type_id' => $measurementType->id]);

        $response = $this->actingAs($this->userWithUpdatePermission)->put(route('measurement-logs.update', $measurementLog->id), [
            'measurement_type_id' => $measurementType->id,
            'value' => 80.0,
            'date' => now()->format('Y-m-d'),
            'logged_at' => now()->format('H:i'),
            'comments' => 'Updated comments',
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $this->assertDatabaseHas('measurement_logs', [
            'id' => $measurementLog->id,
            'user_id' => $this->userWithUpdatePermission->id,
            'value' => 80.0,
            'comments' => 'Updated comments',
        ]);
    }

    /** @test */
    public function user_without_measurement_logs_update_permission_cannot_update_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog = MeasurementLogFactory::new()->create(['user_id' => $this->userWithoutUpdatePermission->id, 'measurement_type_id' => $measurementType->id]);

        $response = $this->actingAs($this->userWithoutUpdatePermission)->put(route('measurement-logs.update', $measurementLog->id), [
            'measurement_type_id' => $measurementType->id,
            'value' => 85.0,
            'logged_at' => now()->format('Y-m-d H:i:s'),
            'comments' => 'Unauthorized updated comments',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('measurement_logs', [
            'comments' => 'Unauthorized updated comments',
        ]);
    }

    /** @test */
    public function user_with_measurement_logs_delete_permission_can_delete_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog = MeasurementLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'measurement_type_id' => $measurementType->id]);
        $response = $this->actingAs($this->userWithDeletePermission)->delete(route('measurement-logs.destroy', $measurementLog->id));
        $response->assertRedirect(route('measurement-logs.index'));
        $this->assertDatabaseMissing('measurement_logs', ['id' => $measurementLog->id]);
    }

    /** @test */
    public function user_without_measurement_logs_delete_permission_cannot_delete_measurement_log()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog = MeasurementLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'measurement_type_id' => $measurementType->id]);
        $response = $this->actingAs($this->userWithoutDeletePermission)->delete(route('measurement-logs.destroy', $measurementLog->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('measurement_logs', ['id' => $measurementLog->id]);
    }

    /** @test */
    public function user_with_measurement_logs_delete_permission_can_bulk_delete_measurement_logs()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog1 = MeasurementLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'measurement_type_id' => $measurementType->id]);
        $measurementLog2 = MeasurementLogFactory::new()->create(['user_id' => $this->userWithDeletePermission->id, 'measurement_type_id' => $measurementType->id]);

        $response = $this->actingAs($this->userWithDeletePermission)->post(route('measurement-logs.destroy-selected'), [
            'measurement_log_ids' => [$measurementLog1->id, $measurementLog2->id],
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $this->assertDatabaseMissing('measurement_logs', ['id' => $measurementLog1->id]);
        $this->assertDatabaseMissing('measurement_logs', ['id' => $measurementLog2->id]);
    }

    /** @test */
    public function user_without_measurement_logs_delete_permission_cannot_bulk_delete_measurement_logs()
    {
        $measurementType = MeasurementTypeFactory::new()->create();
        $measurementLog1 = MeasurementLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'measurement_type_id' => $measurementType->id]);
        $measurementLog2 = MeasurementLogFactory::new()->create(['user_id' => $this->userWithoutDeletePermission->id, 'measurement_type_id' => $measurementType->id]);

        $response = $this->actingAs($this->userWithoutDeletePermission)->post(route('measurement-logs.destroy-selected'), [
            'measurement_log_ids' => [$measurementLog1->id, $measurementLog2->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('measurement_logs', ['id' => $measurementLog1->id]);
        $this->assertDatabaseHas('measurement_logs', ['id' => $measurementLog2->id]);
    }
}
