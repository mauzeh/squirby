{{-- Button Component --}}
<div class="component-button-section">
    @if(($data['type'] ?? 'button') === 'link')
        <a href="{{ $data['url'] }}" class="component-button {{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}" onclick="window.location.href='{{ $data['url'] }}'; return false;">
            {{ $data['text'] }}
        </a>
    @else
        <button type="button" class="component-button {{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}">
            {{ $data['text'] }}
        </button>
    @endif
</div>
