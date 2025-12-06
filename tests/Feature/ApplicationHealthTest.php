<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_get_routes_return_a_successful_response_for_standard_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $routes = $this->getTestableRoutes();
        $failures = [];

        foreach ($routes as $routeName) {
            try {
                $response = $this->get(route($routeName));
                if ($response->status() !== 200) {
                    $failures[] = "Route '{$routeName}' returned {$response->status()} instead of 200";
                }
            } catch (\Exception $e) {
                $failures[] = "Route '{$routeName}' threw exception: " . $e->getMessage();
            }
        }

        if (!empty($failures)) {
            $this->fail("The following routes failed:\n" . implode("\n", $failures));
        }

        $this->assertTrue(true, "All " . count($routes) . " routes returned 200");
    }

    public function test_all_get_routes_return_a_successful_response_for_admin()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::factory()->create(['name' => 'Admin']));
        $this->actingAs($admin);

        $routes = $this->getTestableRoutes();
        $failures = [];

        foreach ($routes as $routeName) {
            try {
                $response = $this->get(route($routeName));
                if ($response->status() !== 200) {
                    $failures[] = "Route '{$routeName}' returned {$response->status()} instead of 200";
                }
            } catch (\Exception $e) {
                $failures[] = "Route '{$routeName}' threw exception: " . $e->getMessage();
            }
        }

        if (!empty($failures)) {
            $this->fail("The following routes failed:\n" . implode("\n", $failures));
        }

        $this->assertTrue(true, "All " . count($routes) . " routes returned 200");
    }

    private function getTestableRoutes(): array
    {
        $routes = Route::getRoutes()->getRoutesByName();
        $routeList = [];

        $exclude = [
            // Auth routes that redirect when already authenticated
            'register',
            'login',
            'password.request',
            'password.email',
            'password.reset',
            'password.confirm',
            'verification.notice',
            'verification.verify',
            'verification.resend',
            'logout',
            
            // OAuth routes that redirect
            'auth.google',
            'auth.google.callback',
            
            // Admin-only routes (tested separately)
            'users.index',
            'users.create',
            'users.impersonate',
            'workouts.create', // Advanced WOD syntax - admin only
            
            // Impersonation leave (requires active impersonation)
            'users.leave-impersonate',
            
            // Routes that require query parameters
            'lift-logs.create', // Requires exercise_id query parameter
            'exercise-aliases.create', // Requires alias_name query parameter
            'exercise-aliases.store', // Requires exercise_id and alias_name query parameters
            
            // API/utility routes
            'sanctum.csrf-cookie',
            '_ignition.*',
        ];

        foreach ($routes as $name => $route) {
            if (!in_array('GET', $route->methods())) {
                continue;
            }

            // Exclude routes with parameters
            if (count($route->parameterNames()) > 0) {
                continue;
            }

            // Exclude routes from the explicit list
            $shouldExclude = false;
            foreach ($exclude as $pattern) {
                if (fnmatch($pattern, $name)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $routeList[] = $name;
        }

        return $routeList;
    }
}
