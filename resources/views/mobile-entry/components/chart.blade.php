{{-- Chart Component --}}
<div class="{{ $data['containerClass'] ?? 'form-container' }}">
    @if(isset($data['title']) && $data['title'])
        <div class="cell-title">{{ $data['title'] }}</div>
    @endif
    
    @if(isset($data['showTimeframeSelector']) && $data['showTimeframeSelector'])
        <div class="chart-timeframe-selector" style="margin-bottom: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
            <button type="button" class="btn btn-sm btn-outline-secondary timeframe-btn active" data-timeframe="all">All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary timeframe-btn" data-timeframe="1yr">1 Year</button>
            <button type="button" class="btn btn-sm btn-outline-secondary timeframe-btn" data-timeframe="6mo">6 Months</button>
            <button type="button" class="btn btn-sm btn-outline-secondary timeframe-btn" data-timeframe="3mo">3 Months</button>
        </div>
    @endif
    
    <div class="chart-canvas-wrapper" @if(isset($data['height'])) style="height: {{ $data['height'] }}px; position: relative;" @endif>
        <canvas 
            id="{{ $data['canvasId'] }}" 
            data-chart-type="{{ $data['type'] }}"
            data-chart-datasets="{{ json_encode($data['datasets']) }}"
            data-chart-options="{{ json_encode($data['options']) }}"
            @if(isset($data['showTimeframeSelector']) && $data['showTimeframeSelector'])
            data-chart-timeframe-enabled="true"
            @endif
            aria-label="{{ $data['ariaLabel'] ?? 'Chart' }}"
            role="img"
        ></canvas>
    </div>
</div>
