{{-- Button Component --}}
<div class="component-button-section" data-initial-state="{{ $data['initialState'] ?? 'visible' }}">
    @if(($data['type'] ?? 'button') === 'link')
        <a href="{{ $data['url'] }}" class="component-button {{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}" onclick="window.location.href='{{ $data['url'] }}'; return false;">
            @if(isset($data['icon']))<i class="fas {{ $data['icon'] }} button-icon"></i>@endif{{ $data['text'] }}
        </a>
    @else
        <button type="button" class="component-button {{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}">
            @if(isset($data['icon']))<i class="fas {{ $data['icon'] }} button-icon"></i>@endif{{ $data['text'] }}
        </button>
    @endif
</div>
