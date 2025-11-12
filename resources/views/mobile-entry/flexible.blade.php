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
        @foreach($data['components'] as $component)
            @include("mobile-entry.components.{$component['type']}", ['data' => $component['data']])
        @endforeach
    </div>
@endsection
