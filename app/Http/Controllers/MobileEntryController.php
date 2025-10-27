<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DateTitleService;

class MobileEntryController extends Controller
{
    /**
     * Display the mobile entry interface
     * 
     * Supports date-based navigation through URL parameters:
     * - /mobile-entry (defaults to today)
     * - /mobile-entry?date=2024-01-15 (specific date)
     * 
     * @param Request $request
     * @param DateTitleService $dateTitleService
     * @return \Illuminate\View\View
     */
    public function index(Request $request, DateTitleService $dateTitleService)
    {
        // Get the selected date from request or default to today
        // Supports formats: Y-m-d, Y/m/d, and other Carbon-parseable formats
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        // Calculate navigation dates for prev/next functionality
        $prevDay = $selectedDate->copy()->subDay();
        $nextDay = $selectedDate->copy()->addDay();
        $today = \Carbon\Carbon::today();
        
        // Generate user-friendly date title with main title and optional subtitle
        $dateTitleData = $dateTitleService->generateDateTitle($selectedDate, $today);
        // All text content and data for the view
        $data = [
            /**
             * Date Navigation Configuration
             * 
             * Provides configurable navigation buttons with dynamic URLs based on selected date.
             * Each button contains both display text and href for proper link functionality.
             * 
             * Structure:
             * - prevButton: Links to previous day (selectedDate - 1 day)
             * - todayButton: Links to current date (always today, regardless of selected date)
             * - nextButton: Links to next day (selectedDate + 1 day)
             * 
             * URLs are generated using Laravel's route() helper with date parameters.
             * This allows the mobile entry interface to work with any date-based route pattern.
             * 
             * Usage Examples:
             * - For food logs: route('food-logs.mobile-entry', ['date' => $date])
             * - For lift logs: route('lift-logs.mobile-entry', ['date' => $date])
             * - For generic mobile entry: route('mobile-entry.index', ['date' => $date])
             */
            'navigation' => [
                'prevButton' => [
                    'text' => '← Prev',
                    'href' => route('mobile-entry.index', ['date' => $prevDay->toDateString()])
                ],
                'todayButton' => [
                    'text' => 'Today',
                    'href' => route('mobile-entry.index', ['date' => $today->toDateString()])
                ],
                'nextButton' => [
                    'text' => 'Next →',
                    'href' => route('mobile-entry.index', ['date' => $nextDay->toDateString()])
                ],
                'dateTitle' => $dateTitleData,
                'ariaLabels' => [
                    'navigation' => 'Date navigation',
                    'previousDay' => 'Previous day',
                    'goToToday' => 'Go to today',
                    'nextDay' => 'Next day'
                ]
            ],
            
            // Summary section
            'summary' => [
                'values' => [
                    'total' => 1250,
                    'completed' => 3,
                    'average' => 85,
                    'today' => 12
                ],
                'labels' => [
                    'total' => 'Total',
                    'completed' => 'Completed',
                    'average' => 'Average',
                    'today' => 'Today'
                ],
                'ariaLabels' => [
                    'section' => 'Daily summary'
                ]
            ],
            
            // Add Item button
            'addItemButton' => [
                'text' => 'Add Item',
                'ariaLabel' => 'Add new item'
            ],
            
            // Item selection list (appears when Add Item is clicked)
            'itemSelectionList' => [
                'noResultsMessage' => 'No items found. Hit "+" to save as new item.',
                'createForm' => [
                    'action' => '#', // route('mobile-entry.create-item'), // Configure this route as needed
                    'method' => 'POST',
                    'inputName' => 'item_name',
                    'submitText' => '+',
                    'ariaLabel' => 'Create new item'
                ],
                'items' => [
                    [
                        'id' => 'item-1',
                        'name' => 'Bench Press',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 1]) or similar
                    ],
                    [
                        'id' => 'item-2',
                        'name' => 'Squats',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 2]) or similar
                    ],
                    [
                        'id' => 'item-3',
                        'name' => 'Chicken Breast',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.food', ['id' => 3]) or similar
                    ],
                    [
                        'id' => 'item-4',
                        'name' => 'Brown Rice',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.food', ['id' => 4]) or similar
                    ],
                    [
                        'id' => 'item-5',
                        'name' => 'Body Weight',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.measurement', ['id' => 5]) or similar
                    ],
                    [
                        'id' => 'item-6',
                        'name' => 'Body Fat %',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.measurement', ['id' => 6]) or similar
                    ],
                    [
                        'id' => 'item-7',
                        'name' => 'Deadlift: This is a very long exercise name to test the overflow behavior',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 7]) or similar
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Item selection list',
                    'selectItem' => 'Select this item to log'
                ],
                'filterPlaceholder' => 'Filter items...'
            ],
            

            
            /**
             * Prepopulated Forms Configuration
             * 
             * These forms are rendered immediately visible to the user with predefined data.
             * Each form can have different types, prepopulated values, and configurations.
             * 
             * Structure:
             * - Forms are always visible (not hidden behind item selection)
             * - Each form can have different field configurations
             * - Values are prepopulated from the data array
             * - Forms maintain the same structure as dynamic forms for consistency
             */
            'prepopulatedForms' => [
                // Example: Quick Exercise Entry
                [
                    'id' => 'quick-exercise-1',
                    'type' => 'exercise',
                    'title' => 'Morning Push-ups',
                    'itemName' => 'Push-ups',
                    'formAction' => '#', // route('lift-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-exercise-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Last time:',
                            'text' => '3 sets of 20 reps'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Goal:',
                            'text' => 'Try to increase by 2 reps today'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-exercise-1-sets',
                            'name' => 'sets',
                            'label' => 'Sets:',
                            'defaultValue' => 3,
                            'increment' => 1,
                            'min' => 1,
                            'max' => 10,
                            'ariaLabels' => [
                                'decrease' => 'Decrease sets',
                                'increase' => 'Increase sets'
                            ]
                        ],
                        [
                            'id' => 'quick-exercise-1-reps',
                            'name' => 'reps',
                            'label' => 'Reps:',
                            'defaultValue' => 22,
                            'increment' => 1,
                            'min' => 1,
                            'max' => 100,
                            'ariaLabels' => [
                                'decrease' => 'Decrease reps',
                                'increase' => 'Increase reps'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-exercise-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'How did it feel?',
                        'defaultValue' => 'Feeling strong today!'
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Push-ups'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick exercise entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ],
                
                // Example: Quick Food Entry
                [
                    'id' => 'quick-food-1',
                    'type' => 'food',
                    'title' => 'Breakfast Protein Shake',
                    'itemName' => 'Protein Shake',
                    'formAction' => '#', // route('food-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-food-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Calories:',
                            'text' => '~320 per serving'
                        ],
                        [
                            'type' => 'neutral',
                            'prefix' => 'Protein:',
                            'text' => '25g per scoop'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-food-1-scoops',
                            'name' => 'scoops',
                            'label' => 'Scoops:',
                            'defaultValue' => 1.5,
                            'increment' => 0.5,
                            'min' => 0.5,
                            'max' => 5,
                            'ariaLabels' => [
                                'decrease' => 'Decrease scoops',
                                'increase' => 'Increase scoops'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-food-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'Add any notes...',
                        'defaultValue' => 'Post-workout shake with banana'
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Shake'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick food entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ],
                
                // Example: Quick Measurement Entry
                [
                    'id' => 'quick-measurement-1',
                    'type' => 'measurement',
                    'title' => 'Daily Weigh-in',
                    'itemName' => 'Body Weight',
                    'formAction' => '#', // route('body-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-measurement-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Yesterday:',
                            'text' => '184.2 lbs'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Tip:',
                            'text' => 'Weigh yourself at the same time daily'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-measurement-1-weight',
                            'name' => 'weight',
                            'label' => 'Weight (lbs):',
                            'defaultValue' => 184.5,
                            'increment' => 0.1,
                            'min' => 50,
                            'max' => 500,
                            'ariaLabels' => [
                                'decrease' => 'Decrease weight',
                                'increase' => 'Increase weight'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-measurement-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'How are you feeling?',
                        'defaultValue' => ''
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Weight'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick measurement entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ]
            ],
            
            // Logged items
            'loggedItems' => [
                'emptyMessage' => 'No logged items yet!',
                'title' => 'Item Entry',
                'items' => [
                    [
                        'value' => 25,
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Morning workout completed'
                        ],
                        'freeformText' => 'Felt great today! Energy levels were high and form was solid throughout the entire session. Weather was perfect for outdoor training.'
                    ],
                    [
                        'value' => 15,
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Quick afternoon session'
                        ]
                        // No freeformText - demonstrates optional field
                    ],
                    [
                        'value' => 30,
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Evening cardio and stretching routine'
                        ],
                        'freeformText' => 'Extended session with 20 minutes of cardio followed by full-body stretching. Really helped with recovery from yesterday\'s intense workout.'
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Logged items',
                    'deleteItem' => 'Delete logged item'
                ]
            ]
        ];

        return view('mobile-entry.index', compact('data'));
    }

}