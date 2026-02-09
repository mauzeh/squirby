{{-- PR Feed List Component --}}
<div class="pr-feed-list">
    @if(empty($data['items']))
        <div class="empty-state">
            <p>{{ $data['emptyMessage'] }}</p>
        </div>
    @else
        <div class="pr-feed">
            @foreach($data['items'] as $mainItem)
                @php
                    // Get all lift logs for this user-date group
                    $allLiftLogs = $mainItem->allLiftLogs ?? collect([$mainItem]);
                    $user = $mainItem->user;
                    $date = $mainItem->achieved_at;
                    $exerciseCount = $allLiftLogs->count(); // Count exercises instead of total PRs
                    
                    // Check if new: within 24 hours AND after last viewed (or never viewed)
                    $isWithin24Hours = $date->isAfter(now()->subHours(24));
                    $lastViewed = $data['lastFeedViewedAt'] ?? null;
                    $isAfterLastViewed = !$lastViewed || $date->isAfter($lastViewed);
                    $isNew = $isWithin24Hours && $isAfterLastViewed;
                @endphp
                <div class="pr-card{{ $isNew ? ' pr-card-new' : '' }}">
                    <div class="pr-header">
                        <div class="pr-header-left">
                            <a href="{{ route('feed.users.show', $user) }}" class="pr-user-info">
                                <div class="pr-avatar">
                                    @if($user->profile_photo_url)
                                        <img src="{{ $user->profile_photo_url }}" alt="Profile photo" class="pr-avatar-img">
                                    @else
                                        <i class="fas fa-user-circle"></i>
                                    @endif
                                </div>
                                <div class="pr-user-details">
                                    <strong>{{ $user->id === ($data['currentUserId'] ?? null) ? 'You' : $user->name }}</strong>
                                    <span class="pr-exercise">{{ $exerciseCount }} PR{{ $exerciseCount > 1 ? 's' : '' }} on {{ $date->format('M j') }}</span>
                                </div>
                            </a>
                        </div>
                        <div class="pr-header-right">
                            @if($isNew)
                                <span class="pr-new-badge">NEW</span>
                            @endif
                            <span class="pr-time">{{ $date->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="pr-body">
                        {{-- Show all lift logs for this user-date --}}
                        @foreach($allLiftLogs as $liftLog)
                            <div class="pr-lift-session">
                                <div class="pr-lift-header">
                                    <strong>{{ $liftLog->exercise->title }}</strong>
                                </div>
                                
                                {{-- Show weight and reps from the lift log --}}
                                @php
                                    $weight = $liftLog->liftLog?->display_weight ?? 0;
                                    $reps = $liftLog->liftLog?->display_reps ?? 0;
                                @endphp
                                @if($weight > 0 || $reps > 0)
                                    <div class="pr-lift-details">
                                        @if($weight > 0)
                                            <span class="pr-weight">{{ number_format($weight, 0) }} lbs</span>
                                        @endif
                                        @if($reps > 0)
                                            <span class="pr-reps">{{ $reps }} reps</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- PR Badges --}}
                                @php
                                    // Ensure allPRs exists
                                    if (!isset($liftLog->allPRs)) {
                                        $liftLog->allPRs = collect([$liftLog]);
                                    }
                                @endphp
                                <div class="pr-badges">
                                    @foreach($liftLog->allPRs as $pr)
                                        <span class="pr-badge">{{ match($pr->pr_type) {
                                            'one_rm' => '1RM',
                                            'rep_specific' => ($pr->rep_count ?? '') . ' Rep' . (($pr->rep_count ?? 1) > 1 ? 's' : ''),
                                            'volume' => 'Volume',
                                            'density' => 'Density',
                                            'time' => 'Time',
                                            'endurance' => 'Endurance',
                                            'consistency' => 'Consistency',
                                            default => ucfirst(str_replace('_', ' ', $pr->pr_type))
                                        } }}</span>
                                    @endforeach
                                </div>

                                {{-- First PR note if any PR is first --}}
                                @if($liftLog->allPRs->whereNull('previous_value')->count() > 0)
                                    <div class="pr-first">
                                        <i class="fas fa-star"></i>
                                        First PR!
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if(isset($data['paginator']) && $data['paginator'])
            <div class="pagination-wrapper">
                {{ $data['paginator']->links() }}
            </div>
        @endif
    @endif
</div>
