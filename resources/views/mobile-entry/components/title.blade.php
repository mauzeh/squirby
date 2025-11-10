{{-- Title Component --}}
<div class="component-title-container">
    <h1 class="component-title">{{ $data['main'] }}</h1>
    @if($data['subtitle'])
        <div class="component-subtitle">{{ $data['subtitle'] }}</div>
    @endif
</div>
