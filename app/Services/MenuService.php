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
                'route' => 'flexible.with-nav',
                'active' => Request::routeIs('flexible.*'),
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
        if (Request::routeIs('flexible.*')) {
            return $this->getFlexibleSubMenu();
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
            'flexible.*', 'workouts.*'
        ]);
    }

    /**
     * Get flexible workflow sub-menu items
     *
     * @return array
     */
    protected function getFlexibleSubMenu(): array
    {
        return [
            [
                'label' => null,
                'icon' => 'fa-plus',
                'route' => 'flexible.with-nav',
                'active' => Request::routeIs('flexible.with-nav'),
                'title' => 'With Navigation',
            ],
            [
                'label' => null,
                'icon' => 'fa-minus',
                'route' => 'flexible.without-nav',
                'active' => Request::routeIs('flexible.without-nav'),
                'title' => 'Without Navigation',
            ],
            [
                'label' => null,
                'icon' => 'fa-clone',
                'route' => 'flexible.multiple-forms',
                'active' => Request::routeIs('flexible.multiple-forms'),
                'title' => 'Multiple Forms',
            ],
            [
                'label' => null,
                'icon' => 'fa-sort',
                'route' => 'flexible.custom-order',
                'active' => Request::routeIs('flexible.custom-order'),
                'title' => 'Custom Order',
            ],
            [
                'label' => null,
                'icon' => 'fa-hand-pointer',
                'route' => 'flexible.multiple-buttons',
                'active' => Request::routeIs('flexible.multiple-buttons'),
                'title' => 'Multiple Buttons',
            ],
            [
                'label' => null,
                'icon' => 'fa-table',
                'route' => 'flexible.table-example',
                'active' => Request::routeIs('flexible.table-example'),
                'title' => 'Table Example',
            ],
            [
                'label' => null,
                'icon' => 'fa-arrows-alt-v',
                'route' => 'flexible.table-reorder',
                'active' => Request::routeIs('flexible.table-reorder'),
                'title' => 'Table Reorder',
            ],
            [
                'label' => null,
                'icon' => 'fa-list-ul',
                'route' => 'flexible.multiple-lists',
                'active' => Request::routeIs('flexible.multiple-lists'),
                'title' => 'Multiple Lists',
            ],
            [
                'label' => null,
                'icon' => 'fa-arrow-left',
                'route' => 'flexible.title-back-button',
                'active' => Request::routeIs('flexible.title-back-button'),
                'title' => 'Title Back Button',
            ],
            [
                'label' => null,
                'icon' => 'fa-chevron-down',
                'route' => 'flexible.table-initial-expanded',
                'active' => Request::routeIs('flexible.table-initial-expanded'),
                'title' => 'Table Initial Expanded',
            ],
            [
                'label' => null,
                'icon' => 'fa-expand',
                'route' => 'flexible.expanded-list',
                'active' => Request::routeIs('flexible.expanded-list'),
                'title' => 'Expanded List',
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
                'icon' => 'fa-mobile-alt',
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
                'icon' => 'fa-mobile-alt',
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
        $items = [
            [
                'label' => null,
                'icon' => 'fa-mobile-alt',
                'route' => 'mobile-entry.lifts',
                'active' => Request::routeIs(['mobile-entry.lifts']),
            ],
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

        $items[] = [
            'label' => 'History',
            'route' => 'lift-logs.index',
            'active' => Request::routeIs(['lift-logs.index', 'lift-logs.edit', 'lift-logs.destroy-selected', 'exercises.show-logs']),
        ];

        $items[] = [
            'label' => 'Workouts',
            'route' => 'workouts.index',
            'active' => Request::routeIs('workouts.*'),
        ];

        $items[] = [
            'label' => 'Exercises',
            'route' => 'exercises.index',
            'active' => Request::routeIs(['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.store', 'exercises.update', 'exercises.destroy']),
        ];

        return $items;
    }
}
