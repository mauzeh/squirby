@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
    @php
        // Automatically collect required styles from components
        $requiredStyles = [];
        if (isset($data['components'])) {
            foreach ($data['components'] as $component) {
                if (isset($component['requiresStyle'])) {
                    if (is_array($component['requiresStyle'])) {
                        foreach ($component['requiresStyle'] as $style) {
                            $requiredStyles[$style] = true;
                        }
                    } else {
                        $requiredStyles[$component['requiresStyle']] = true;
                    }
                }
            }
        }
    @endphp
    @foreach(array_keys($requiredStyles) as $styleName)
        <link rel="stylesheet" href="{{ asset('css/mobile-entry/' . $styleName . '.css') }}">
    @endforeach
@endsection

@section('scripts')
    <script>
        window.mobileEntryConfig = {
            autoscroll: {{ isset($data['autoscroll']) && $data['autoscroll'] ? 'true' : 'false' }}
        };
    </script>
    <script src="{{ asset('js/mobile-entry.js') }}"></script>
    @php
        // Automatically collect required scripts from components
        $requiredScripts = [];
        if (isset($data['components'])) {
            foreach ($data['components'] as $component) {
                if (isset($component['requiresScript'])) {
                    $requiredScripts[$component['requiresScript']] = true;
                }
            }
        }
    @endphp
    @foreach(array_keys($requiredScripts) as $scriptName)
        <script src="{{ asset('js/' . $scriptName . '.js') }}"></script>
    @endforeach
    @if(isset($data['customScripts']))
        @foreach($data['customScripts'] as $scriptName)
            <script src="{{ asset('js/' . $scriptName . '.js') }}"></script>
        @endforeach
    @endif
@endsection

@section('content')
    <div class="mobile-entry-container">
        @foreach($data['components'] as $component)
            @include("mobile-entry.components.{$component['type']}", ['data' => $component['data']])
        @endforeach
    </div>
@endsection
