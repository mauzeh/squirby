{{-- Title Component --}}
<div class="component-title-container">
    <div class="component-title-row">
        @if(isset($data['backButton']))
            <a href="{{ $data['backButton']['url'] }}" class="component-title-back-button" aria-label="{{ $data['backButton']['ariaLabel'] }}">
                <i class="fas {{ $data['backButton']['icon'] }}"></i>
            </a>
        @endif
        <div class="component-title-content">
            <h1 class="component-title">{{ $data['main'] }}</h1>
            @if($data['subtitle'])
                <div class="component-subtitle">{{ $data['subtitle'] }}</div>
            @endif
        </div>
    </div>
</div>
