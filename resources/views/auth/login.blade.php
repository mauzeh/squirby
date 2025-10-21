@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endsection

@section('content')
    <div class="login-container">
        <div class="login-form-wrapper">
            <h3 class="login-title">Log In</h3>

            @if ($errors->any())
                <div class="error-message-box">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Session Status -->
            @if (session('status'))
                <div class="success-message-box">
                    {{ session('status') }}
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

                <!-- Remember Me -->
                <div class="login-checkbox-group">
                    <label for="remember_me" class="checkbox-label">
                        <input id="remember_me" type="checkbox" name="remember" checked>
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="login-button">
                    Log in
                </button>
            </form>
        </div>
    </div>
@endsection