<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ComponentBuilder as C;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $liked = session('liked', false);
        
        $data = [
            'components' => [
                C::form('like-form', '')
                    ->formAction(route('feed.like'))
                    ->submitButton($liked ? 'Liked' : 'Like')
                    ->submitButtonClass($liked ? 'btn btn-primary' : 'btn btn-secondary')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    public function like(Request $request)
    {
        $liked = session('liked', false);
        session(['liked' => !$liked]);
        
        return redirect()->route('feed.index');
    }
}
