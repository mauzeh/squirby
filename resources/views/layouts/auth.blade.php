<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">

    <title>{{ config('app.name', 'Quantified Athletics') }} - @yield('title')</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.version', '1.0') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ config('app.version', '1.0') }}">
    @yield('styles')
</head>
<body>
    @if(app()->environment('staging'))
        <div class="env-bar staging">
            STAGING ENVIRONMENT
        </div>
    @elseif(app()->environment('local'))
        <div class="env-bar local">
            LOCAL DEV ENVIRONMENT
        </div>
    @endif

    <div class="login-container">
        <div class="login-form-wrapper">
            <h3 class="login-title">@yield('title')</h3>

            @yield('description')

            @if ($errors->any())
                <div class="error-message-box">
                    @if ($errors->count() == 1)
                        {{ $errors->first() }}
                    @else
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if (session('status'))
                <div class="success-message-box">
                    {!! session('status') !!}
                </div>
            @endif

            @if (session('error'))
                <div class="error-message-box">
                    {{ session('error') }}
                </div>
            @endif

            @yield('google-auth')

            @hasSection('google-auth')
                <div class="divider">
                    <span>or</span>
                </div>
            @endif

            @yield('form')

            @yield('bottom-links')
        </div>
    </div>

    @yield('scripts')
</body>
</html>