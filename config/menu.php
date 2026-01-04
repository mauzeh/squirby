<?php

return [
    'main' => [
        // Lifts Main Menu Item
        [
            'id' => 'lifts-nav-link',
            'label' => 'Lifts',
            'icon' => 'fa-dumbbell',
            'route' => 'mobile-entry.lifts',
            'patterns' => [ // Patterns for main item active state and sub-menu dispatch
                'lift-logs.*', 'recommendations.*', 'mobile-entry.lifts', 'workouts.*', 'exercises.show-logs',
            ],
            'children' => [ // Lifts Sub-Menu Items
                [
                    'label' => 'Log now',
                    'icon' => 'fa-plus',
                    'route' => 'mobile-entry.lifts',
                    'title' => 'Direct Entry',
                    'patterns' => ['mobile-entry.lifts', 'lift-logs.create'],
                ],
                [
                    'label' => 'Metrics',
                    'icon' => 'fa-chart-line',
                    'route' => 'lift-logs.index',
                    'title' => 'My Metrics',
                    'patterns' => ['lift-logs.index', 'lift-logs.edit', 'exercises.show-logs'],
                ],
                [
                    'label' => 'Workouts',
                    'icon' => 'fa-clipboard-list',
                    'route' => 'workouts.index',
                    'patterns' => ['workouts.*'],
                ],

            ],
        ],
        // Food Main Menu Item
        [
            'id' => 'food-nav-link',
            'label' => 'Food',
            'icon' => 'fa-utensils',
            'route' => 'mobile-entry.foods',
            'patterns' => [ // Patterns for main item active state and sub-menu dispatch
                'meals.*', 'ingredients.*', 'mobile-entry.foods', 'food-logs.*',
            ],
            'children' => [ // Food Sub-Menu Items
                [
                    'label' => 'Log now',
                    'icon' => 'fa-plus',
                    'route' => 'mobile-entry.foods',
                    'title' => 'Direct Entry',
                    'patterns' => ['mobile-entry.foods', 'food-logs.*'],
                ],
                [
                    'label' => 'Meals',
                    'icon' => 'fa-plate-wheat',
                    'route' => 'meals.index',
                    'patterns' => ['meals.*'],
                ],
                [
                    'label' => 'Ingredients',
                    'icon' => 'fa-carrot',
                    'route' => 'ingredients.index',
                    'patterns' => ['ingredients.*'],
                ],
            ],
        ],
        // Body Main Menu Item
        [
            'label' => 'Body',
            'icon' => 'fa-heartbeat',
            'route' => 'mobile-entry.measurements',
            'patterns' => [ // Patterns for main item active state and sub-menu dispatch
                'body-logs.*', 'measurement-types.*', 'mobile-entry.measurements',
            ],
            'children' => [ // Body Sub-Menu Items
                [
                    'label' => 'Log now',
                    'icon' => 'fa-plus',
                    'route' => 'mobile-entry.measurements',
                    'title' => 'Direct Entry',
                    'patterns' => ['mobile-entry.measurements'],  // Changed from getBodyRoutePatterns to be more specific to this item
                ],
                // Measurement Types (dynamic) - This will need special handling outside the config or in a post-processing step.
                // For now, I'll represent the dynamic nature.
                [
                    'type' => 'dynamic-measurement-types', // Indicate dynamic generation
                    'patterns' => ['body-logs.show-by-type'], // Pattern for individual measurement type active state
                ]
            ],
        ],

    ],
    // Utility Menu (outside main hierarchy, processed differently)
    'utility' => [
        // Admin specific utility items
        // Labs Item (with sub-menu)
        [
            'label' => null,
            'icon' => 'fa-flask',
            'route' => 'labs.with-nav',
            'patterns' => ['labs.*'],
            'roles' => ['Admin'],
            'style' => 'padding: 14px 8px', // Added for consistency with utility items
            'children' => [ // Labs Sub-Menu Items
                [ 'label' => null, 'icon' => 'fa-plus', 'route' => 'labs.with-nav', 'title' => 'With Navigation', 'patterns' => ['labs.with-nav'] ],
                [ 'label' => null, 'icon' => 'fa-minus', 'route' => 'labs.without-nav', 'title' => 'Without Navigation', 'patterns' => ['labs.without-nav'] ],
                [ 'label' => null, 'icon' => 'fa-clone', 'route' => 'labs.multiple-forms', 'title' => 'Multiple Forms', 'patterns' => ['labs.multiple-forms'] ],
                [ 'label' => null, 'icon' => 'fa-sort', 'route' => 'labs.custom-order', 'title' => 'Custom Order', 'patterns' => ['labs.custom-order'] ],
                [ 'label' => null, 'icon' => 'fa-hand-pointer', 'route' => 'labs.multiple-buttons', 'title' => 'Multiple Buttons', 'patterns' => ['labs.multiple-buttons'] ],
                [ 'label' => null, 'icon' => 'fa-table', 'route' => 'labs.table-example', 'title' => 'Table Example', 'patterns' => ['labs.table-example'] ],
                [ 'label' => null, 'icon' => 'fa-arrows-alt-v', 'route' => 'labs.table-reorder', 'title' => 'Table Reorder', 'patterns' => ['labs.table-reorder'] ],
                [ 'label' => null, 'icon' => 'fa-list-ul', 'route' => 'labs.multiple-lists', 'title' => 'Multiple Lists', 'patterns' => ['labs.multiple-lists'] ],
                [ 'label' => null, 'icon' => 'fa-arrow-left', 'route' => 'labs.title-back-button', 'title' => 'Title Back Button', 'patterns' => ['labs.title-back-button'] ],
                [ 'label' => null, 'icon' => 'fa-chevron-down', 'route' => 'labs.table-initial-expanded', 'title' => 'Table Initial Expanded', 'patterns' => ['labs.table-initial-expanded'] ],
                [ 'label' => null, 'icon' => 'fa-expand', 'route' => 'labs.expanded-list', 'title' => 'Expanded List', 'patterns' => ['labs.expanded-list'] ],
                [ 'label' => null, 'icon' => 'fa-check-square', 'route' => 'labs.table-bulk-selection', 'title' => 'Table Bulk Selection', 'patterns' => ['labs.table-bulk-selection'] ],
                [ 'label' => null, 'icon' => 'fa-apple-alt', 'route' => 'labs.ingredient-entry', 'title' => 'Ingredient Entry', 'patterns' => ['labs.ingredient-entry'] ],
                [ 'label' => null, 'icon' => 'fa-chart-line', 'route' => 'labs.chart-example', 'title' => 'Chart Example', 'patterns' => ['labs.chart-example'] ],
                [ 'label' => null, 'icon' => 'fa-folder-open', 'route' => 'labs.tabbed-lift-logger', 'title' => 'Tabbed Container', 'patterns' => ['labs.tabbed-lift-logger'] ],
            ],
        ],
        [
            'label' => null,
            'icon' => 'fa-cog',
            'route' => 'users.index', // Default route when clicking the main settings icon
            'patterns' => ['users.*', 'exercises.index', 'exercises.create', 'exercises.edit', 'exercises.destroy', 'recommendations.*'], // Admin exercise management only
            'style' => 'padding: 14px 8px',
            'roles' => ['Admin'], // Only for Admin
            'children' => [ // Settings Sub-Menu Items
                [
                    'label' => 'Users',
                    'route' => 'users.index',
                    'patterns' => ['users.*'],
                ],
                [
                    'label' => 'Exercises',
                    'route' => 'exercises.index',
                    'patterns' => ['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.destroy'],
                ],
                [
                    'label' => null,
                    'icon' => 'fa-star',
                    'route' => 'recommendations.index',
                    'title' => 'Recommendations',
                    'patterns' => ['recommendations.*'],
                    'roles' => ['Admin'],
                ],
            ],
        ],
        // Profile
        [
            'label' => null,
            'icon' => 'fa-user',
            'route' => 'profile.edit',
            'patterns' => ['profile.edit'],
            'style' => 'padding: 14px 8px',
        ],
        // Logout
        [
            'type' => 'logout',
            'icon' => 'fa-sign-out-alt',
        ],
    ],
];