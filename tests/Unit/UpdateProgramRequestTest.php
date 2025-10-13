<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Requests\UpdateProgramRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

class UpdateProgramRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function update_program_request_has_correct_validation_rules()
    {
        $request = new UpdateProgramRequest();
        $rules = $request->rules();

        // Verify that sets and reps validation rules are still present for editing
        $this->assertArrayHasKey('sets', $rules);
        $this->assertArrayHasKey('reps', $rules);
        
        // Verify the specific validation rules for sets and reps
        $this->assertContains('required', $rules['sets']);
        $this->assertContains('integer', $rules['sets']);
        $this->assertContains('min:1', $rules['sets']);
        
        $this->assertContains('required', $rules['reps']);
        $this->assertContains('integer', $rules['reps']);
        $this->assertContains('min:1', $rules['reps']);
    }

    /** @test */
    public function update_program_request_validates_sets_and_reps_correctly()
    {
        // Create a user and exercise for validation
        $user = \App\Models\User::factory()->create();
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $user->id]);
        
        $request = new UpdateProgramRequest();
        
        // Test valid data
        $validData = [
            'exercise_id' => $exercise->id,
            'date' => '2025-01-01',
            'sets' => 3,
            'reps' => 10,
        ];
        
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());
        
        // Test invalid sets (missing)
        $invalidData = $validData;
        unset($invalidData['sets']);
        
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('sets'));
        
        // Test invalid reps (missing)
        $invalidData = $validData;
        unset($invalidData['reps']);
        
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('reps'));
        
        // Test invalid sets (zero)
        $invalidData = $validData;
        $invalidData['sets'] = 0;
        
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('sets'));
        
        // Test invalid reps (negative)
        $invalidData = $validData;
        $invalidData['reps'] = -1;
        
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('reps'));
    }

    /** @test */
    public function update_program_request_authorization_returns_true()
    {
        $request = new UpdateProgramRequest();
        $this->assertTrue($request->authorize());
    }
}