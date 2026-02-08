{{-- PR Feed List Component --}}
<div class="pr-feed-list">
    @if(empty($data['items']))
        <div class="empty-state">
            <p>{{ $data['emptyMessage'] }}</p>
        </div>
    @else
        <div class="pr-feed">
            @foreach($data['items'] as $liftLog)
                <div class="pr-card">
                    <div class="pr-header">
                        <div class="pr-header-left">
                            <strong>{{ $liftLog->user_id === ($data['currentUserId'] ?? null) ? 'You' : $liftLog->user->name }}</strong>
                            <span class="pr-exercise">{{ $liftLog->exercise->title }}</span>
                        </div>
                        <span class="pr-time">{{ $liftLog->achieved_at->diffForHumans() }}</span>
                    </div>
                    <div class="pr-body">
                        {{-- Show weight and reps from the lift log --}}
                        @php
                            // Get weight and reps from the lift log
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
