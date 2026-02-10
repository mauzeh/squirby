<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\PRHighFive;
use App\Models\PRComment;
use App\Models\Notification;
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
        // Filter to only include exercises that have show_in_feed enabled
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog', 'highFives.user', 'comments.user'])
            ->current()
            ->where('achieved_at', '>=', now()->subDays(7))
            ->whereIn('user_id', $followingIds)
            ->whereHas('exercise', function ($query) {
                $query->where('show_in_feed', true);
            })
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
        
        // Check if there are any new PRs (including own PRs) - for badge display
        $hasNewPRs = $groupedPrs->contains(function ($item) use ($currentUser) {
            // If never viewed, all PRs in last 7 days are new
            if (!$currentUser->last_feed_viewed_at) {
                return true;
            }
            
            // Check if this PR is from a newly followed user
            $followRelationship = $currentUser->following()
                ->wherePivot('following_id', $item->user_id)
                ->first();
            
            if ($followRelationship && $followRelationship->pivot->created_at->isAfter($currentUser->last_feed_viewed_at)) {
                // This is a newly followed user, show their PRs as new
                return true;
            }
            
            // Otherwise, check if within 24 hours AND after last viewed
            $isWithin24Hours = $item->achieved_at->isAfter(now()->subHours(24));
            $isAfterLastViewed = $item->achieved_at->isAfter($currentUser->last_feed_viewed_at);
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
        
        // Add FAB for quick connection
        $components[] = C::rawHtml('<a href="' . route('connections.index') . '" class="fab" title="Connect"><i class="fas fa-user-plus"></i></a>');
        
        $data = [
            'components' => $components,
        ];
        
        // Mark as read AFTER the response is sent to the browser (skip if impersonating on production)
        $isImpersonatingOnProduction = session()->has('impersonator_id') && app()->environment('production');
        if (!$isImpersonatingOnProduction) {
            $userId = $currentUser->id;
            app()->terminating(function () use ($userId) {
                \App\Models\User::where('id', $userId)->update(['last_feed_viewed_at' => now()]);
            });
        }
        
        return view('mobile-entry.flexible', compact('data'));
    }

    public function users(Request $request)
    {
        $currentUser = $request->user();
        
        // Check if user is admin or being impersonated
        $isAdminOrImpersonated = $currentUser->hasRole('Admin') || session()->has('impersonator_id');
        
        // Get IDs of users that current user is following
        $followingIds = $currentUser->following()->pluck('users.id')->toArray();
        
        // For regular users, only show users they're following
        // For admins and impersonated users, show all users
        if ($isAdminOrImpersonated) {
            $users = User::where('id', '!=', $currentUser->id)
                ->orderBy('name')
                ->get();
        } else {
            // Only show users the current user is following
            $users = User::whereIn('id', $followingIds)
                ->orderBy('name')
                ->get();
        }
        
        // Separate users into following and not following (only relevant for admins/impersonated)
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
        
        // Then add not following users (only for admins/impersonated)
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
        
        // Add FAB for quick connection
        $components[] = C::rawHtml('<a href="' . route('connections.index') . '" class="fab" title="Connect"><i class="fas fa-user-plus"></i></a>');
        
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
            : '<div class="user-profile-avatar">
                <i class="fas fa-user-circle"></i>
                ' . ($isSelf ? '<a href="' . route('profile.edit') . '" class="user-profile-avatar-upload-btn">
                    <i class="fas fa-camera"></i> Add Photo
                </a>' : '') . '
            </div>';
        
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
        // Filter to only include exercises that have show_in_feed enabled
        $prs = PersonalRecord::with(['user', 'exercise', 'liftLog', 'comments.user'])
            ->current()
            ->where('user_id', $user->id)
            ->whereHas('exercise', function ($query) {
                $query->where('show_in_feed', true);
            })
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

    public function toggleHighFive(Request $request, PersonalRecord $personalRecord)
    {
        $currentUser = $request->user();
        
        // Check if user already high-fived this PR
        $existingHighFive = PRHighFive::where('user_id', $currentUser->id)
            ->where('personal_record_id', $personalRecord->id)
            ->first();
        
        if ($existingHighFive) {
            // Remove high five
            $existingHighFive->delete();
            $highFived = false;
        } else {
            // Add high five
            PRHighFive::create([
                'user_id' => $currentUser->id,
                'personal_record_id' => $personalRecord->id,
            ]);
            $highFived = true;
        }
        
        // Get updated count and names
        $highFives = PRHighFive::where('personal_record_id', $personalRecord->id)
            ->with('user')
            ->get();
        $highFiveCount = $highFives->count();
        
        // Format names for display
        $names = $highFives->pluck('user.name')->toArray();
        $userIds = $highFives->pluck('user_id')->toArray();
        
        // Replace current user's name with "You"
        $names = array_map(function($name, $userId) use ($currentUser) {
            return $userId === $currentUser->id ? 'You' : $name;
        }, $names, $userIds);
        
        // Sort so "You" always comes first
        usort($names, function($a, $b) {
            if ($a === 'You') return -1;
            if ($b === 'You') return 1;
            return 0;
        });
        
        $formattedNames = '';
        $nameCount = count($names);
        
        // Determine verb based on count and whether "You" is in the list
        $hasYou = in_array('You', $names);
        // Use "love" for multiple people or when "You" is included, "loves" for single person
        $verb = ($nameCount > 1 || $hasYou) ? 'love' : 'loves';
        
        if ($nameCount === 1) {
            $formattedNames = '<strong>' . $names[0] . '</strong>';
        } elseif ($nameCount === 2) {
            $formattedNames = '<strong>' . $names[0] . '</strong> and <strong>' . $names[1] . '</strong>';
        } elseif ($nameCount > 2) {
            $lastIndex = $nameCount - 1;
            $allButLast = array_slice($names, 0, $lastIndex);
            $formattedNames = '<strong>' . implode('</strong>, <strong>', $allButLast) . '</strong>, and <strong>' . $names[$lastIndex] . '</strong>';
        }
        
        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'highFived' => $highFived,
                'count' => $highFiveCount,
                'names' => $formattedNames,
                'verb' => $verb,
            ]);
        }
        
        return redirect()->back();
    }

    public function storeComment(Request $request, PersonalRecord $personalRecord)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $currentUser = $request->user();

        $comment = PRComment::create([
            'personal_record_id' => $personalRecord->id,
            'user_id' => $currentUser->id,
            'comment' => $request->comment,
        ]);

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            // Load the user relationship
            $comment->load('user');
            
            // Render the comment HTML using the partial
            $html = view('mobile-entry.components.partials.pr-comment', [
                'comment' => $comment,
                'currentUserId' => $currentUser->id,
            ])->render();

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);
        }

        return redirect()->back()->with('success', 'Comment added!');
    }

    public function deleteComment(Request $request, PRComment $comment)
    {
        $currentUser = $request->user();

        // Only allow deleting own comments or if admin
        if ($comment->user_id !== $currentUser->id && !$currentUser->hasRole('Admin')) {
            abort(403, 'Unauthorized action.');
        }

        $comment->delete();

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->back()->with('success', 'Comment deleted!');
    }

    public function notifications(Request $request)
    {
        $currentUser = $request->user();
        
        // Get recent notifications (last 30 days)
        $notifications = Notification::with(['actor', 'notifiable'])
            ->where('user_id', $currentUser->id)
            ->recent()
            ->latest()
            ->paginate(50);
        
        $unreadCount = $currentUser->notifications()->unread()->count();
        
        $components = [
            C::title(
                'Notifications',
                $unreadCount > 0 ? "{$unreadCount} unread" : 'All caught up!'
            )->build(),
        ];
        
        // Add session messages if present
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Build notification list
        if ($notifications->isEmpty()) {
            $components[] = [
                'type' => 'raw_html',
                'data' => [
                    'html' => '<div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 2em; text-align: center; margin: 1em 0;">
                        <i class="fas fa-spa" style="font-size: 3em; color: rgba(255, 255, 255, 0.3); margin-bottom: 0.5em;"></i>
                        <p style="font-size: 1.1em; margin-bottom: 0.5em; color: #f2f2f2;">Nothing to see here. Yet.</p>
                        <p style="color: #999; font-size: 0.95em; margin: 0;">When someone high-fives your PR or leaves a comment, we\'ll let you know. Until then, enjoy the silence.</p>
                    </div>'
                ]
            ];
        } else {
            foreach ($notifications as $notification) {
                $components[] = $this->buildNotificationComponent($notification, $currentUser);
            }
        }
        
        $data = [
            'components' => $components,
        ];
        
        // Mark as read AFTER the response is sent to the browser (skip if impersonating on production)
        $isImpersonatingOnProduction = session()->has('impersonator_id') && app()->environment('production');
        if (!$isImpersonatingOnProduction) {
            app()->terminating(function () use ($currentUser) {
                $currentUser->notifications()->unread()->update(['read_at' => now()]);
            });
        }
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    private function buildNotificationComponent(Notification $notification, User $currentUser): array
    {
        $actor = $notification->actor;
        $isUnread = $notification->isUnread();
        
        // Build notification message based on type
        $message = '';
        $link = '#';
        
        switch ($notification->type) {
            case 'pr_comment':
                $commentPreview = $notification->data['comment_preview'] ?? '';
                $message = "commented on your PR: \"{$commentPreview}\"";
                // Link to the PR owner's profile (the notification recipient)
                $link = route('feed.users.show', $notification->user_id);
                break;
                
            case 'pr_high_five':
                $message = "gave you a high five!";
                // Link to the PR owner's profile (the notification recipient)
                $link = route('feed.users.show', $notification->user_id);
                break;
                
            case 'new_pr':
                $message = "achieved a new PR!";
                // Link to the actor's profile (the person who achieved the PR)
                $link = route('feed.users.show', $actor->id);
                break;
        }
        
        $timeAgo = $notification->created_at->diffForHumans();
        $unreadClass = $isUnread ? 'notification-unread' : '';
        
        // Avatar HTML
        $avatarHtml = $actor->profile_photo_url 
            ? '<img src="' . e($actor->profile_photo_url) . '" alt="' . e($actor->name) . '" class="notification-avatar-img">'
            : '<i class="fas fa-user-circle"></i>';
        
        return [
            'type' => 'raw_html',
            'data' => [
                'html' => "
                    <div class='notification-item {$unreadClass}'>
                        <a href='{$link}' class='notification-link'>
                            <div class='notification-avatar'>
                                {$avatarHtml}
                            </div>
                            <div class='notification-content'>
                                <div class='notification-message'>
                                    <span class='notification-actor'>{$actor->name}</span> {$message}
                                </div>
                                <div class='notification-time'>{$timeAgo}</div>
                            </div>
                        </a>
                    </div>
                "
            ]
        ];
    }
    


}
