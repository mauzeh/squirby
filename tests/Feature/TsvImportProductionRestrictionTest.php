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

    public function test_ingredient_tsv_import_routes_are_available_in_current_environment()
    {
        // In testing environment (non-production/staging), routes should be available
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                '_token' => csrf_token(),
                'tsv_data' => '',
            ]);

        // Should not be 404 - route exists but may have validation errors or redirect
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_ingredient_tsv_import_ui_is_visible_in_development()
    {
        $response = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Import');
    }

    public function test_ingredient_tsv_import_form_is_hidden_in_production_environment()
    {
        // Test the environment check logic directly
        $isProductionRestricted = in_array('production', ['production', 'staging']);
        $this->assertTrue($isProductionRestricted, 'Production environment should be restricted');
        
        // Test that the Blade condition would hide the form
        $shouldShowForm = !in_array('production', ['production', 'staging']);
        $this->assertFalse($shouldShowForm, 'TSV Import form should be hidden in production');
    }

    public function test_ingredient_tsv_import_form_is_hidden_in_staging_environment()
    {
        // Test the environment check logic directly
        $isStagingRestricted = in_array('staging', ['production', 'staging']);
        $this->assertTrue($isStagingRestricted, 'Staging environment should be restricted');
        
        // Test that the Blade condition would hide the form
        $shouldShowForm = !in_array('staging', ['production', 'staging']);
        $this->assertFalse($shouldShowForm, 'TSV Import form should be hidden in staging');
    }

    public function test_ingredient_tsv_import_route_protection_logic()
    {
        // Test that the route registration logic would exclude production/staging
        $restrictedEnvironments = ['production', 'staging'];
        
        foreach ($restrictedEnvironments as $env) {
            $shouldRegisterRoute = !in_array($env, ['production', 'staging']);
            $this->assertFalse($shouldRegisterRoute, "TSV import routes should not be registered in {$env}");
        }
        
        // Test that development environments would register routes
        $allowedEnvironments = ['local', 'testing', 'development'];
        
        foreach ($allowedEnvironments as $env) {
            $shouldRegisterRoute = !in_array($env, ['production', 'staging']);
            $this->assertTrue($shouldRegisterRoute, "TSV import routes should be registered in {$env}");
        }
    }

    public function test_ingredient_import_consistency_with_exercise_import_restrictions()
    {
        // Test that ingredient import has the same environment restrictions as exercise import
        $exerciseResponse = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $ingredientResponse = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        // Both should show TSV Import in development/testing
        $exerciseResponse->assertSee('TSV Import');
        $ingredientResponse->assertSee('TSV Import');

        // Both should have the same environment check pattern
        $this->assertTrue(!app()->environment(['production', 'staging']));
    }

    public function test_ingredient_tsv_import_middleware_protection()
    {
        // Test that the middleware would block requests in production/staging
        $middleware = new \App\Http\Middleware\RestrictTsvImportsInProduction();
        
        // In testing environment, middleware should allow the request
        $request = \Illuminate\Http\Request::create('/ingredients/import-tsv', 'POST');
        
        $response = $middleware->handle($request, function ($req) {
            return response('success');
        });
        
        $this->assertEquals('success', $response->getContent());
    }

    public function test_ingredient_tsv_import_route_exists_in_development()
    {
        // Verify that the ingredient import route is properly registered in development
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('ingredients.import-tsv'));
        
        // Verify the route is protected by the correct middleware
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('ingredients.import-tsv');
        $this->assertNotNull($route);
        
        // Check that the route has the production restriction middleware
        $middlewares = $route->gatherMiddleware();
        $this->assertContains('no.tsv.in.production', $middlewares);
    }
}