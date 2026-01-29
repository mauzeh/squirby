<?php

return [
    'main' => [
        // Feed Main Menu Item
        [
            'id' => 'feed-nav-link',
            'label' => 'Feed',
            'icon' => 'fa-stream',
            'route' => 'feed.index',
            'patterns' => [
                'feed.*',
            ],
        ],
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
                    'icon' => 'fa-list',
                    'route' => 'meals.index',
                    'patterns' => ['meals.*'],
                ],
                [
                    'label' => 'Ingredients',
                    'icon' => 'fa-apple-alt',
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
        // Settings menu (consolidated from settings + profile)
        [
            'label' => null,
            'icon' => 'fa-cog',
            'route' => 'profile.edit', // Default route - profile for all users
            'patterns' => ['users.*', 'exercises.index', 'exercises.create', 'exercises.edit', 'exercises.destroy', 'recommendations.*', 'profile.edit'],
            'style' => 'padding: 14px 8px',
            'children' => [
                // Admin-only items
                [
                    'label' => 'Users',
                    'route' => 'users.index',
                    'patterns' => ['users.*'],
                    'roles' => ['Admin'],
                ],
                [
                    'label' => 'Exercises',
                    'route' => 'exercises.index',
                    'patterns' => ['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.destroy'],
                    'roles' => ['Admin'],
                ],
                [
                    'label' => null,
                    'icon' => 'fa-star',
                    'route' => 'recommendations.index',
                    'title' => 'Recommendations',
                    'patterns' => ['recommendations.*'],
                    'roles' => ['Admin'],
                ],
                // Available to all users
                [
                    'label' => 'Profile',
                    'route' => 'profile.edit',
                    'patterns' => ['profile.edit'],
                ],
                [
                    'label' => 'Logout',
                    'route' => 'logout.get',
                    'icon' => 'fa-sign-out-alt',
                ],
            ],
        ],
    ],
];