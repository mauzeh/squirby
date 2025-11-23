{{-- Chart Component --}}
<div class="{{ $data['containerClass'] ?? 'form-container' }}">
    @if(isset($data['title']) && $data['title'])
        <div class="cell-title">{{ $data['title'] }}</div>
    @endif
    <div class="chart-canvas-wrapper" @if(isset($data['height'])) style="height: {{ $data['height'] }}px; position: relative;" @endif>
        <canvas 
            id="{{ $data['canvasId'] }}" 
            data-chart-type="{{ $data['type'] }}"
            data-chart-datasets="{{ json_encode($data['datasets']) }}"
            data-chart-options="{{ json_encode($data['options']) }}"
            aria-label="{{ $data['ariaLabel'] ?? 'Chart' }}"
            role="img"
        ></canvas>
    </div>
</div>
