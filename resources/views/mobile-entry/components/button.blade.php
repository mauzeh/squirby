{{-- Button Component --}}
<div class="component-button-section">
    @if(($data['type'] ?? 'button') === 'link')
        <a href="{{ $data['url'] }}" class="{{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}">
            {{ $data['text'] }}
        </a>
    @else
        <button type="button" class="{{ $data['cssClass'] }}" aria-label="{{ $data['ariaLabel'] }}">
            {{ $data['text'] }}
        </button>
    @endif
</div>
