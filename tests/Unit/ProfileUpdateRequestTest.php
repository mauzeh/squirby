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
    public function test_show_global_exercises_validation_accepts_boolean_values(): void
    {
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $rules = $request->rules();

        // Test valid boolean values
        $validData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'show_global_exercises' => true,
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());

        // Test false value
        $validData['show_global_exercises'] = false;
        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());

        // Test null value (should be allowed)
        $validData['show_global_exercises'] = null;
        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());

        // Test without the field (backward compatibility)
        unset($validData['show_global_exercises']);
        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function test_show_global_exercises_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $request = new ProfileUpdateRequest();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $rules = $request->rules();

        // Test invalid string value
        $invalidData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'show_global_exercises' => 'invalid',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('show_global_exercises'));

        // Test invalid numeric value
        $invalidData['show_global_exercises'] = 123;
        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('show_global_exercises'));
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