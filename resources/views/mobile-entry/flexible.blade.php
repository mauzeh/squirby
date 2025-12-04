@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/navigation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/title.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/messages.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/summary.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/button.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/table.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/form.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/list.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/badges.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/collapsible.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-cards.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/calculator-grid.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/chart.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/markdown.css') }}">
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
