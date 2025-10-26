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
        // Sample data for demonstration
        $sampleData = [
            'currentDate' => 'Today',
            'summary' => [
                'total' => 1250,
                'completed' => 3,
                'average' => 85,
                'today' => 12
            ],
            'loggedItem' => [
                'value' => 25,
                'comment' => 'Morning workout completed'
            ],
            'formDefaults' => [
                'value' => 10,
                'comment' => ''
            ]
        ];

        return view('mobile-entry.index', compact('sampleData'));
    }
}