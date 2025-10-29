<?php

namespace Tests\Unit;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_profile_update_validation_works_without_preferences(): void
    {
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $rules = $request->rules();

        // Test valid profile data (without preferences)
        $validData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function test_profile_update_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $rules = $request->rules();

        // Test invalid email
        $invalidData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }

    /** @test */
    public function test_existing_validation_rules_still_work(): void
    {
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $rules = $request->rules();

        // Test missing required fields
        $invalidData = [
            'show_global_exercises' => true,
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
        $this->assertTrue($validator->errors()->has('email'));

        // Test invalid email
        $invalidData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'show_global_exercises' => true,
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }
}