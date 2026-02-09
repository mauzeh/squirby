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
        
        // Get PRs from the last 7 days, only from users being followed (including self)
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->where('achieved_at', '>=', now()->subDays(7))
            ->whereIn('user_id', $followingIds)
            ->latest('achieved_at')
            ->get();
        
        // Group by user and date (not lift_log_id)
        $groupedPrs = $prs->groupBy(function ($pr) {
            return $pr->user_id . '_' . $pr->achieved_at->format('Y-m-d');
        })
        ->map(function ($group) {
            // For each user-date group, further group by lift_log_id
            $liftLogGroups = $group->groupBy('lift_log_id')->map(function ($liftLogGroup) {
                $main = $liftLogGroup->first();
                $main->allPRs = $liftLogGroup;
                return $main;
            })->values();
            
            // Return the first lift log as the main item with all lift logs for that user-date
            $main = $liftLogGroups->first();
            $main->allLiftLogs = $liftLogGroups;
            return $main;
        })
        ->values()
        ->take(50);
        
        // Check if there are any new PRs (within 24 hours OR after last viewed)
        $hasNewPRs = $groupedPrs->contains(function ($item) use ($currentUser) {
            $isWithin24Hours = $item->achieved_at->isAfter(now()->subHours(24));
            $isAfterLastViewed = !$currentUser->last_feed_viewed_at || 
                                 $item->achieved_at->isAfter($currentUser->last_feed_viewed_at);
            return $isWithin24Hours && $isAfterLastViewed;
        });
        
        $components = [
            C::title(
                'PR Feed',
                'Recent personal records from you and your friends'
            )->build(),
        ];
        
        // Add session messages if present
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add "Mark all as read" button if there are new PRs
        if ($hasNewPRs) {
            $components[] = [
                'type' => 'raw_html',
                'data' => [
                    'html' => '<div class="feed-action-buttons">
                        <form method="POST" action="' . route('feed.mark-read') . '" style="display: inline;">
                            ' . csrf_field() . '
                            <button type="submit" class="feed-action-btn">Mark all as read</button>
                        </form>
                    </div>'
                ]
            ];
        } elseif ($currentUser->hasRole('Admin') || session()->has('impersonator_id')) {
            // Add "Reset to unread" button for admins and impersonating users (only when no new PRs)
            $components[] = [
                'type' => 'raw_html',
                'data' => [
                    'html' => '<div class="feed-action-buttons">
                        <form method="POST" action="' . route('feed.reset-read') . '" style="display: inline;">
                            ' . csrf_field() . '
                            <button type="submit" class="feed-action-btn feed-action-btn-secondary">Reset to unread</button>
                        </form>
                    </div>'
                ]
            ];
        }
        
        // Build PR feed component manually to bypass type checking
        $components[] = [
            'type' => 'pr-feed-list',
            'data' => [
                'items' => $groupedPrs->all(),
                'paginator' => null,
                'emptyMessage' => 'No PRs in the last 7 days.',
                'currentUserId' => $currentUser->id,
                'lastFeedViewedAt' => $currentUser->last_feed_viewed_at,
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
                'Find Friends',
                'Find your friends and follow them to see their PRs in your feed!'
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
        
        // Determine back button URL based on referrer
        $referrer = $request->headers->get('referer');
        $backUrl = route('feed.users'); // Default to users list
        $backLabel = 'Back to users';
        
        if ($referrer && str_contains($referrer, route('feed.index'))) {
            $backUrl = route('feed.index');
            $backLabel = 'Back to feed';
        }
        
        $components = [
            C::title(
                $user->name,
                'User Profile'
            )
            ->backButton('fa-arrow-left', $backUrl, $backLabel)
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
        
        // Get user's last 10 PRs grouped by user-date (same logic as feed)
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog'])
            ->current()
            ->where('user_id', $user->id)
            ->latest('achieved_at')
            ->take(50) // Get more to ensure we have enough after grouping
            ->get();
        
        // Group by date (not user since it's all the same user)
        $groupedPrs = $prs->groupBy(function ($pr) {
            return $pr->achieved_at->format('Y-m-d');
        })
        ->map(function ($group) {
            // For each date group, further group by lift_log_id
            $liftLogGroups = $group->groupBy('lift_log_id')->map(function ($liftLogGroup) {
                $main = $liftLogGroup->first();
                $main->allPRs = $liftLogGroup;
                return $main;
            })->values();
            
            // Return the first lift log as the main item with all lift logs for that date
            $main = $liftLogGroups->first();
            $main->allLiftLogs = $liftLogGroups;
            return $main;
        })
        ->values()
        ->take(10); // Limit to 10 date groups
        
        if ($groupedPrs->isNotEmpty()) {
            // Add section title
            $components[] = [
                'type' => 'raw_html',
                'data' => [
                    'html' => '<h3 style="margin-top: var(--spacing-xl); margin-bottom: var(--spacing-md); color: var(--text-primary); font-size: 1.25rem; font-weight: 600;">Recent PRs</h3>'
                ]
            ];
            
            // Add PR feed component
            $components[] = [
                'type' => 'pr-feed-list',
                'data' => [
                    'items' => $groupedPrs->all(),
                    'paginator' => null,
                    'emptyMessage' => 'No PRs yet.',
                    'currentUserId' => $currentUser->id,
                ]
            ];
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

    public function markAsRead(Request $request)
    {
        $currentUser = $request->user();
        $currentUser->update([
            'last_feed_viewed_at' => now(),
        ]);
        
        return redirect()->route('feed.index');
    }

    public function resetRead(Request $request)
    {
        $currentUser = $request->user();
        
        // Only allow admins and impersonating users
        if (!$currentUser->hasRole('Admin') && !session()->has('impersonator_id')) {
            abort(403, 'Unauthorized action.');
        }
        
        $currentUser->update([
            'last_feed_viewed_at' => null,
        ]);
        
        return redirect()->route('feed.index');
    }
}
