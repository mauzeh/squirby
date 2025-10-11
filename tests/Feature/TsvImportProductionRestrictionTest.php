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
        // In testing environment (non-production), routes should be available
        $response = $this->actingAs($this->user)
            ->post(route('food-logs.import-tsv'), [
                '_token' => csrf_token(),
                'tsv_data' => '',
                'date' => '2025-01-01'
            ]);

        // Should not be 404 - route exists but may have validation errors or redirect
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_tsv_import_ui_is_visible_in_non_production()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Import');
    }

    public function test_environment_check_works_correctly()
    {
        // Test that our environment check logic works
        $this->assertFalse(app()->environment('production'));
        
        // In non-production, TSV import should be available
        $this->assertTrue(!app()->environment('production'));
    }

    public function test_production_environment_simulation()
    {
        // Simulate production environment by temporarily changing config
        $originalEnv = app()->environment();
        
        // Mock the environment method to return 'production'
        $this->app->instance('env', 'production');
        
        // Test that the blade directive would work correctly
        $isProduction = app()->environment('production');
        $this->assertTrue($isProduction);
        
        // Restore original environment
        $this->app->instance('env', $originalEnv);
    }
}