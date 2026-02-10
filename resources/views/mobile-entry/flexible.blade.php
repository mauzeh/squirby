@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/navigation.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/title.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/messages.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/summary.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/button.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/table.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/form.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/list.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/badges.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/collapsible.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-cards.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/calculator-grid.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/chart.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/markdown.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/code-editor.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/quick-actions.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/tabs.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/welcome-overlay.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-info.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-records-table.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-feed-list.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/notifications.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/connection.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/components/fab.css') }}?v={{ config('app.version', '1.0') }}">
@endsection

@section('scripts')
    <script>
        window.mobileEntryConfig = {
            autoscroll: {{ isset($data['autoscroll']) && $data['autoscroll'] ? 'true' : 'false' }},
            hasPRs: {{ isset($data['has_prs']) && $data['has_prs'] ? 'true' : 'false' }}
        };
        
        // Exercise names for autocomplete (if available)
        @if(isset($data['exerciseNames']))
            window.exerciseNames = @json($data['exerciseNames']);
        @endif
        
        // Check for PR flag from session and store in sessionStorage
        @if(session('is_pr'))
            sessionStorage.setItem('is_pr', 'true');
        @endif
    </script>
    <script src="{{ asset('js/pr-confetti.js') }}"></script>
    <script src="{{ asset('js/mobile-entry.js') }}"></script>
    @php
        // Automatically collect required scripts from components
        $requiredScripts = [];
        if (isset($data['components'])) {
            foreach ($data['components'] as $component) {
                if (isset($component['requiresScript'])) {
                    // Support both string and array format
                    $scripts = is_array($component['requiresScript']) 
                        ? $component['requiresScript'] 
                        : [$component['requiresScript']];
                    
                    foreach ($scripts as $script) {
                        $requiredScripts[$script] = true;
                    }
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
