<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\MeasurementLog;

class MeasurementLogManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /** @test */
    public function authenticated_user_only_sees_their_measurement_types_in_log_form()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $measurementType1 = MeasurementType::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Measurement Type']);

        $this->actingAs($user2);
        $measurementType2 = MeasurementType::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Measurement Type']);

        $response = $this->get(route('measurement-logs.create'));

        $response->assertOk();
        $response->assertSee($measurementType2->name);
        $response->assertDontSee($measurementType1->name);
    }

    /** @test */
    public function authenticated_user_only_sees_their_measurement_logs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $measurementType1 = MeasurementType::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Measurement Type']);
        $measurementLog1 = MeasurementLog::factory()->create(['user_id' => $user1->id, 'measurement_type_id' => $measurementType1->id]);

        $this->actingAs($user2);
        $measurementType2 = MeasurementType::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Measurement Type']);
        $measurementLog2 = MeasurementLog::factory()->create(['user_id' => $user2->id, 'measurement_type_id' => $measurementType2->id]);

        $response = $this->get(route('measurement-logs.index'));

        $response->assertOk();
        $response->assertSee($measurementType2->name);
        $response->assertDontSee($measurementType1->name);
    }

    /** @test */
    public function authenticated_user_can_pre_select_measurement_type_in_log_form()
    {
        $user = User::factory()->create();
        $measurementType = MeasurementType::factory()->create(['user_id' => $user->id, 'name' => 'Pre-selected Type']);

        $this->actingAs($user);

        $response = $this->get(route('measurement-logs.create', ['measurement_type_id' => $measurementType->id]));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<option value="' . $measurementType->id . '"',
            'selected',
            '>' . $measurementType->name . '</option>'
        ]);
    }
}
