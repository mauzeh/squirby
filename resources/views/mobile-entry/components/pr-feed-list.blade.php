{{-- PR Feed List Component --}}
<div class="pr-feed-list">
    @if(empty($data['items']))
        <div class="empty-state">
            <p>{{ $data['emptyMessage'] }}</p>
        </div>
    @else
        <div class="pr-feed">
            @foreach($data['items'] as $pr)
                <div class="pr-card">
                    <div class="pr-header">
                        <div class="pr-header-left">
                            <strong>{{ $pr->user->name }}</strong>
                            <span class="pr-exercise">{{ $pr->exercise->title }}</span>
                        </div>
                        <span class="pr-time">{{ $pr->achieved_at->diffForHumans() }}</span>
                    </div>
                    <div class="pr-body">
                        <div class="pr-details">
                            <span class="pr-type">{{ match($pr->pr_type) {
                                'one_rm' => '1RM',
                                'rep_specific' => ($pr->rep_count ?? '') . ' Rep' . (($pr->rep_count ?? 1) > 1 ? 's' : ''),
                                'volume' => 'Volume',
                                'density' => 'Density',
                                'time' => 'Time',
                                'endurance' => 'Endurance',
                                'consistency' => 'Consistency',
                                default => ucfirst(str_replace('_', ' ', $pr->pr_type))
                            } }}</span>
                            @if($pr->rep_count && $pr->pr_type !== 'rep_specific')
                                <span class="pr-reps">{{ $pr->rep_count }} reps</span>
                            @endif
                            @if($pr->weight)
                                <span class="pr-weight">@ {{ $pr->weight }} lbs</span>
                            @endif
                            <span class="pr-value">{{ number_format($pr->value, 1) }}</span>
                        </div>
                        @if($pr->previous_value)
                            <div class="pr-improvement">
                                <i class="fas fa-arrow-up"></i>
                                Improved from {{ number_format($pr->previous_value, 1) }}
                                (+{{ number_format($pr->value - $pr->previous_value, 1) }})
                            </div>
                        @else
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
