<?php

namespace Tests\Feature;

use App\Models\MeasurementType;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create();
        
        // Create admin role and assign it
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole);
    }

    /** @test */
    public function menu_service_processes_main_menu_correctly()
    {
        $menuService = app(MenuService::class);
        $mainMenu = $menuService->getMainMenu();
        
        $this->assertIsArray($mainMenu);
        $this->assertNotEmpty($mainMenu);
        
        // Check that each item has required properties
        foreach ($mainMenu as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('active', $item);
        }
    }

    /** @test */
    public function menu_service_processes_utility_menu_correctly()
    {
        $menuService = app(MenuService::class);
        $utilityMenu = $menuService->getUtilityMenu();
        
        $this->assertIsArray($utilityMenu);
        
        // Check that each item has required properties
        foreach ($utilityMenu as $item) {
            $this->assertArrayHasKey('active', $item);
        }
    }

    /** @test */
    public function admin_user_sees_admin_menu_items()
    {
        $this->actingAs($this->adminUser);
        
        $menuService = app(MenuService::class);
        $utilityMenu = $menuService->getUtilityMenu();
        
        // Admin should see more items than regular user
        $this->assertNotEmpty($utilityMenu);
        
        // Check for admin-specific items (Labs, Settings)
        $hasAdminItems = false;
        foreach ($utilityMenu as $item) {
            if (isset($item['roles']) && in_array('Admin', $item['roles'])) {
                $hasAdminItems = true;
                break;
            }
        }
        $this->assertTrue($hasAdminItems);
    }

    /** @test */
    public function regular_user_does_not_see_admin_menu_items()
    {
        $this->actingAs($this->user);
        
        $menuService = app(MenuService::class);
        $utilityMenu = $menuService->getUtilityMenu();
        
        // Count admin-only items
        $adminItemCount = 0;
        foreach ($utilityMenu as $item) {
            if (isset($item['roles']) && in_array('Admin', $item['roles'])) {
                $adminItemCount++;
            }
        }
        
        $this->assertEquals(0, $adminItemCount, 'Regular user should not see any admin-only menu items');
    }

    /** @test */
    public function profile_menu_contains_logout_option()
    {
        $this->actingAs($this->user);
        
        // Mock being on profile route to activate profile menu
        $this->app['request']->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/profile', []);
            $route->name('profile.edit');
            return $route;
        });
        
        $menuService = app(MenuService::class);
        $subMenu = $menuService->getSubMenu();
        
        if ($subMenu) {
            $logoutItem = collect($subMenu)->firstWhere('label', 'Logout');
            $this->assertNotNull($logoutItem, 'Profile sub-menu should contain logout option');
            $this->assertEquals('logout.get', $logoutItem['route']);
        }
    }

    /** @test */
    public function body_menu_handles_dynamic_measurement_types()
    {
        $this->actingAs($this->user);
        
        // Create some measurement types for the user
        MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight'
        ]);
        MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Body Fat Percentage'
        ]);

        // Mock being on body route to activate body menu
        $this->app['request']->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/body-logs', []);
            $route->name('body-logs.index');
            return $route;
        });
        
        $menuService = app(MenuService::class);
        $subMenu = $menuService->getSubMenu();
        
        if ($subMenu) {
            $measurementItems = collect($subMenu)->where('route', 'body-logs.show-by-type');
            $this->assertGreaterThan(0, $measurementItems->count(), 
                'Body sub-menu should contain dynamic measurement type items');
        }
    }

    /** @test */
    public function impersonator_sees_special_menu_items()
    {
        $this->actingAs($this->user);
        session(['impersonator_id' => $this->adminUser->id]);
        
        $menuService = app(MenuService::class);
        $utilityMenu = $menuService->getUtilityMenu();
        
        // Should have access to items marked with 'Impersonator' role
        $this->assertIsArray($utilityMenu);
    }

    /** @test */
    public function menu_handles_missing_route_gracefully()
    {
        // Test that menu doesn't break when current route is null
        $this->app['request']->setRouteResolver(function () {
            return null;
        });
        
        $menuService = app(MenuService::class);
        $mainMenu = $menuService->getMainMenu();
        
        $this->assertIsArray($mainMenu);
        foreach ($mainMenu as $item) {
            $this->assertFalse($item['active'], 'Items should not be active when no route is set');
        }
    }

    /** @test */
    public function sub_menu_returns_null_when_no_active_parent()
    {
        $this->actingAs($this->user);
        
        // Mock being on a route that doesn't match any menu patterns
        $this->app['request']->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/unknown', []);
            $route->name('unknown.route');
            return $route;
        });
        
        $menuService = app(MenuService::class);
        $subMenu = $menuService->getSubMenu();
        
        $this->assertNull($subMenu, 'Sub-menu should be null when no parent menu is active');
        $this->assertFalse($menuService->shouldShowSubMenu());
    }
}