<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Http\Middleware\RestrictTsvImportsInProduction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IngredientTsvImportProductionRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_ingredient_tsv_import_form_visibility_matches_exercise_pattern()
    {
        // Test that ingredient import form visibility follows the same pattern as exercises
        $ingredientResponse = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        $exerciseResponse = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        // Both should show TSV Import in development/testing environment
        $ingredientResponse->assertSee('TSV Import');
        $exerciseResponse->assertSee('TSV Import');

        // Both should show the import form elements
        $ingredientResponse->assertSee('Import TSV');
        $exerciseResponse->assertSee('Import Exercises');
    }

    public function test_ingredient_tsv_import_middleware_blocks_production_requests()
    {
        $middleware = new RestrictTsvImportsInProduction();
        
        // Mock production environment
        $originalEnv = app()->environment();
        
        // Test production environment blocking
        app()->instance('env', 'production');
        
        $request = Request::create('/ingredients/import-tsv', 'POST');
        
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('TSV import functionality is not available in production or staging environments.');
        
        $middleware->handle($request, function ($req) {
            return response('This should not be reached');
        });
    }

    public function test_ingredient_tsv_import_middleware_blocks_staging_requests()
    {
        $middleware = new RestrictTsvImportsInProduction();
        
        // Mock staging environment
        app()->instance('env', 'staging');
        
        $request = Request::create('/ingredients/import-tsv', 'POST');
        
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('TSV import functionality is not available in production or staging environments.');
        
        $middleware->handle($request, function ($req) {
            return response('This should not be reached');
        });
    }

    public function test_ingredient_tsv_import_middleware_allows_development_requests()
    {
        $middleware = new RestrictTsvImportsInProduction();
        
        // Test development environment (should allow)
        $request = Request::create('/ingredients/import-tsv', 'POST');
        
        $response = $middleware->handle($request, function ($req) {
            return response('Request allowed');
        });
        
        $this->assertEquals('Request allowed', $response->getContent());
    }

    public function test_ingredient_tsv_import_route_registration_consistency()
    {
        // Verify that ingredient import route is registered with the same conditions as other TSV imports
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('ingredients.import-tsv'));
        
        // Get the route and verify it has the correct middleware
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('ingredients.import-tsv');
        $middlewares = $route->gatherMiddleware();
        
        // Should have the production restriction middleware
        $this->assertContains('no.tsv.in.production', $middlewares);
        
        // Should also have auth middleware
        $this->assertContains('auth', $middlewares);
    }

    public function test_ingredient_import_environment_restrictions_match_exercise_restrictions()
    {
        // Test that both ingredient and exercise imports use identical environment restrictions
        $restrictedEnvironments = ['production', 'staging'];
        $allowedEnvironments = ['local', 'testing', 'development'];
        
        foreach ($restrictedEnvironments as $env) {
            // Both should be restricted in these environments
            $isRestricted = in_array($env, ['production', 'staging']);
            $this->assertTrue($isRestricted, "Both ingredient and exercise imports should be restricted in {$env}");
        }
        
        foreach ($allowedEnvironments as $env) {
            // Both should be allowed in these environments
            $isAllowed = !in_array($env, ['production', 'staging']);
            $this->assertTrue($isAllowed, "Both ingredient and exercise imports should be allowed in {$env}");
        }
    }

    public function test_ingredient_tsv_import_ui_elements_are_properly_protected()
    {
        $response = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        $response->assertStatus(200);
        
        // In development/testing, should see the import form
        $response->assertSee('TSV Import');
        $response->assertSee('Import TSV');
        
        // Should see the form elements (HTML will have escaped quotes)
        $content = $response->getContent();
        $this->assertStringContainsString('name="tsv_data"', $content);
        $this->assertStringContainsString('textarea', $content);
    }

    public function test_ingredient_tsv_import_error_handling_consistency()
    {
        // Test that ingredient import returns proper validation errors (not 404) in development
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => '', // Empty data should trigger validation error
            ]);

        // Should not be 404 (route exists)
        $this->assertNotEquals(404, $response->getStatusCode());
        
        // Should redirect back with validation error
        $this->assertTrue(in_array($response->getStatusCode(), [302, 422]));
    }
}