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
            const highFiveAction = this.closest('.high-five-action');
            
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
                    
                    // Update the text in high-five-action (either names or prompt)
                    const highFiveAction = this.closest('.high-five-action');
                    
                    // Remove existing names or prompt
                    let existingText = highFiveAction.querySelector('.high-five-names, .high-five-prompt');
                    if (existingText) {
                        existingText.remove();
                    }
                    
                    // Add new text
                    if (data.count > 0 && data.names && data.verb) {
                        const namesSpan = document.createElement('span');
                        namesSpan.className = 'high-five-names';
                        namesSpan.innerHTML = data.names + ' ' + data.verb + '&nbsp;this!';
                        highFiveAction.appendChild(namesSpan);
                    } else {
                        const promptSpan = document.createElement('span');
                        promptSpan.className = 'high-five-prompt';
                        promptSpan.textContent = 'Be the first to love this!';
                        highFiveAction.appendChild(promptSpan);
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
    
    // Handle comment form submissions
    document.querySelectorAll('.pr-comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const url = this.action;
            const csrfToken = this.querySelector('[name="_token"]').value;
            const input = this.querySelector('.pr-comment-input');
            const comment = input.value.trim();
            
            if (!comment) return;
            
            // Disable input during request
            input.disabled = true;
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ comment: comment })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input
                    input.value = '';
                    
                    // Get or create comments list
                    const commentsSection = this.closest('.pr-comments-section');
                    let commentsList = commentsSection.querySelector('.pr-comments-list');
                    
                    if (!commentsList) {
                        commentsList = document.createElement('div');
                        commentsList.className = 'pr-comments-list';
                        commentsSection.insertBefore(commentsList, this);
                    }
                    
                    // Create temporary container and insert rendered HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.html;
                    const commentEl = tempDiv.firstElementChild;
                    
                    // Add to list
                    commentsList.appendChild(commentEl);
                    
                    // Attach delete handler if it has a delete form
                    const deleteForm = commentEl.querySelector('.pr-comment-delete-form');
                    if (deleteForm) {
                        attachDeleteHandler(deleteForm);
                    }
                }
            })
            .catch(error => {
                console.error('Error posting comment:', error);
            })
            .finally(() => {
                // Re-enable input
                input.disabled = false;
                input.focus();
            });
        });
    });
    
    // Handle comment deletion
    function attachDeleteHandler(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Delete this comment?')) return;
            
            const url = this.action;
            const csrfToken = this.querySelector('[name="_token"]').value;
            const commentEl = this.closest('.pr-comment');
            
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove comment element
                    commentEl.remove();
                    
                    // Remove comments list if empty
                    const commentsList = commentEl.closest('.pr-comments-list');
                    if (commentsList && commentsList.children.length === 0) {
                        commentsList.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error deleting comment:', error);
            });
        });
    }
    
    // Attach delete handlers to existing delete forms
    document.querySelectorAll('.pr-comment-delete-form').forEach(form => {
        attachDeleteHandler(form);
    });
});
</script>

<div class="pr-feed-list">
    @if(empty($data['items']))
        <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 2em; text-align: center; margin: 1em 0;">
            <i class="fas fa-spa" style="font-size: 3em; color: rgba(255, 255, 255, 0.3); margin-bottom: 0.5em;"></i>
            <p style="font-size: 1.1em; margin-bottom: 0.5em; color: #f2f2f2;">{{ $data['emptyMessage'] }}</p>
            <p style="color: #999; font-size: 0.95em; margin: 0;">Check back soon for new PRs from your friends.</p>
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
                    
                    // Check if any PR in this group is unread
                    $readPRIds = $data['readPRIds'] ?? [];
                    $isNew = false;
                    foreach ($allLiftLogs as $liftLog) {
                        $allPRs = $liftLog->allPRs ?? collect([$liftLog]);
                        foreach ($allPRs as $pr) {
                            if (!in_array($pr->id, $readPRIds)) {
                                $isNew = true;
                                break 2; // Break out of both loops
                            }
                        }
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
                                                
                                                $hasFirstPR = $liftLog->allPRs->whereNull('previous_value')->count() > 0;
                                            @endphp
                                            @if(!empty($prDescriptions) || $hasFirstPR)
                                                <div class="pr-types">
                                                    @foreach($prDescriptions as $description)
                                                        <span class="pr-type-label"><i class="fas fa-check"></i> {{ ucfirst($description) }}</span>
                                                    @endforeach
                                                    @if($hasFirstPR)
                                                        <span class="pr-type-label pr-first"><i class="fas fa-star"></i> First PR!</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        
                                        @if($weight > 0 || $reps > 0)
                                            <div class="pr-lift-pills">
                                                @if($weight > 0)
                                                    <span class="pr-weight-pill{{ $isNew ? ' pr-weight-pill-new' : '' }}">{{ number_format($weight, 0) }} lbs</span>
                                                @endif
                                                @if($sets > 0 && $reps > 0)
                                                    <span class="pr-reps-pill">{{ $sets }} x {{ $reps }}</span>
                                                @elseif($reps > 0)
                                                    <span class="pr-reps-pill">{{ $reps }} reps</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
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
                                            } else {
                                                $formattedNames = '';
                                            }
                                            
                                            // Get comments for all PRs in this lift log
                                            $comments = $allPRsForLiftLog->flatMap(function($pr) {
                                                return $pr->comments ?? collect();
                                            })->sortBy('created_at');
                                        @endphp
                                        
                                        <div class="high-five-info">
                                            @if($isOwnPR)
                                                {{-- Non-interactive display for own PRs --}}
                                                @if($highFiveCount > 0)
                                                    <div class="high-five-display">
                                                        <i class="fas fa-heart"></i>
                                                        <span class="high-five-count">{{ $highFiveCount }}</span>
                                                    </div>
                                                    <span class="high-five-names">{!! $formattedNames !!} {{ $verb }}&nbsp;this!</span>
                                                @endif
                                            @else
                                                {{-- Interactive button for others' PRs --}}
                                                <div class="high-five-action">
                                                    <form method="POST" action="{{ route('feed.toggle-high-five', $firstPRId) }}" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="high-five-btn {{ $currentUserHighFived ? 'high-fived' : '' }}" title="{{ $currentUserHighFived ? 'Remove high five' : 'Give high five' }}">
                                                            <i class="{{ $currentUserHighFived ? 'fas' : 'far' }} fa-heart"></i>
                                                            @if($highFiveCount > 0)
                                                                <span class="high-five-count">{{ $highFiveCount }}</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                    @if($highFiveCount > 0)
                                                        <span class="high-five-names">{!! $formattedNames !!} {{ $verb }}&nbsp;this!</span>
                                                    @else
                                                        <span class="high-five-prompt">Be the first to love this!</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                {{-- Comments Section - Outside pr-lift-content --}}
                                @if($firstPRId)
                                    <div class="pr-comments-section" data-pr-id="{{ $firstPRId }}">
                                        @if($comments->count() > 0)
                                            <div class="pr-comments-list">
                                                @foreach($comments as $comment)
                                                    @include('mobile-entry.components.partials.pr-comment', ['comment' => $comment, 'currentUserId' => $data['currentUserId'] ?? null])
                                                @endforeach
                                            </div>
                                        @endif
                                        
                                        {{-- Comment Form --}}
                                        <form method="POST" action="{{ route('feed.store-comment', $firstPRId) }}" class="pr-comment-form">
                                            @csrf
                                            <div class="pr-comment-input-wrapper">
                                                <input type="text" name="comment" class="pr-comment-input" placeholder="Add a comment..." maxlength="1000" required>
                                                <button type="submit" class="pr-comment-submit-btn">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </form>
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
