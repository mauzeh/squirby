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
        $currentUser = $request->user();
        
        // Get IDs of users that current user is following, plus current user
        $followingIds = $currentUser->following()->pluck('users.id')->toArray();
        $followingIds[] = $currentUser->id;
        
        // Get PRs from the last 7 days, only from users being followed (including self), grouped by lift_log_id
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->where('achieved_at', '>=', now()->subDays(7))
            ->whereIn('user_id', $followingIds)
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
                'Recent personal records from you and users you follow'
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
                'emptyMessage' => 'No PRs in the last 7 days.',
                'currentUserId' => $currentUser->id,
            ]
        ];
        
        $data = [
            'components' => $components,
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    public function users(Request $request)
    {
        $currentUser = $request->user();
        
        // Get all users except current user
        $users = User::where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get();
        
        // Get IDs of users that current user is following
        $followingIds = $currentUser->following()->pluck('users.id')->toArray();
        
        // Separate users into following and not following
        $followingUsers = $users->filter(fn($user) => in_array($user->id, $followingIds));
        $notFollowingUsers = $users->filter(fn($user) => !in_array($user->id, $followingIds));
        
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
        
        // Add following users first
        foreach ($followingUsers as $user) {
            $itemList->item(
                id: (string) $user->id,
                name: $user->name,
                href: route('feed.users.show', $user),
                typeLabel: 'Following',
                typeCssClass: 'recent',
                priority: 1
            );
        }
        
        // Then add not following users
        foreach ($notFollowingUsers as $user) {
            $itemList->item(
                id: (string) $user->id,
                name: $user->name,
                href: route('feed.users.show', $user),
                typeLabel: 'Not following',
                typeCssClass: 'exercise-history',
                priority: 2
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
        
        // User avatar
        $avatarHtml = $user->profile_photo_url 
            ? '<div class="user-profile-avatar"><img src="' . e($user->profile_photo_url) . '" alt="Profile photo" class="user-profile-avatar-img"></div>'
            : '<div class="user-profile-avatar"><i class="fas fa-user-circle"></i></div>';
        
        $components[] = [
            'type' => 'raw_html',
            'data' => [
                'html' => $avatarHtml
            ]
        ];
        
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
