<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New User Default Settings
    |--------------------------------------------------------------------------
    |
    | These settings define the default values for new users regardless of
    | how they register (standard registration, Google OAuth, or admin creation).
    |
    */

    'exercise_preferences' => [
        'show_global_exercises' => true,
        'show_extra_weight' => true,
        'prefill_suggested_values' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Measurement Types
    |--------------------------------------------------------------------------
    |
    | Measurement types that are automatically created for new users.
    |
    */

    'measurement_types' => [
        [
            'name' => 'Bodyweight',
            'default_unit' => 'lbs',
        ],
        [
            'name' => 'Waist',
            'default_unit' => 'in',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Ingredients
    |--------------------------------------------------------------------------
    |
    | Basic ingredients that are created for new users to get them started.
    |
    */

    'ingredients' => [
        [
            'name' => 'Chicken Breast',
            'base_quantity' => 100,
            'protein' => 31.0,
            'carbs' => 0.0,
            'added_sugars' => 0.0,
            'fats' => 3.6,
            'sodium' => 74,
            'iron' => 0.7,
            'potassium' => 256,
            'fiber' => 0.0,
            'calcium' => 15,
            'caffeine' => 0.0,
            'base_unit' => 'g',
            'cost_per_unit' => 0.12,
        ],
        [
            'name' => 'Brown Rice',
            'base_quantity' => 100,
            'protein' => 2.6,
            'carbs' => 23.0,
            'added_sugars' => 0.0,
            'fats' => 0.9,
            'sodium' => 5,
            'iron' => 0.4,
            'potassium' => 43,
            'fiber' => 1.8,
            'calcium' => 10,
            'caffeine' => 0.0,
            'base_unit' => 'g',
            'cost_per_unit' => 0.03,
        ],
        [
            'name' => 'Broccoli',
            'base_quantity' => 100,
            'protein' => 2.8,
            'carbs' => 6.6,
            'added_sugars' => 0.0,
            'fats' => 0.4,
            'sodium' => 33,
            'iron' => 0.7,
            'potassium' => 316,
            'fiber' => 2.6,
            'calcium' => 47,
            'caffeine' => 0.0,
            'base_unit' => 'g',
            'cost_per_unit' => 0.06,
        ],
        [
            'name' => 'Olive Oil',
            'base_quantity' => 1,
            'protein' => 0.0,
            'carbs' => 0.0,
            'added_sugars' => 0.0,
            'fats' => 13.5,
            'sodium' => 0,
            'iron' => 0.1,
            'potassium' => 0,
            'fiber' => 0.0,
            'calcium' => 0,
            'caffeine' => 0.0,
            'base_unit' => 'tbsp',
            'cost_per_unit' => 0.20,
        ],
        [
            'name' => 'Eggs',
            'base_quantity' => 1,
            'protein' => 6.3,
            'carbs' => 0.6,
            'added_sugars' => 0.0,
            'fats' => 5.3,
            'sodium' => 62,
            'iron' => 0.9,
            'potassium' => 69,
            'fiber' => 0.0,
            'calcium' => 28,
            'caffeine' => 0.0,
            'base_unit' => 'pc',
            'cost_per_unit' => 0.25,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sample Meal
    |--------------------------------------------------------------------------
    |
    | A sample meal that is created for new users using the default ingredients.
    |
    */

    'sample_meal' => [
        'name' => 'Chicken, Rice & Broccoli',
        'comments' => 'A balanced meal with protein, carbs, and vegetables.',
        'ingredients' => [
            'Chicken Breast' => 150,
            'Brown Rice' => 100,
            'Broccoli' => 200,
            'Olive Oil' => 1,
        ],
    ],
];