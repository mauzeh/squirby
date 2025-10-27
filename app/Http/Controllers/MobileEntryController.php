<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MobileEntryController extends Controller
{
    /**
     * Display the mobile entry interface
     */
    public function index()
    {
        // All text content and data for the view
        $data = [
            // Navigation and titles
            'navigation' => [
                'prevButton' => '← Prev',
                'todayButton' => 'Today',
                'nextButton' => 'Next →',
                'dateTitle' => 'Today',
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
                        'name' => 'Item 1: This is a very long item name to test the overflow behavior of the item selection list',
                        'type' => 'highlighted'
                    ],
                    [
                        'id' => 'item-2',
                        'name' => 'Item 2',
                        'type' => 'highlighted'
                    ],
                    [
                        'id' => 'item-3',
                        'name' => 'Item 3',
                        'type' => 'highlighted'
                    ],
                    [
                        'id' => 'item-4',
                        'name' => 'Item 4',
                        'type' => 'regular'
                    ],
                    [
                        'id' => 'item-5',
                        'name' => 'Item 5',
                        'type' => 'regular'
                    ],
                    [
                        'id' => 'item-6',
                        'name' => 'Item 6',
                        'type' => 'regular'
                    ],
                    [
                        'id' => 'item-7',
                        'name' => 'Item 7: This is a very long item name to test the overflow behavior of the item selection list',
                        'type' => 'regular'
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Item selection list',
                    'selectItem' => 'Select this item to log'
                ],
                'filterPlaceholder' => 'Filter items...'
            ],
            
            // Item logging form
            'itemForm' => [
                'title' => 'Log New Item',
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
                    'id' => 'item-comment',
                    'name' => 'comment',
                    'label' => 'Comment:',
                    'placeholder' => 'Add a comment...',
                    'defaultValue' => ''
                ],
                'buttons' => [
                    'decrement' => '-',
                    'increment' => '+',
                    'submit' => 'Log Item'
                ],
                'ariaLabels' => [
                    'section' => 'Log new item',
                    'deleteForm' => 'Delete form'
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
                        ]
                    ],
                    [
                        'value' => 15,
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Quick afternoon session'
                        ]
                    ],
                    [
                        'value' => 30,
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Evening cardio and stretching routine'
                        ]
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