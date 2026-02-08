<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalRecord;
use App\Services\ComponentBuilder as C;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->latest('achieved_at')
            ->paginate(50);
        
        $components = [
            C::title(
                'PR Feed',
                'Recent personal records from all users'
            )->build(),
        ];
        
        // Add session messages if present
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        $components[] = C::prFeedList()
            ->paginator($prs)
            ->build();
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
