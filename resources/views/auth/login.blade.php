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

    <title>{{ config('app.name', 'Quantified Athletics') }} - Login</title>

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
            <h3 class="login-title">Log In</h3>

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

            <!-- Error Messages -->
            @if (session('error'))
                <div class="error-message-box">
                    {{ session('error') }}
                </div>
            @endif

            <div class="google-signin-container">
                <a href="{{ route('auth.google') }}" class="google-signin-btn">
                    <svg class="google-icon" viewBox="0 0 24 24" width="18" height="18">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span>Sign in with Google</span>
                </a>
            </div>

            <div class="divider">
                <span>or</span>
            </div>

            <form method="POST" action="{{ route('login') }}" class="login-form">
                @csrf

                <!-- Email Address -->
                <div class="login-form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
                </div>

                <!-- Password -->
                <div class="login-form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" />
                </div>

                <!-- Remember Me and Forgot Password -->
                <div class="login-checkbox-group">
                    <label for="remember_me" class="checkbox-label">
                        <input id="remember_me" type="checkbox" name="remember" checked>
                        <span>Remember me</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="forgot-password-link">Forgot password?</a>
                </div>

                <button type="submit" class="login-button">
                    Log in
                </button>
            </form>

            <div class="register-link-container">
                <p>Don't have an account? <a href="{{ route('register') }}" class="register-link">Sign up here</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const forgotPasswordLink = document.querySelector('.forgot-password-link');
            const baseUrl = forgotPasswordLink.href;

            function updateForgotPasswordLink() {
                const email = emailInput.value.trim();
                if (email) {
                    const url = new URL(baseUrl);
                    url.searchParams.set('email', email);
                    forgotPasswordLink.href = url.toString();
                } else {
                    forgotPasswordLink.href = baseUrl;
                }
            }

            // Update link when user types in email field
            emailInput.addEventListener('input', updateForgotPasswordLink);
            
            // Update link when user pastes into email field
            emailInput.addEventListener('paste', function() {
                setTimeout(updateForgotPasswordLink, 10);
            });

            // Initial update in case there's a pre-filled value
            updateForgotPasswordLink();
        });
    </script>
</body>
</html>