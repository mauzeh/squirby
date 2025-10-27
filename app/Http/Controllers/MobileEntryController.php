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
                'numericFields' => [
                    [
                        'id' => 'item-value-1',
                        'name' => 'value1',
                        'label' => 'Value 1:',
                        'defaultValue' => 10,
                        'ariaLabels' => [
                            'decrease' => 'Decrease value 1',
                            'increase' => 'Increase value 1'
                        ]
                    ],
                    [
                        'id' => 'item-value-2',
                        'name' => 'value2',
                        'label' => 'Value 2:',
                        'defaultValue' => 5,
                        'ariaLabels' => [
                            'decrease' => 'Decrease value 2',
                            'increase' => 'Increase value 2'
                        ]
                    ]
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