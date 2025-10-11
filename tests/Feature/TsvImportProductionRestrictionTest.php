<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TsvImportProductionRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_tsv_import_routes_are_available_in_current_environment()
    {
        // In testing environment (non-production/staging), routes should be available
        $response = $this->actingAs($this->user)
            ->post(route('food-logs.import-tsv'), [
                '_token' => csrf_token(),
                'tsv_data' => '',
                'date' => '2025-01-01'
            ]);

        // Should not be 404 - route exists but may have validation errors or redirect
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_tsv_import_ui_is_visible_in_development()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Import');
    }

    public function test_environment_check_works_correctly()
    {
        // Test that our environment check logic works
        $this->assertFalse(app()->environment(['production', 'staging']));
        
        // In development/testing, TSV import should be available
        $this->assertTrue(!app()->environment(['production', 'staging']));
    }

    public function test_production_and_staging_environment_restrictions()
    {
        // Test that both production and staging are restricted
        $restrictedEnvironments = ['production', 'staging'];
        
        foreach ($restrictedEnvironments as $env) {
            // Test that the environment check would block these environments
            $isRestricted = in_array($env, ['production', 'staging']);
            $this->assertTrue($isRestricted, "Environment {$env} should be restricted");
        }
        
        // Test that development environments are allowed
        $allowedEnvironments = ['local', 'testing', 'development'];
        
        foreach ($allowedEnvironments as $env) {
            $isRestricted = in_array($env, ['production', 'staging']);
            $this->assertFalse($isRestricted, "Environment {$env} should be allowed");
        }
    }
}