{{-- PR Feed List Component --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle high five button clicks
    document.querySelectorAll('.high-five-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = this.closest('form');
            const url = form.action;
            const csrfToken = form.querySelector('[name="_token"]').value;
            const highFiveInfo = this.closest('.high-five-info');
            
            // Disable button during request
            this.disabled = true;
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button state
                    const icon = this.querySelector('i');
                    if (data.highFived) {
                        this.classList.add('high-fived');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        this.title = 'Remove high five';
                    } else {
                        this.classList.remove('high-fived');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        this.title = 'Give high five';
                    }
                    
                    // Update count
                    let countSpan = this.querySelector('.high-five-count');
                    if (data.count > 0) {
                        if (!countSpan) {
                            countSpan = document.createElement('span');
                            countSpan.className = 'high-five-count';
                            this.appendChild(countSpan);
                        }
                        countSpan.textContent = data.count;
                    } else if (countSpan) {
                        countSpan.remove();
                    }
                    
                    // Update or create names display
                    let namesDiv = highFiveInfo.querySelector('.high-five-names');
                    if (data.count > 0 && data.names && data.verb) {
                        if (!namesDiv) {
                            namesDiv = document.createElement('div');
                            namesDiv.className = 'high-five-names';
                            highFiveInfo.appendChild(namesDiv);
                        }
                        namesDiv.innerHTML = data.names + ' ' + data.verb + '&nbsp;this!';
                    } else if (namesDiv) {
                        namesDiv.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error toggling high five:', error);
            })
            .finally(() => {
                // Re-enable button
                this.disabled = false;
            });
        });
    });
});
</script>

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
                    
                    $isOwnPR = $user->id === ($data['currentUserId'] ?? null);
                    
                    // Check if new (applies to all PRs, including own)
                    // If never viewed, all PRs in last 7 days are new
                    // Otherwise, check if within 24 hours AND after last viewed
                    $lastViewed = $data['lastFeedViewedAt'] ?? null;
                    if (!$lastViewed) {
                        $isNew = true;
                    } else {
                        $isWithin24Hours = $date->isAfter(now()->subHours(24));
                        $isAfterLastViewed = $date->isAfter($lastViewed);
                        $isNew = $isWithin24Hours && $isAfterLastViewed;
                    }
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
                            @php
                                // Get all PRs for this lift log and collect their high fives
                                $allPRsForLiftLog = $liftLog->allPRs ?? collect([$liftLog]);
                                $allHighFives = $allPRsForLiftLog->flatMap(fn($pr) => $pr->highFives ?? collect());
                                $highFiveCount = $allHighFives->count();
                                $currentUserHighFived = $allHighFives->contains('user_id', $data['currentUserId'] ?? null);
                                // Get the first PR ID for the toggle action
                                $firstPRId = $allPRsForLiftLog->first()->id ?? null;
                                $isOwnPR = $user->id === ($data['currentUserId'] ?? null);
                            @endphp
                            <div class="pr-lift-session">
                                @php
                                    $weight = $liftLog->liftLog?->display_weight ?? 0;
                                    $reps = $liftLog->liftLog?->display_reps ?? 0;
                                    $sets = $liftLog->liftLog?->display_rounds ?? 0;
                                @endphp
                                <div class="pr-lift-header">
                                    <div class="pr-lift-title-row">
                                        <div class="pr-lift-title-column">
                                            <strong>{{ $liftLog->exercise->title }}</strong>
                                            
                                            {{-- PR Types - Directly under exercise name --}}
                                            @php
                                                // Ensure allPRs exists
                                                if (!isset($liftLog->allPRs)) {
                                                    $liftLog->allPRs = collect([$liftLog]);
                                                }
                                                
                                                // Build friendly PR descriptions as array
                                                $prDescriptions = $liftLog->allPRs->map(function($pr) {
                                                    return match($pr->pr_type) {
                                                        'one_rm' => 'new max weight',
                                                        'rep_specific' => 'most weight for ' . ($pr->rep_count ?? '') . ' rep' . (($pr->rep_count ?? 1) > 1 ? 's' : ''),
                                                        'volume' => 'most total volume',
                                                        'density' => 'most sets at this weight',
                                                        'time' => 'best time',
                                                        'endurance' => 'most reps',
                                                        'consistency' => 'most consistent',
                                                        'hypertrophy' => 'hypertrophy PR',
                                                        default => strtolower(str_replace('_', ' ', $pr->pr_type))
                                                    };
                                                })->toArray();
                                            @endphp
                                            @if(!empty($prDescriptions))
                                                <div class="pr-types">
                                                    @foreach($prDescriptions as $description)
                                                        <span class="pr-type-label"><i class="fas fa-check"></i> {{ ucfirst($description) }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        
                                        @if($weight > 0 || $reps > 0)
                                            <div class="pr-lift-pills">
                                                @if($sets > 0 && $reps > 0)
                                                    <span class="pr-reps-pill">{{ $sets }} x {{ $reps }}</span>
                                                @elseif($reps > 0)
                                                    <span class="pr-reps-pill">{{ $reps }} reps</span>
                                                @endif
                                                @if($weight > 0)
                                                    <span class="pr-weight-pill{{ $isNew ? ' pr-weight-pill-new' : '' }}">{{ number_format($weight, 0) }} lbs</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    
                                    {{-- First PR note if any PR is first --}}
                                    @if($liftLog->allPRs->whereNull('previous_value')->count() > 0)
                                        <div class="pr-first">
                                            <i class="fas fa-star"></i>
                                            First PR!
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="pr-lift-content">
                                    
                                    {{-- Right column: High five info --}}
                                    @if($firstPRId)
                                        @php
                                            $names = $allHighFives->pluck('user.name')->toArray();
                                            $userIds = $allHighFives->pluck('user_id')->toArray();
                                            $nameCount = count($names);
                                            
                                            // Replace current user's name with "You"
                                            $names = array_map(function($name, $userId) use ($data) {
                                                return $userId === ($data['currentUserId'] ?? null) ? 'You' : $name;
                                            }, $names, $userIds);
                                            
                                            // Sort so "You" always comes first
                                            usort($names, function($a, $b) {
                                                if ($a === 'You') return -1;
                                                if ($b === 'You') return 1;
                                                return 0;
                                            });
                                            
                                            // Determine verb based on whether "You" is in the list
                                            $hasYou = in_array('You', $names);
                                            $verb = $hasYou ? 'love' : 'loves';
                                            
                                            if ($nameCount === 1) {
                                                $formattedNames = '<strong>' . $names[0] . '</strong>';
                                            } elseif ($nameCount === 2) {
                                                $formattedNames = '<strong>' . $names[0] . '</strong> and <strong>' . $names[1] . '</strong>';
                                            } elseif ($nameCount > 2) {
                                                $lastIndex = $nameCount - 1;
                                                $allButLast = array_slice($names, 0, $lastIndex);
                                                $formattedNames = '<strong>' . implode('</strong>, <strong>', $allButLast) . '</strong>, and <strong>' . $names[$lastIndex] . '</strong>';
                                            } else {
                                                $formattedNames = '';
                                            }
                                        @endphp
                                        
                                        <div class="high-five-info">
                                            @if($isOwnPR)
                                                {{-- Non-interactive display for own PRs --}}
                                                @if($highFiveCount > 0)
                                                    <div class="high-five-display">
                                                        <i class="fas fa-heart"></i>
                                                        <span class="high-five-count">{{ $highFiveCount }}</span>
                                                    </div>
                                                @endif
                                            @else
                                                {{-- Interactive button for others' PRs --}}
                                                <form method="POST" action="{{ route('feed.toggle-high-five', $firstPRId) }}" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="high-five-btn {{ $currentUserHighFived ? 'high-fived' : '' }}" title="{{ $currentUserHighFived ? 'Remove high five' : 'Give high five' }}">
                                                        <i class="{{ $currentUserHighFived ? 'fas' : 'far' }} fa-heart"></i>
                                                        @if($highFiveCount > 0)
                                                            <span class="high-five-count">{{ $highFiveCount }}</span>
                                                        @endif
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            {{-- Show names for everyone if there are high fives --}}
                                            @if($highFiveCount > 0)
                                                <div class="high-five-names">
                                                    {!! $formattedNames !!} {{ $verb }}&nbsp;this!
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
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
