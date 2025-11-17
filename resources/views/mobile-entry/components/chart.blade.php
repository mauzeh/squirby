{{-- Chart Component --}}
<div class="{{ $data['containerClass'] ?? 'form-container' }}">
    @if(isset($data['title']) && $data['title'])
        <h3>{{ $data['title'] }}</h3>
    @endif
    <canvas 
        id="{{ $data['canvasId'] }}" 
        @if(isset($data['height'])) 
            style="height: {{ $data['height'] }}px;"
        @endif
        data-chart-type="{{ $data['type'] }}"
        data-chart-datasets="{{ json_encode($data['datasets']) }}"
        data-chart-options="{{ json_encode($data['options']) }}"
        aria-label="{{ $data['ariaLabel'] ?? 'Chart' }}"
        role="img"
    ></canvas>
</div>
