<?php

namespace Tests\Unit\Services;

use App\Models\MeasurementType;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MenuServiceTest extends TestCase
{
    use RefreshDatabase;

    private MenuService $menuService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menuService = new MenuService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_returns_main_menu_items()
    {
        Config::set('menu.main', [
            [
                'label' => 'Test Item',
                'route' => 'test.route',
                'patterns' => ['test.*'],
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Item', $result[0]['label']);
        $this->assertEquals('test.route', $result[0]['route']);
        $this->assertFalse($result[0]['active']);
    }

    /** @test */
    public function it_returns_utility_menu_items()
    {
        Config::set('menu.utility', [
            [
                'label' => 'Utility Item',
                'route' => 'utility.route',
                'patterns' => ['utility.*'],
            ]
        ]);

        $result = $this->menuService->getUtilityMenu();

        $this->assertCount(1, $result);
        $this->assertEquals('Utility Item', $result[0]['label']);
        $this->assertEquals('utility.route', $result[0]['route']);
    }

    /** @test */
    public function it_filters_menu_items_by_admin_role()
    {
        $this->actingAs($this->user);
        
        Config::set('menu.main', [
            [
                'label' => 'Public Item',
                'route' => 'public.route',
            ],
            [
                'label' => 'Admin Item',
                'route' => 'admin.route',
                'roles' => ['Admin'],
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertCount(1, $result);
        $this->assertEquals('Public Item', $result[0]['label']);
    }

    /** @test */
    public function it_shows_admin_items_for_admin_users()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach($adminRole);
        $this->actingAs($adminUser);
        
        Config::set('menu.main', [
            [
                'label' => 'Public Item',
                'route' => 'public.route',
            ],
            [
                'label' => 'Admin Item',
                'route' => 'admin.route',
                'roles' => ['Admin'],
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertCount(2, $result);
        $this->assertEquals('Public Item', $result[0]['label']);
        $this->assertEquals('Admin Item', $result[1]['label']);
    }

    /** @test */
    public function it_handles_impersonator_role()
    {
        $this->actingAs($this->user);
        session(['impersonator_id' => 123]);
        
        Config::set('menu.main', [
            [
                'label' => 'Impersonator Item',
                'route' => 'impersonator.route',
                'roles' => ['Impersonator'],
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertCount(1, $result);
        $this->assertEquals('Impersonator Item', $result[0]['label']);
    }

    /** @test */
    public function it_sets_active_state_based_on_current_route()
    {
        $route = new Route(['GET'], '/test', []);
        $route->name('test.index');
        
        $request = Request::create('/test');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->app->instance('request', $request);
        
        Config::set('menu.main', [
            [
                'label' => 'Test Item',
                'route' => 'test.route',
                'patterns' => ['test.*'],
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertTrue($result[0]['active']);
    }

    /** @test */
    public function it_processes_children_recursively()
    {
        $this->actingAs($this->user);
        
        Config::set('menu.main', [
            [
                'label' => 'Parent Item',
                'route' => 'parent.route',
                'children' => [
                    [
                        'label' => 'Child Item',
                        'route' => 'child.route',
                        'roles' => ['Admin'],
                    ]
                ]
            ]
        ]);

        $result = $this->menuService->getMainMenu();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('children', $result[0]);
        $this->assertEmpty($result[0]['children']); // Child filtered out due to role
    }

    /** @test */
    public function it_returns_null_when_no_sub_menu_available()
    {
        Config::set('menu.main', []);
        Config::set('menu.utility', []);

        $result = $this->menuService->getSubMenu();

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_sub_menu_from_active_main_item()
    {
        $route = new Route(['GET'], '/lifts', []);
        $route->name('lift-logs.index');
        
        $request = Request::create('/lifts');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->app->instance('request', $request);
        
        Config::set('menu.main', [
            [
                'label' => 'Lifts',
                'route' => 'lifts.index',
                'patterns' => ['lift-logs.*'],
                'children' => [
                    [
                        'label' => 'Log now',
                        'route' => 'mobile-entry.lifts',
                    ]
                ]
            ]
        ]);
        Config::set('menu.utility', []);

        $result = $this->menuService->getSubMenu();

        $this->assertCount(1, $result);
        $this->assertEquals('Log now', $result[0]['label']);
    }

    /** @test */
    public function it_returns_sub_menu_from_active_utility_item()
    {
        $route = new Route(['GET'], '/profile', []);
        $route->name('profile.edit');
        
        $request = Request::create('/profile');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->app->instance('request', $request);
        
        Config::set('menu.main', []);
        Config::set('menu.utility', [
            [
                'label' => 'Profile',
                'route' => 'profile.edit',
                'patterns' => ['profile.*'],
                'children' => [
                    [
                        'label' => 'Edit Profile',
                        'route' => 'profile.edit',
                    ],
                    [
                        'label' => 'Logout',
                        'route' => 'logout.get',
                    ]
                ]
            ]
        ]);

        $result = $this->menuService->getSubMenu();

        $this->assertCount(2, $result);
        $this->assertEquals('Edit Profile', $result[0]['label']);
        $this->assertEquals('Logout', $result[1]['label']);
    }

    /** @test */
    public function it_handles_dynamic_measurement_types()
    {
        $this->actingAs($this->user);
        
        // Create measurement types for the user
        $measurementType1 = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight'
        ]);
        $measurementType2 = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Body Fat'
        ]);

        $route = new Route(['GET'], '/body', []);
        $route->name('body-logs.index');
        
        $request = Request::create('/body');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->app->instance('request', $request);
        
        Config::set('menu.main', [
            [
                'label' => 'Body',
                'route' => 'body.index',
                'patterns' => ['body-logs.*'],
                'children' => [
                    [
                        'label' => 'Log now',
                        'route' => 'mobile-entry.measurements',
                    ],
                    [
                        'type' => 'dynamic-measurement-types',
                        'patterns' => ['body-logs.show-by-type'],
                    ]
                ]
            ]
        ]);
        Config::set('menu.utility', []);

        $result = $this->menuService->getSubMenu();

        $this->assertCount(3, $result); // Log now + 2 measurement types
        $this->assertEquals('Log now', $result[0]['label']);
        $this->assertEquals('Body Fat', $result[1]['label']); // Ordered by name
        $this->assertEquals('Weight', $result[2]['label']);
        $this->assertEquals('body-logs.show-by-type', $result[1]['route']);
    }

    /** @test */
    public function should_show_sub_menu_returns_true_when_sub_menu_exists()
    {
        $route = new Route(['GET'], '/lifts', []);
        $route->name('lift-logs.index');
        
        $request = Request::create('/lifts');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->app->instance('request', $request);
        
        Config::set('menu.main', [
            [
                'label' => 'Lifts',
                'route' => 'lifts.index',
                'patterns' => ['lift-logs.*'],
                'children' => [
                    [
                        'label' => 'Log now',
                        'route' => 'mobile-entry.lifts',
                    ]
                ]
            ]
        ]);
        Config::set('menu.utility', []);

        $this->assertTrue($this->menuService->shouldShowSubMenu());
    }

    /** @test */
    public function should_show_sub_menu_returns_false_when_no_sub_menu()
    {
        Config::set('menu.main', []);
        Config::set('menu.utility', []);

        $this->assertFalse($this->menuService->shouldShowSubMenu());
    }
}