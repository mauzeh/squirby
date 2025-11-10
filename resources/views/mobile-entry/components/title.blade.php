{{-- Title Component --}}
<div class="date-title-container">
    <h1 class="date-title">{{ $data['main'] }}</h1>
    @if($data['subtitle'])
        <div class="date-subtitle">{{ $data['subtitle'] }}</div>
    @endif
</div>
