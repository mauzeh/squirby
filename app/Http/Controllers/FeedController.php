<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ComponentBuilder as C;

class FeedController extends Controller
{
    public function index()
    {
        $data = [
            'components' => [
                C::title('Feed', 'Your social activity feed')->build(),
                
                C::messages()
                    ->info('This is a placeholder for the social feed feature')
                    ->tip('More functionality coming soon!', 'Status:')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
