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

    <title>{{ config('app.name', 'Quantified Athletics') }} - Reset Password</title>

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
            <h3 class="login-title">Reset Password</h3>

            <div class="forgot-password-description">
                <p>Forgot your password? No problem. Just enter your email address and we'll send you a password reset link.</p>
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

            <!-- Session Status -->
            @if (session('status'))
                <div class="success-message-box">
                    {!! session('status') !!}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="login-form">
                @csrf

                <!-- Email Address -->
                <div class="login-form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus />
                </div>

                <button type="submit" class="login-button">
                    Send Reset Link
                </button>
            </form>

            <div class="register-link-container">
                <p>Remember your password? <a href="{{ route('login') }}" class="register-link">Back to login</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            
            // Check if there's an email in the URL query string
            const urlParams = new URLSearchParams(window.location.search);
            const emailFromUrl = urlParams.get('email');
            
            if (emailFromUrl && !emailInput.value) {
                emailInput.value = emailFromUrl;
                // Focus on the email field and move cursor to end
                emailInput.focus();
                emailInput.setSelectionRange(emailInput.value.length, emailInput.value.length);
            }
        });
    </script>
</body>
</html>