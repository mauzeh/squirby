{{-- Navigation Component --}}
<nav class="date-navigation" aria-label="{{ $data['ariaLabels']['navigation'] }}">
    @if($data['prevButton'])
        @if($data['prevButton']['enabled'])
            <a href="{{ $data['prevButton']['href'] }}" class="nav-button nav-button--prev" aria-label="{{ $data['ariaLabels']['previous'] }}">
                {{ $data['prevButton']['text'] }}
            </a>
        @else
            <button type="button" class="nav-button nav-button--prev" disabled aria-label="{{ $data['ariaLabels']['previous'] }}">
                {{ $data['prevButton']['text'] }}
            </button>
        @endif
    @endif
    
    @if($data['centerButton'])
        @if($data['centerButton']['href'] && $data['centerButton']['enabled'])
            <a href="{{ $data['centerButton']['href'] }}" class="nav-button nav-button--today" aria-label="{{ $data['ariaLabels']['center'] }}">
                {{ $data['centerButton']['text'] }}
            </a>
        @else
            <button type="button" class="nav-button nav-button--today" {{ !$data['centerButton']['enabled'] ? 'disabled' : '' }} aria-label="{{ $data['ariaLabels']['center'] }}">
                {{ $data['centerButton']['text'] }}
            </button>
        @endif
    @endif
    
    @if($data['nextButton'])
        @if($data['nextButton']['enabled'])
            <a href="{{ $data['nextButton']['href'] }}" class="nav-button nav-button--next" aria-label="{{ $data['ariaLabels']['next'] }}">
                {{ $data['nextButton']['text'] }}
            </a>
        @else
            <button type="button" class="nav-button nav-button--next" disabled aria-label="{{ $data['ariaLabels']['next'] }}">
                {{ $data['nextButton']['text'] }}
            </button>
        @endif
    @endif
</nav>
