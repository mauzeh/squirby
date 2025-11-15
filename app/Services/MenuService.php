<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class MenuService
{
    /**
     * Get the main navigation menu items
     *
     * @return array
     */
    public function getMainMenu(): array
    {
        return [
            [
                'id' => 'lifts-nav-link',
                'label' => 'Lifts',
                'icon' => 'fa-dumbbell',
                'route' => 'mobile-entry.lifts',
                'active' => Request::routeIs(['exercises.*', 'lift-logs.*', 'recommendations.*', 'mobile-entry.lifts', 'workouts.*']),
            ],
            [
                'id' => 'food-nav-link',
                'label' => 'Food',
                'icon' => 'fa-utensils',
                'route' => 'mobile-entry.foods',
                'active' => Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*', 'mobile-entry.foods']),
            ],
            [
                'label' => 'Body',
                'icon' => 'fa-heartbeat',
                'route' => 'mobile-entry.measurements',
                'active' => Request::routeIs(['body-logs.*', 'measurement-types.*', 'mobile-entry.measurements']),
            ],
        ];
    }

    /**
     * Get the right-side utility menu items
     *
     * @return array
     */
    public function getUtilityMenu(): array
    {
        $items = [];

        if (Auth::user()->hasRole('Admin')) {
            $items[] = [
                'label' => null,
                'icon' => 'fa-flask',
                'route' => 'labs.with-nav',
                'active' => Request::routeIs('labs.*'),
                'style' => 'padding: 14px 8px',
            ];
            $items[] = [
                'label' => null,
                'icon' => 'fa-cog',
                'route' => 'users.index',
                'active' => Request::routeIs('users.*'),
                'style' => 'padding: 14px 8px',
            ];
        }

        $items[] = [
            'label' => null,
            'icon' => 'fa-user',
            'route' => 'profile.edit',
            'active' => Request::routeIs('profile.edit'),
            'style' => 'padding: 14px 8px',
        ];

        $items[] = [
            'type' => 'logout',
            'icon' => 'fa-sign-out-alt',
        ];

        return $items;
    }

    /**
     * Get the sub-navigation menu items based on current route
     *
     * @return array|null
     */
    public function getSubMenu(): ?array
    {
        if (Request::routeIs('labs.*')) {
            return $this->getLabsSubMenu();
        }

        if (Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*', 'mobile-entry.foods'])) {
            return $this->getFoodSubMenu();
        }

        if (Request::routeIs(['body-logs.*', 'measurement-types.*', 'mobile-entry.measurements'])) {
            return $this->getBodySubMenu();
        }

        if (Request::routeIs(['exercises.*', 'lift-logs.*', 'recommendations.*', 'mobile-entry.lifts', 'workouts.*'])) {
            return $this->getLiftsSubMenu();
        }

        return null;
    }

    /**
     * Check if sub-navigation should be shown
     *
     * @return bool
     */
    public function shouldShowSubMenu(): bool
    {
        return Request::routeIs([
            'food-logs.*', 'meals.*', 'ingredients.*',
            'exercises.*', 'lift-logs.*', 'recommendations.*',
            'body-logs.*', 'measurement-types.*',
            'mobile-entry.lifts', 'mobile-entry.foods', 'mobile-entry.measurements',
            'labs.*', 'workouts.*'
        ]);
    }

    /**
     * Get labs sub-menu items
     *
     * @return array
     */
    protected function getLabsSubMenu(): array
    {
        return [
            [
                'label' => null,
                'icon' => 'fa-plus',
                'route' => 'labs.with-nav',
                'active' => Request::routeIs('labs.with-nav'),
                'title' => 'With Navigation',
            ],
            [
                'label' => null,
                'icon' => 'fa-minus',
                'route' => 'labs.without-nav',
                'active' => Request::routeIs('labs.without-nav'),
                'title' => 'Without Navigation',
            ],
            [
                'label' => null,
                'icon' => 'fa-clone',
                'route' => 'labs.multiple-forms',
                'active' => Request::routeIs('labs.multiple-forms'),
                'title' => 'Multiple Forms',
            ],
            [
                'label' => null,
                'icon' => 'fa-sort',
                'route' => 'labs.custom-order',
                'active' => Request::routeIs('labs.custom-order'),
                'title' => 'Custom Order',
            ],
            [
                'label' => null,
                'icon' => 'fa-hand-pointer',
                'route' => 'labs.multiple-buttons',
                'active' => Request::routeIs('labs.multiple-buttons'),
                'title' => 'Multiple Buttons',
            ],
            [
                'label' => null,
                'icon' => 'fa-table',
                'route' => 'labs.table-example',
                'active' => Request::routeIs('labs.table-example'),
                'title' => 'Table Example',
            ],
            [
                'label' => null,
                'icon' => 'fa-arrows-alt-v',
                'route' => 'labs.table-reorder',
                'active' => Request::routeIs('labs.table-reorder'),
                'title' => 'Table Reorder',
            ],
            [
                'label' => null,
                'icon' => 'fa-list-ul',
                'route' => 'labs.multiple-lists',
                'active' => Request::routeIs('labs.multiple-lists'),
                'title' => 'Multiple Lists',
            ],
            [
                'label' => null,
                'icon' => 'fa-arrow-left',
                'route' => 'labs.title-back-button',
                'active' => Request::routeIs('labs.title-back-button'),
                'title' => 'Title Back Button',
            ],
            [
                'label' => null,
                'icon' => 'fa-chevron-down',
                'route' => 'labs.table-initial-expanded',
                'active' => Request::routeIs('labs.table-initial-expanded'),
                'title' => 'Table Initial Expanded',
            ],
            [
                'label' => null,
                'icon' => 'fa-expand',
                'route' => 'labs.expanded-list',
                'active' => Request::routeIs('labs.expanded-list'),
                'title' => 'Expanded List',
            ],
            [
                'label' => null,
                'icon' => 'fa-check-square',
                'route' => 'labs.table-bulk-selection',
                'active' => Request::routeIs('labs.table-bulk-selection'),
                'title' => 'Table Bulk Selection',
            ],
        ];
    }

    /**
     * Get food sub-menu items
     *
     * @return array
     */
    protected function getFoodSubMenu(): array
    {
        return [
            [
                'label' => null,
                'icon' => 'fa-calendar-day',
                'route' => 'mobile-entry.foods',
                'active' => Request::routeIs(['mobile-entry.foods']),
            ],
            [
                'label' => 'History',
                'route' => 'food-logs.index',
                'active' => Request::routeIs(['food-logs.index', 'food-logs.edit', 'food-logs.destroy-selected', 'food-logs.export', 'food-logs.export-all']),
            ],
            [
                'label' => 'Meals',
                'route' => 'meals.index',
                'active' => Request::routeIs('meals.*'),
            ],
            [
                'label' => 'Ingredients',
                'route' => 'ingredients.index',
                'active' => Request::routeIs('ingredients.*'),
            ],
        ];
    }

    /**
     * Get body/measurements sub-menu items
     *
     * @return array
     */
    protected function getBodySubMenu(): array
    {
        $items = [
            [
                'label' => null,
                'icon' => 'fa-calendar-day',
                'route' => 'mobile-entry.measurements',
                'active' => Request::routeIs(['mobile-entry.measurements']),
            ],
        ];

        if (Auth::user() && Auth::user()->hasRole('Admin')) {
            $items[] = [
                'label' => 'History',
                'route' => 'body-logs.index',
                'active' => Request::routeIs(['body-logs.index', 'body-logs.edit', 'body-logs.destroy-selected']),
            ];
        }

        // Add measurement type links
        $measurementTypes = \App\Models\MeasurementType::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        foreach ($measurementTypes as $measurementType) {
            $items[] = [
                'label' => $measurementType->name,
                'route' => 'body-logs.show-by-type',
                'routeParams' => [$measurementType],
                'active' => Request::is('body-logs/type/' . $measurementType->id),
            ];
        }

        return $items;
    }

    /**
     * Get lifts sub-menu items
     *
     * @return array
     */
    protected function getLiftsSubMenu(): array
    {
        $items = [];

        $items[] = [
            'label' => null,
            'icon' => 'fa-calendar-day',
            'route' => 'mobile-entry.lifts',
            'active' => Request::routeIs(['mobile-entry.lifts']),
            'title' => 'Direct Entry'
        ];

        $items[] = [
            'label' => 'Workouts',
            'route' => 'workouts.index',
            'active' => Request::routeIs(['workouts.*']),
        ];

        $items[] = [
            'label' => 'History',
            'route' => 'lift-logs.index',
            'active' => Request::routeIs(['lift-logs.index', 'lift-logs.edit', 'lift-logs.destroy-selected', 'exercises.show-logs']),
        ];

        if (Auth::user() && (Auth::user()->hasRole('Admin') || session()->has('impersonator_id'))) {
            $items[] = [
                'label' => null,
                'icon' => 'fa-star',
                'route' => 'recommendations.index',
                'active' => Request::routeIs('recommendations.*'),
                'title' => 'Recommendations',
            ];
        }

        // Only show Exercises to admins
        if (Auth::user() && Auth::user()->hasRole('Admin')) {
            $items[] = [
                'label' => 'Exercises',
                'route' => 'exercises.index',
                'active' => Request::routeIs(['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.store', 'exercises.update', 'exercises.destroy']),
            ];
        }

        return $items;
    }
}
