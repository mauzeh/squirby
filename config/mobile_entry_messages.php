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
    ],

    'error' => [
        'exercise_not_found' => 'Exercise not found. Try searching for a different name or create a new exercise using the "+" button.',
        'exercise_already_exists' => '\':exercise\' already exists in your exercise library. Use the search above to find and add it instead.',
        'exercise_already_in_program' => ':exercise is already ready to log below. Scroll down to find the entry and enter or modify your workout details.',
        'form_invalid_format' => 'Unable to remove form - invalid format.',
        'form_not_found' => 'Exercise form not found. It may have already been removed.',
    ],

    'empty_states' => [
        'no_workouts_logged' => 'No workouts logged yet! Add exercises above to get started.',
        'no_exercises_found' => 'No exercises found. Type a name and hit "+" to create a new exercise.',
        'no_measurements_logged' => 'No measurements logged yet today!',
        'no_measurement_types_found' => 'No measurement types found. Create measurement types first.',
    ],

    'form_guidance' => [
        'how_to_log' => 'Adjust the values below, then tap "Log :exercise" to record your workout.',
        'last_workout' => 'Last workout (:date):',
        'your_last_notes' => 'Your last notes:',
        'todays_focus' => 'Today\'s focus:',
        'try_this' => 'Try this:',
        'suggestion_suffix' => ' (values set below)',
    ],

    'contextual_help' => [
        'getting_started' => 'Tap "Add Exercise" below to choose what you want to work out today.',
        'ready_to_log' => 'You have :count exercise:plural ready to log below.',
        'keep_going' => 'Great progress! You have :count more exercise:plural to log.',
        'workout_complete' => 'All exercises completed! Tap "Add Exercise" below if you want to keep going.',
    ],

    'placeholders' => [
        'search_exercises' => 'Search exercises (e.g. "bench press")...',
        'workout_notes' => 'How did it feel? Any form notes?',
        'search_measurements' => 'Search measurements (e.g. "weight")...',
        'measurement_notes' => 'Any additional notes...',
    ],

    'program_comments' => [
        'new_exercise' => 'New exercise - adjust weight/reps as needed',
    ],
];