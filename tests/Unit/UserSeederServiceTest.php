<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_seeds_new_user_with_default_data()
    {
        $user = User::factory()->create();
        $service = new UserSeederService();

        // Verify user starts with no data
        $this->assertCount(0, $user->measurementTypes);
        $this->assertCount(0, $user->ingredients);
        $this->assertCount(0, $user->meals);

        // Seed the user
        $service->seedNewUser($user);

        // Refresh the user to get the latest data
        $user->refresh();

        // Verify measurement types were created
        $this->assertCount(2, $user->measurementTypes);
        $this->assertTrue($user->measurementTypes->contains('name', 'Bodyweight'));
        $this->assertTrue($user->measurementTypes->contains('name', 'Waist'));

        // Verify ingredients were created
        $this->assertCount(5, $user->ingredients);
        $this->assertTrue($user->ingredients->contains('name', 'Chicken Breast'));
        $this->assertTrue($user->ingredients->contains('name', 'Rice (dry, brown)'));
        $this->assertTrue($user->ingredients->contains('name', 'Broccoli (raw)'));
        $this->assertTrue($user->ingredients->contains('name', 'Olive Oil'));
        $this->assertTrue($user->ingredients->contains('name', 'Egg (whole, large)'));

        // Verify sample meal was created
        $this->assertCount(1, $user->meals);
        $meal = $user->meals->first();
        $this->assertEquals('Chicken, Rice & Broccoli', $meal->name);
        $this->assertCount(4, $meal->ingredients);
    }
}