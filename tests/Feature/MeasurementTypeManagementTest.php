<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementType;

class MeasurementTypeManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /** @test */
    public function authenticated_user_can_create_measurement_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $measurementTypeData = [
            'name' => $this->faker->word,
            'default_unit' => $this->faker->word,
        ];

        $response = $this->post(route('measurement-types.store'), $measurementTypeData);

        $response->assertRedirect(route('measurement-types.index'));
        $response->assertSessionHas('success', 'Measurement type created successfully.');
        $this->assertDatabaseHas('measurement_types', array_merge($measurementTypeData, ['user_id' => $user->id]));
    }

    /** @test */
    public function authenticated_user_cannot_create_measurement_type_with_missing_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $measurementTypeData = [
            'name' => '',
            'default_unit' => $this->faker->word,
        ];

        $response = $this->post(route('measurement-types.store'), $measurementTypeData);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('measurement_types', ['user_id' => $user->id, 'name' => '']);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_measurement_type()
    {
        $measurementTypeData = [
            'name' => $this->faker->word,
            'default_unit' => $this->faker->word,
        ];

        $response = $this->post(route('measurement-types.store'), $measurementTypeData);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('measurement_types', $measurementTypeData);
    }

    /** @test */
    public function authenticated_user_only_sees_their_measurement_types()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $measurementType1 = MeasurementType::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Measurement Type']);

        $this->actingAs($user2);
        $measurementType2 = MeasurementType::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Measurement Type']);

        $response = $this->get(route('measurement-types.index'));

        $response->assertOk();
        $response->assertSee($measurementType2->name);
        $response->assertDontSee($measurementType1->name);
    }
}
