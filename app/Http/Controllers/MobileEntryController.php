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
                        'formType' => 'exercise'
                    ],
                    [
                        'id' => 'item-2',
                        'name' => 'Squats',
                        'type' => 'highlighted',
                        'formType' => 'exercise'
                    ],
                    [
                        'id' => 'item-3',
                        'name' => 'Chicken Breast',
                        'type' => 'regular',
                        'formType' => 'food'
                    ],
                    [
                        'id' => 'item-4',
                        'name' => 'Brown Rice',
                        'type' => 'regular',
                        'formType' => 'food'
                    ],
                    [
                        'id' => 'item-5',
                        'name' => 'Body Weight',
                        'type' => 'regular',
                        'formType' => 'measurement'
                    ],
                    [
                        'id' => 'item-6',
                        'name' => 'Body Fat %',
                        'type' => 'regular',
                        'formType' => 'measurement'
                    ],
                    [
                        'id' => 'item-7',
                        'name' => 'Deadlift: This is a very long exercise name to test the overflow behavior',
                        'type' => 'highlighted',
                        'formType' => 'exercise'
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Item selection list',
                    'selectItem' => 'Select this item to log'
                ],
                'filterPlaceholder' => 'Filter items...'
            ],
            
            /**
             * Multiple Item Forms Configuration
             * 
             * Supports multiple forms for different types of data entry.
             * Each form can have its own configuration, fields, and behavior.
             * 
             * Structure allows for:
             * - Different form types (food, exercise, measurement, etc.)
             * - Independent field configurations per form
             * - Separate validation and submission handling
             * - Form-specific messaging and UI elements
             */
            'itemForms' => [
                // Example: Exercise logging form
                [
                    'id' => 'exercise-form',
                    'type' => 'exercise',
                    'title' => 'Log Exercise',
                'messages' => [
                    [
                        'type' => 'info',
                        'prefix' => 'Last comment:',
                        'text' => '"Evening cardio and stretching routine"'
                    ],
                    [
                        'type' => 'tip',
                        'prefix' => 'Tip:',
                        'text' => 'Previous values were 30 and 15'
                    ],
                    [
                        'type' => 'success',
                        'prefix' => 'Success:',
                        'text' => 'Previous entry saved successfully'
                    ],
                    [
                        'type' => 'warning',
                        'prefix' => 'Warning:',
                        'text' => 'Values seem unusually high today'
                    ],
                    [
                        'type' => 'error',
                        'prefix' => 'Error:',
                        'text' => 'Failed to sync with server, data saved locally'
                    ],
                    [
                        'type' => 'neutral',
                        'prefix' => 'Note:',
                        'text' => 'Data will be saved automatically'
                    ]
                ],
                
                /**
                 * Numeric Input Fields Configuration
                 * 
                 * Each numeric field supports increment/decrement buttons with configurable behavior.
                 * The JavaScript handles button interactions, boundary checking, and state management.
                 * 
                 * Field Configuration Options:
                 * - id: Unique HTML element ID for the input field
                 * - name: Form field name for POST submission
                 * - label: Display label shown above the input
                 * - defaultValue: Initial value when form loads
                 * - increment: Step amount for +/- buttons (supports decimals like 0.5, 0.25, etc.)
                 * - min: Minimum allowed value (buttons disabled when reached)
                 * - max: Maximum allowed value (null = no limit, buttons disabled when reached)
                 * - ariaLabels: Accessibility labels for screen readers
                 * 
                 * JavaScript Integration:
                 * - Buttons automatically respect min/max boundaries
                 * - Button states update when values change (disabled at limits)
                 * - Manual input typing is preserved and validated
                 * - Input events are dispatched for other listeners
                 * 
                 * HTML Output:
                 * - Creates number-input-group with data attributes
                 * - Sets HTML input min/max/step attributes
                 * - Generates decrement/increment buttons with ARIA labels
                 */
                'numericFields' => [
                    // Example: Integer field with larger increments and no upper limit
                    // Perfect for weight, reps, or other whole number values
                    [
                        'id' => 'item-value-1',
                        'name' => 'value1',
                        'label' => 'Value 1:',
                        'defaultValue' => 95,        // Starting value (e.g., weight in lbs)
                        'increment' => 5,            // +/- buttons change by 5 (common weight increments)
                        'min' => 0,                  // Cannot go below 0
                        'max' => null,               // No upper limit (unlimited weight)
                        'ariaLabels' => [
                            'decrease' => 'Decrease value 1',
                            'increase' => 'Increase value 1'
                        ]
                    ],
                    
                    // Example: Decimal field with fine-grained control and limits
                    // Perfect for percentages, multipliers, or precise measurements
                    [
                        'id' => 'item-value-2',
                        'name' => 'value2',
                        'label' => 'Value 2:',
                        'defaultValue' => 1,         // Starting value (e.g., multiplier)
                        'increment' => 0.25,         // +/- buttons change by 0.25 (fine control)
                        'min' => 0,                  // Cannot go below 0
                        'max' => 100,                // Cannot exceed 100 (e.g., percentage limit)
                        'ariaLabels' => [
                            'decrease' => 'Decrease value 2',
                            'increase' => 'Increase value 2'
                        ]
                    ]
                    
                    /*
                     * Additional Configuration Examples:
                     * 
                     * // Time-based field (minutes/seconds)
                     * [
                     *     'increment' => 15,        // 15-minute intervals
                     *     'min' => 0,
                     *     'max' => 1440,           // 24 hours in minutes
                     * ]
                     * 
                     * // Percentage field
                     * [
                     *     'increment' => 5,         // 5% increments
                     *     'min' => 0,
                     *     'max' => 100,
                     * ]
                     * 
                     * // Currency field
                     * [
                     *     'increment' => 0.01,      // Penny increments
                     *     'min' => 0,
                     *     'max' => null,
                     * ]
                     * 
                     * // Rating field
                     * [
                     *     'increment' => 0.5,       // Half-star increments
                     *     'min' => 0,
                     *     'max' => 5,
                     * ]
                     */
                ],
                    'commentField' => [
                        'id' => 'exercise-comment',
                        'name' => 'comment',
                        'label' => 'Comment:',
                        'placeholder' => 'Add a comment...',
                        'defaultValue' => ''
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Exercise'
                    ],
                    'ariaLabels' => [
                        'section' => 'Log new exercise',
                        'deleteForm' => 'Delete exercise form'
                    ]
                ],
                
                // Example: Food logging form
                [
                    'id' => 'food-form',
                    'type' => 'food',
                    'title' => 'Log Food',
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Last meal:',
                            'text' => '"Chicken and rice bowl"'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Tip:',
                            'text' => 'Previous portion was 1.5 servings'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'food-quantity',
                            'name' => 'quantity',
                            'label' => 'Quantity:',
                            'defaultValue' => 1,
                            'increment' => 0.25,
                            'min' => 0,
                            'max' => 50,
                            'ariaLabels' => [
                                'decrease' => 'Decrease quantity',
                                'increase' => 'Increase quantity'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'food-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'Add notes about this meal...',
                        'defaultValue' => ''
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Food'
                    ],
                    'ariaLabels' => [
                        'section' => 'Log new food',
                        'deleteForm' => 'Delete food form'
                    ]
                ],
                
                // Example: Measurement logging form
                [
                    'id' => 'measurement-form',
                    'type' => 'measurement',
                    'title' => 'Log Measurement',
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Last measurement:',
                            'text' => '185.2 lbs on Jan 10'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Tip:',
                            'text' => 'Weigh yourself at the same time each day for consistency'
                        ],
                        [
                            'type' => 'neutral',
                            'prefix' => 'Note:',
                            'text' => 'Measurements are automatically timestamped'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'measurement-value',
                            'name' => 'value',
                            'label' => 'Weight (lbs):',
                            'defaultValue' => 185,
                            'increment' => 0.1,
                            'min' => 50,
                            'max' => 500,
                            'ariaLabels' => [
                                'decrease' => 'Decrease weight',
                                'increase' => 'Increase weight'
                            ]
                        ],
                        [
                            'id' => 'measurement-body-fat',
                            'name' => 'body_fat',
                            'label' => 'Body Fat %:',
                            'defaultValue' => 15,
                            'increment' => 0.1,
                            'min' => 0,
                            'max' => 50,
                            'ariaLabels' => [
                                'decrease' => 'Decrease body fat percentage',
                                'increase' => 'Increase body fat percentage'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'measurement-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'Add notes about this measurement...',
                        'defaultValue' => ''
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Measurement'
                    ],
                    'ariaLabels' => [
                        'section' => 'Log new measurement',
                        'deleteForm' => 'Delete measurement form'
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