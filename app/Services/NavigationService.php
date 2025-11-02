<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class NavigationService
{
    /**
     * Get the main navigation items.
     *
     * @return array
     */
    public function getMainNavigation(): array
    {
        $leftNav = [
            [
                'id' => 'lifts-nav-link',
                'label' => 'Lifts',
                'icon' => 'fas fa-dumbbell',
                'route' => 'mobile-entry.lifts',
                'activeRoutes' => ['exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'mobile-entry.lifts'],
            ],
            [
                'id' => 'food-nav-link',
                'label' => 'Food',
                'icon' => 'fas fa-utensils',
                'route' => 'mobile-entry.foods',
                'activeRoutes' => ['food-logs.*', 'meals.*', 'ingredients.*', 'mobile-entry.foods'],
            ],
            [
                'id' => 'body-nav-link',
                'label' => 'Body',
                'icon' => 'fas fa-heartbeat',
                'route' => 'mobile-entry.measurements',
                'activeRoutes' => ['body-logs.*', 'measurement-types.*', 'mobile-entry.measurements'],
            ],
        ];

        $rightNav = [];

        if (Auth::check()) {
            if (Auth::user()->hasRole('Admin')) {
                $rightNav[] = [
                    'id' => 'admin-nav-link',
                    'label' => '',
                    'icon' => 'fas fa-cog',
                    'route' => 'users.index',
                    'activeRoutes' => ['users.*'],
                    'style' => 'padding: 14px 8px',
                    'type' => 'link',
                ];
            }
            $rightNav[] = [
                'id' => 'profile-nav-link',
                'label' => '',
                'icon' => 'fas fa-user',
                'route' => 'profile.edit',
                'activeRoutes' => ['profile.edit'],
                'style' => 'padding: 14px 8px',
                'type' => 'link',
            ];
            $rightNav[] = [
                'id' => 'logout-nav-link',
                'label' => '',
                'icon' => 'fas fa-sign-out-alt',
                'route' => 'logout',
                'type' => 'form', // Indicate this is a form for logout
            ];
        }

        return [
            'left' => $this->processNavigationItems($leftNav),
            'right' => $this->processNavigationItems($rightNav),
        ];
    }

    /**
     * Get the submenu items based on the active main navigation section.
     *
     * @return array
     */
    public function getSubNavigation(): array
    {
        $subnav = [];

        if (Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*', 'mobile-entry.foods'])) {
            $subnav = [
                [
                    'label' => '',
                    'icon' => 'fas fa-mobile-alt',
                    'route' => 'mobile-entry.foods',
                    'activeRoutes' => ['mobile-entry.foods'],
                ],
                [
                    'label' => 'History',
                    'route' => 'food-logs.index',
                    'activeRoutes' => ['food-logs.index', 'food-logs.edit', 'food-logs.destroy-selected', 'food-logs.export', 'food-logs.export-all'],
                ],
                [
                    'label' => 'Meals',
                    'route' => 'meals.index',
                    'activeRoutes' => ['meals.*'],
                ],
                [
                    'label' => 'Ingredients',
                    'route' => 'ingredients.index',
                    'activeRoutes' => ['ingredients.*'],
                ],
            ];
        } elseif (Request::routeIs(['body-logs.*', 'measurement-types.*', 'mobile-entry.measurements'])) {
            $subnav = [
                [
                    'label' => '',
                    'icon' => 'fas fa-mobile-alt',
                    'route' => 'mobile-entry.measurements',
                    'activeRoutes' => ['mobile-entry.measurements'],
                ],
            ];
            if (Auth::user() && Auth::user()->hasRole('Admin')) {
                $subnav[] = [
                    'label' => 'History',
                    'route' => 'body-logs.index',
                    'activeRoutes' => ['body-logs.index', 'body-logs.edit', 'body-logs.destroy-selected'],
                ];
            }
            // Dynamically add measurement types
            if (Auth::check()) {
                $measurementTypes = \App\Models\MeasurementType::where('user_id', Auth::id())->orderBy('name')->get();
                foreach ($measurementTypes as $measurementType) {
                    $subnav[] = [
                        'label' => $measurementType->name,
                        'route' => 'body-logs.show-by-type',
                        'routeParams' => $measurementType->id,
                        'activeRoutes' => ['body-logs.show-by-type'],
                        'isActive' => Request::is('body-logs/type/' . $measurementType->id),
                    ];
                }
            }
        } elseif (Request::routeIs(['exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'mobile-entry.lifts'])) {
            $subnav = [
                [
                    'label' => '',
                    'icon' => 'fas fa-mobile-alt',
                    'route' => 'mobile-entry.lifts',
                    'activeRoutes' => ['mobile-entry.lifts'],
                ],
            ];
            if (Auth::user() && Auth::user()->hasRole('Admin')) {
                $subnav[] = [
                    'label' => '',
                    'icon' => 'fas fa-star',
                    'route' => 'recommendations.index',
                    'activeRoutes' => ['recommendations.*'],
                    'title' => 'Recommendations',
                ];
            }
            $subnav[] = [
                'label' => 'History',
                'route' => 'lift-logs.index',
                'activeRoutes' => ['lift-logs.index', 'lift-logs.edit', 'lift-logs.destroy-selected', 'exercises.show-logs'],
            ];
            if (Auth::user() && Auth::user()->hasRole('Admin')) {
                $subnav[] = [
                    'label' => 'Program',
                    'route' => 'programs.index',
                    'activeRoutes' => ['programs.*'],
                ];
            }
            $subnav[] = [
                'label' => 'Exercises',
                'route' => 'exercises.index',
                'activeRoutes' => ['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.store', 'exercises.update', 'exercises.destroy'],
            ];
        }

        return $this->processNavigationItems($subnav);
    }

    /**
     * Process navigation items to add 'active' status.
     *
     * @param array $navItems
     * @return array
     */
    protected function processNavigationItems(array $navItems): array
    {
        foreach ($navItems as &$item) {
            $item['isActive'] = false;
            if (isset($item['activeRoutes'])) {
                foreach ($item['activeRoutes'] as $activeRoute) {
                    if (Request::routeIs($activeRoute)) {
                        $item['isActive'] = true;
                        break;
                    }
                }
            }
            // Special handling for body-logs.show-by-type as Request::routeIs doesn't work with route params
            if (isset($item['route']) && $item['route'] === 'body-logs.show-by-type' && isset($item['routeParams'])) {
                if (Request::is('body-logs/type/' . $item['routeParams'])) {
                    $item['isActive'] = true;
                }
            }
        }
        return $navItems;
    }

    /**
     * Check if any of the given routes are active.
     *
     * @param array $routes
     * @return bool
     */
    public function isActive(array $routes): bool
    {
        foreach ($routes as $route) {
            if (Request::routeIs($route)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the submenu should be displayed.
     *
     * @return bool
     */
    public function shouldShowSubmenu(): bool
    {
        return Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*', 'exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'body-logs.*', 'measurement-types.*', 'mobile-entry.lifts', 'mobile-entry.foods', 'mobile-entry.measurements']);
    }
}
