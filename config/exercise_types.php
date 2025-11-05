<?php

return [
    'types' => [
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
    
    'default_type' => 'regular',
    
    'factory' => [
        'cache_strategies' => true,
        'fallback_type' => 'regular',
    ],
    
    'validation' => [
        'common_rules' => [
            'reps' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
        ],
    ],
    
    'display' => [
        'weight_unit' => 'lbs',
        'precision' => 1,
        'show_1rm_when_supported' => true,
    ],
];