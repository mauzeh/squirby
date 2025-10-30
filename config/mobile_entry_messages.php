<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile Entry Messages
    |--------------------------------------------------------------------------
    |
    | These messages are used throughout the mobile-entry workflow to guide
    | users through adding exercises and logging workouts. You can customize
    | these messages to match your app's tone and branding.
    |
    */

    'success' => [
        'exercise_added' => ':exercise added! Now scroll down to log your workout - adjust the weight/reps and tap \'Log :exercise\' when ready.',
        'exercise_created' => 'Created \':exercise\'! Now scroll down to log your first set - the form is ready with default values you can adjust.',
        'form_removed' => 'Removed :exercise form. You can add it back anytime using \'Add Exercise\' below.',
        
        'lift_logged' => [
            'Nice work! :exercise: :details logged!',
            'Crushed it! :exercise: :details complete!',
            'Great job! :exercise: :details in the books!',
            'Awesome! :exercise: :details logged successfully!',
            'Well done! :exercise: :details completed!'
        ],
        
        'lift_deleted' => 'Removed :exercise.',
        'lift_deleted_mobile' => 'Removed :exercise. Need to adjust and re-log? Just update the values in the form below. Or, hit the delete button again to remove it completely from today\'s workout',
        
        'bulk_deleted_single' => '1 workout entry removed.',
        'bulk_deleted_multiple' => ':count workout entries removed.',
        
        // Food-specific success messages
        'food_added' => ':food added! Now scroll down to log your intake - adjust the quantity and tap \'Log :food\' when ready.',
        'ingredient_created' => 'Created \':ingredient\'! Now scroll down to log your first entry - update the nutrition info and quantity as needed.',
        'food_form_removed' => 'Removed :food form. You can add it back anytime using \'Add Food\' below.',
        
        'food_logged' => [
            'Tasty! :food: :details logged!',
            'Delicious! :food: :details added to your day!',
            'Great choice! :food: :details tracked!',
            'Yum! :food: :details logged successfully!',
            'Nice! :food: :details in the books!'
        ],
        
        'meal_logged' => [
            'Fantastic! :meal: :details logged!',
            'Great meal! :meal: :details added!',
            'Perfect! :meal: :details tracked!',
            'Excellent! :meal: :details logged successfully!',
            'Well done! :meal: :details completed!'
        ],
        
        'food_deleted' => 'Removed :food.',
        'food_deleted_mobile' => 'Removed :food. Need to adjust and re-log? Just update the values in the form below.',
    ],

    'error' => [
        'exercise_not_found' => 'Exercise not found. Try searching for a different name or create a new exercise using the "+" button.',
        'exercise_already_exists' => '\':exercise\' already exists in your exercise library. Use the search above to find and add it instead.',
        'exercise_already_in_program' => ':exercise is already ready to log below. Scroll down to find the entry and enter or modify your workout details.',
        'form_invalid_format' => 'Unable to remove form - invalid format.',
        'form_not_found' => 'Exercise form not found. It may have already been removed.',
        
        // Food-specific error messages
        'food_not_found' => 'Food item not found. Try searching for a different name or create a new ingredient using the "+" button.',
        'ingredient_already_exists' => '\':ingredient\' already exists in your ingredient library. Use the search above to find and add it instead.',
        'food_already_in_forms' => ':food is already ready to log below. Scroll down to find the entry and enter or modify your intake details.',
        'food_form_not_found' => 'Food form not found. It may have already been removed.',
        'ingredient_no_unit' => 'Ingredient does not have a valid unit configured. Please update the ingredient settings.',
        'meal_no_ingredients' => 'Meal has no ingredients configured. Please add ingredients to the meal first.',
    ],

    'empty_states' => [
        'no_workouts_logged' => 'No workouts logged yet! Add exercises above to get started.',
        'no_exercises_found' => 'No exercises found. Type a name and hit "+" to create a new exercise.',
        'no_measurements_logged' => 'No measurements logged yet today!',
        'no_measurement_types_found' => 'No measurement types found. Create measurement types first.',
        
        // Food-specific empty states
        'no_food_logged' => 'No food logged yet today! Add ingredients or meals above to get started.',
        'no_food_items_found' => 'No food items found. Type a name and hit "+" to create a new ingredient.',
    ],

    'form_guidance' => [
        'how_to_log' => 'Adjust the values below, then tap "Log :exercise" to record your workout.',
        'last_workout' => 'Last workout (:date):',
        'your_last_notes' => 'Your last notes:',
        'todays_focus' => 'Today\'s focus:',
        'try_this' => 'Try this:',
        'suggestion_suffix' => ' (values set below)',
        
        // Food-specific form guidance
        'how_to_log_food' => 'Adjust the quantity below, then tap "Log :food" to record your intake.',
        'last_logged' => 'Last logged (:date):',
        'nutrition_info' => 'Nutrition per serving:',
        'meal_contains' => 'This meal contains:',
    ],

    'contextual_help' => [
        'getting_started' => 'Tap "Add Exercise" below to choose what you want to work out today.',
        'ready_to_log' => 'You have :count exercise:plural ready to log below.',
        'keep_going' => 'Great progress! You have :count more exercise:plural to log.',
        'workout_complete' => 'All exercises completed! Tap "Add Exercise" below if you want to keep going.',
        
        // Food-specific contextual help
        'getting_started_food' => 'Tap "Add Food" below to choose what you want to log today.',
        'ready_to_log_food' => 'You have :count food item:plural ready to log below.',
        'keep_logging_food' => 'Great tracking! You have :count more food item:plural to log.',
        'daily_logging_complete' => 'Nice work logging your food! Tap "Add Food" below to log more items.',
    ],

    'placeholders' => [
        'search_exercises' => 'Search...',
        'workout_notes' => 'How did it feel? Any form notes?',
        'search_measurements' => 'Search measurements (e.g. "weight")...',
        'measurement_notes' => 'Any additional notes...',
        
        // Food-specific placeholders
        'search_food' => 'Search ingredients and meals...',
        'food_notes' => 'Any notes about this food?',
    ],

    'program_comments' => [
        'new_exercise' => 'New exercise - adjust weight/reps as needed',
        'new_ingredient' => 'New ingredient - please update nutrition information',
    ],
];