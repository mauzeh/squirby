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
                'labels' => [
                    'value' => 'Value:',
                    'comment' => 'Comment:'
                ],
                'placeholders' => [
                    'comment' => 'Add a comment...'
                ],
                'buttons' => [
                    'decrement' => '-',
                    'increment' => '+',
                    'submit' => 'Log Item'
                ],
                'defaults' => [
                    'value' => 10,
                    'comment' => ''
                ],
                'ariaLabels' => [
                    'section' => 'Log new item',
                    'decreaseValue' => 'Decrease value',
                    'increaseValue' => 'Increase value',
                    'deleteForm' => 'Delete form'
                ]
            ],
            
            // Logged items
            'loggedItems' => [
                'title' => 'Item Entry',
                'sampleItem' => [
                    'value' => 25,
                    'comment' => 'Morning workout completed'
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