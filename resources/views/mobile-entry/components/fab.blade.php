<a href="{{ $data['url'] }}" class="fab" title="{{ $data['title'] ?? '' }}">
    <i class="fas {{ $data['icon'] }}"></i>
    @if($data['tooltip'])
    <span class="fab-tooltip">{{ $data['tooltip'] }}</span>
    @endif
</a>
