<?php

return [
    'defaults' => [
        'sets' => 3,
        'reps' => 10,

        /*
        |--------------------------------------------------------------------------
        | High Rep Threshold
        |--------------------------------------------------------------------------
        |
        | This value is used to filter out lift logs that have a very high number
        | of reps. This is useful for ignoring outlier sets that might not be
        | indicative of the user's current strength level.
        |
        */
        'high_rep_threshold' => 20,
    ],
];
