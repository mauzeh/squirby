{{-- Summary Component --}}
<section class="summary" aria-label="{{ $data['ariaLabel'] }}">
    @foreach($data['items'] as $item)
    <div class="summary-item summary-item--{{ $item['key'] }}">
        <span class="summary-value">
            @if(is_numeric($item['value']) && $item['value'] == (int)$item['value'])
                {{ number_format($item['value']) }}
            @elseif(is_numeric($item['value']))
                {{ number_format($item['value'], 1) }}
            @else
                {{ $item['value'] }}
            @endif
        </span>
        <span class="summary-label">{{ $item['label'] }}</span>
    </div>
    @endforeach
</section>
