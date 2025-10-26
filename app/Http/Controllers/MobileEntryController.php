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
            
            // Item logging form
            'itemForm' => [
                'title' => 'Log New Item',
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
                        'comment' => 'Morning workout completed'
                    ],
                    [
                        'value' => 15,
                        'comment' => 'Quick afternoon session'
                    ],
                    [
                        'value' => 30,
                        'comment' => 'Evening cardio and stretching routine'
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