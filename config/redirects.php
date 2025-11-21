<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Controller Redirect Configurations
    |--------------------------------------------------------------------------
    |
    | This file contains centralized redirect logic for controllers.
    | Each controller action can have multiple redirect targets based on
    | the 'redirect_to' parameter passed in the request.
    |
    */

    'lift_logs' => [
        'store' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date', 'submitted_lift_log_id'],
            ],
            'mobile-entry-lifts' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date', 'submitted_lift_log_id'],
            ],
            'workouts' => [
                'route' => 'workouts.index',
                'params' => ['workout_id'],
            ],
            'default' => [
                'route' => 'exercises.show-logs',
                'params' => ['exercise_id'],
            ],
        ],
        'update' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date', 'submitted_lift_log_id'],
            ],
            'mobile-entry-lifts' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date', 'submitted_lift_log_id'],
            ],
            'workouts' => [
                'route' => 'workouts.index',
                'params' => ['workout_id'],
            ],
            'default' => [
                'route' => 'exercises.show-logs',
                'params' => ['exercise_id'],
            ],
        ],
        'destroy' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date'],
            ],
            'mobile-entry-lifts' => [
                'route' => 'mobile-entry.lifts',
                'params' => ['date'],
            ],
            'workouts' => [
                'route' => 'workouts.index',
                'params' => ['workout_id'],
            ],
            'exercises-logs' => [
                'route' => 'exercises.show-logs',
                'params' => ['exercise_id'],
            ],
            'default' => [
                'route' => 'lift-logs.index',
                'params' => [],
            ],
        ],
    ],

    'body_logs' => [
        'store' => [
            'mobile-entry-measurements' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
        ],
        'update' => [
            'mobile-entry-measurements' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
        ],
        'destroy' => [
            'mobile-entry-measurements' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.measurements',
                'params' => ['date'],
            ],
        ],
    ],

    'food_logs' => [
        'store' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'mobile-entry-foods' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
        ],
        'add_meal' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'mobile-entry-foods' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
        ],
        'update' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'mobile-entry-foods' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'mobile-entry.foods' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
        ],
        'destroy' => [
            'mobile-entry' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'mobile-entry-foods' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
            'default' => [
                'route' => 'mobile-entry.foods',
                'params' => ['date'],
            ],
        ],
    ],
];
