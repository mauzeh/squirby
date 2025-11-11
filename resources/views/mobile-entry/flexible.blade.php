@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
@endsection

@section('scripts')
    <script>
        window.mobileEntryConfig = {
            autoscroll: {{ isset($data['autoscroll']) && $data['autoscroll'] ? 'true' : 'false' }}
        };
    </script>
    <script src="{{ asset('js/mobile-entry.js') }}"></script>
@endsection

@section('content')
    <div class="mobile-entry-container">
        @if(app()->environment('local') || (auth()->check() && auth()->user()->hasRole('Admin')))
            <div style="position: fixed; bottom: 10px; right: 10px; background: {{ isset($data['autoscroll']) && $data['autoscroll'] ? '#4CAF50' : '#999' }}; color: white; padding: 5px 10px; border-radius: 4px; font-size: 11px; z-index: 9999; opacity: 0.8;">
                Autoscroll: {{ isset($data['autoscroll']) && $data['autoscroll'] ? 'ON' : 'OFF' }}
            </div>
        @endif
        @foreach($data['components'] as $component)
            @include("mobile-entry.components.{$component['type']}", ['data' => $component['data']])
        @endforeach
    </div>
@endsection
