{{-- PR Cards Component - Display personal records in a card grid --}}
<section class="component-pr-cards-section{{ isset($data['scrollable']) && $data['scrollable'] ? ' pr-cards-scrollable' : '' }}" aria-label="{{ $data['ariaLabel'] ?? 'Personal Records' }}">
    @if(isset($data['title']) && !empty($data['title']))
    <h2 class="component-pr-cards-title">{{ $data['title'] }}</h2>
    @endif
    
    <div class="pr-cards-container{{ isset($data['scrollable']) && $data['scrollable'] ? ' pr-cards-horizontal' : '' }}">
        @foreach($data['cards'] as $card)
        <div class="pr-card{{ isset($card['is_recent']) && $card['is_recent'] ? ' pr-card--recent' : '' }}">
            <div class="pr-card-label">{{ $card['label'] }}</div>
            <div class="pr-card-value">
                @if($card['value'] !== null)
                    {{ $card['value'] }}@if($card['unit']) <span class="pr-card-unit">{{ $card['unit'] }}</span>@endif
                @else
                    —
                @endif
            </div>
            @if(isset($card['date']) && $card['date'])
            <div class="pr-card-date">{{ \Carbon\Carbon::parse($card['date'])->diffForHumans() }}</div>
            @endif
        </div>
        @endforeach
    </div>
</section>
