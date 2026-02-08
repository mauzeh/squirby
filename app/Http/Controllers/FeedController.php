<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalRecord;
use App\Services\ComponentBuilder as C;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        // Get PRs grouped by lift_log_id
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->latest('achieved_at')
            ->get()
            ->groupBy('lift_log_id')
            ->map(function ($group) {
                // Return the first PR as the main item with all PRs attached
                $main = $group->first();
                $main->allPRs = $group;
                return $main;
            })
            ->values()
            ->take(50);
        
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
        
        // Build PR feed component manually to bypass type checking
        $components[] = [
            'type' => 'pr-feed-list',
            'data' => [
                'items' => $prs->all(),
                'paginator' => null,
                'emptyMessage' => 'No PRs logged yet. Be the first!',
            ]
        ];
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
