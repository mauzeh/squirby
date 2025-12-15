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

    <title>{{ config('app.name', 'Quantified Athletics') }} - Set New Password</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
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
            <h3 class="login-title">Set New Password</h3>

            <div class="forgot-password-description">
                <p>Enter your new password below. Make sure it's secure and something you'll remember.</p>
            </div>

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

            <form method="POST" action="{{ route('password.store') }}" class="login-form">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email Address -->
                <div class="login-form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" readonly />
                </div>

                <!-- Password -->
                <div class="login-form-group">
                    <label for="password">New Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password" />
                </div>

                <!-- Confirm Password -->
                <div class="login-form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                </div>

                <button type="submit" class="login-button">
                    Reset Password
                </button>
            </form>

            <div class="register-link-container">
                <p>Remember your password? <a href="{{ route('login') }}" class="register-link">Back to login</a></p>
            </div>
        </div>
    </div>
</body>
</html>