<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\ComponentBuilder as C;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        // Get PRs from the last 7 days, grouped by lift_log_id
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->where('achieved_at', '>=', now()->subDays(7))
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
                'Recent personal records from the last 7 days'
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
                'emptyMessage' => 'No PRs logged in the last 7 days.',
            ]
        ];
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    public function users(Request $request)
    {
        $users = User::where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->get();
        
        $components = [
            C::title(
                'Users',
                'Follow other users to see their PRs in your feed'
            )->build(),
        ];
        
        // Add session messages if present
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Build user list using ItemList component
        $itemList = C::itemList()
            ->filterPlaceholder('Search users...')
            ->noResultsMessage('No users found.')
            ->initialState('expanded')
            ->showCancelButton(false)
            ->restrictHeight(false);
        
        foreach ($users as $user) {
            $itemList->item(
                id: (string) $user->id,
                name: $user->name,
                href: route('feed.users.show', $user),
                typeLabel: '',
                typeCssClass: 'user',
                priority: 3
            );
        }
        
        $components[] = $itemList->build();
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    public function showUser(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isFollowing = $currentUser->isFollowing($user);
        $isSelf = $currentUser->id === $user->id;
        
        // Load counts
        $user->loadCount(['followers', 'following', 'personalRecords']);
        
        $components = [
            C::title(
                $user->name,
                'User Profile'
            )
            ->backButton('fa-arrow-left', route('feed.users'), 'Back to users')
            ->build(),
        ];
        
        // Add session messages if present
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // User stats summary
        $components[] = C::summary()
            ->item('PRs', $user->personal_records_count)
            ->item('Followers', $user->followers_count)
            ->item('Following', $user->following_count)
            ->build();
        
        // Follow/Unfollow button (only if not viewing own profile)
        if (!$isSelf) {
            if ($isFollowing) {
                // Unfollow form
                $components[] = C::form('unfollow-form')
                    ->formAction(route('feed.users.unfollow', $user))
                    ->hiddenField('_method', 'DELETE')
                    ->submitButton('Unfollow')
                    ->submitButtonClass('btn-secondary')
                    ->build();
            } else {
                // Follow form
                $components[] = C::form('follow-form')
                    ->formAction(route('feed.users.follow', $user))
                    ->submitButton('Follow')
                    ->submitButtonClass('btn-primary')
                    ->build();
            }
        }
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    public function followUser(Request $request, User $user)
    {
        $currentUser = $request->user();
        
        if ($currentUser->id === $user->id) {
            return redirect()->back()->with('error', 'You cannot follow yourself.');
        }
        
        $currentUser->follow($user);
        
        return redirect()->back()->with('success', "You are now following {$user->name}.");
    }

    public function unfollowUser(Request $request, User $user)
    {
        $currentUser = $request->user();
        $currentUser->unfollow($user);
        
        return redirect()->back()->with('success', "You have unfollowed {$user->name}.");
    }
}
