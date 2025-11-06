<?php

/**
 * Exercise Types Configuration
 * 
 * This configuration file defines all available exercise types and their properties.
 * The exercise type system uses the Strategy Pattern to handle different types of
 * exercises (regular, banded, bodyweight) with type-specific behavior.
 * 
 * Configuration Structure:
 * - types: Defines each exercise type with its class, validation, capabilities, etc.
 * - default_type: The fallback type when type cannot be determined
 * - factory: Factory-specific configuration (caching, fallbacks)
 * - validation: Common validation rules applied to all types
 * - display: Global display settings and formatting options
 * 
 * Adding New Exercise Types:
 * 1. Create a new strategy class implementing ExerciseTypeInterface
 * 2. Add configuration entry in the 'types' array
 * 3. Update factory logic if needed (or use configuration-driven creation)
 * 4. Add appropriate tests
 * 
 * @package Config
 * @since 1.0.0
 */

return [
    /**
     * Exercise Type Definitions
     * 
     * Each exercise type is defined with the following properties:
     * - class: The strategy class that implements the exercise type behavior
     * - validation: Laravel validation rules specific to this exercise type
     * - chart_type: The type of chart to generate for progress tracking
     * - supports_1rm: Whether this exercise type supports 1RM calculations
     * - form_fields: Fields that should be displayed in forms for this type
     * - progression_types: Supported progression models for training programs
     * - display_format: How to format the exercise data for display
     */
    'types' => [
        /**
         * Regular Exercise Type
         * 
         * Traditional weight-based exercises using barbells, dumbbells, machines, etc.
         * This is the most common exercise type and serves as the default fallback.
         */
        'regular' => [
            'class' => \App\Services\ExerciseTypes\RegularExerciseType::class,
            'validation' => [
                'weight' => 'required|numeric|min:0',
                'reps' => 'required|integer|min:1|max:100',
            ],
            'chart_type' => 'one_rep_max',
            'supports_1rm' => true,
            'form_fields' => ['weight', 'reps'],
            'progression_types' => ['linear', 'double_progression'],
            'display_format' => 'weight_lbs',
        ],
        
        /**
         * Banded Exercise Type
         * 
         * Exercises using resistance or assistance bands. Band color indicates
         * the resistance level. Weight is always set to 0 since bands don't
         * use traditional weight measurements.
         */
        'banded' => [
            'class' => \App\Services\ExerciseTypes\BandedExerciseType::class,
            'validation' => [
                'band_color' => 'required|string|in:red,blue,green',
                'reps' => 'required|integer|min:1|max:100',
            ],
            'chart_type' => 'volume_progression',
            'supports_1rm' => false,
            'form_fields' => ['band_color', 'reps'],
            'progression_types' => ['volume_progression', 'band_progression'],
            'display_format' => 'band_color',
            'subtypes' => [
                'resistance' => [
                    'description' => 'Resistance bands that add difficulty',
                    'progression_direction' => 'up',
                ],
                'assistance' => [
                    'description' => 'Assistance bands that reduce difficulty',
                    'progression_direction' => 'down',
                ],
            ],
        ],
        
        /**
         * Bodyweight Exercise Type
         * 
         * Exercises that primarily use body weight as resistance. The weight field
         * represents additional weight (e.g., weighted vest, dip belt) rather than
         * the total resistance. Supports 1RM calculation by including estimated body weight.
         */
        'bodyweight' => [
            'class' => \App\Services\ExerciseTypes\BodyweightExerciseType::class,
            'validation' => [
                'weight' => 'nullable|numeric|min:0',
                'reps' => 'required|integer|min:1|max:100',
            ],
            'chart_type' => 'bodyweight_progression',
            'supports_1rm' => true,
            'form_fields' => ['weight', 'reps'],
            'progression_types' => ['linear', 'double_progression', 'bodyweight_progression'],
            'display_format' => 'bodyweight_plus_extra',
        ],
    ],
    
    /**
     * Default Exercise Type
     * 
     * The exercise type to use when the type cannot be determined from
     * exercise properties. Should be the most common/safe type.
     */
    'default_type' => 'regular',
    
    /**
     * Factory Configuration
     * 
     * Settings that control how the ExerciseTypeFactory behaves:
     * - cache_strategies: Whether to cache strategy instances for performance
     * - fallback_type: The type to use when strategy creation fails
     */
    'factory' => [
        'cache_strategies' => true,
        'fallback_type' => 'regular',
    ],
    
    /**
     * Common Validation Rules
     * 
     * Validation rules that apply to all exercise types in addition to
     * type-specific rules. These are merged with type-specific rules.
     */
    'validation' => [
        'common_rules' => [
            'reps' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
        ],
    ],
    
    /**
     * Display Configuration
     * 
     * Global settings for how exercise data is formatted and displayed:
     * - weight_unit: The unit to display for weights (lbs, kg)
     * - precision: Number of decimal places for weight display
     * - show_1rm_when_supported: Whether to show 1RM when the exercise type supports it
     */
    'display' => [
        'weight_unit' => 'lbs',
        'precision' => 1,
        'show_1rm_when_supported' => true,
    ],
];