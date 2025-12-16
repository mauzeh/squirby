@extends('layouts.auth')

@section('title', 'Set New Password')

@section('description')
    <div class="forgot-password-description">
        <p>Enter your new password below. Make sure it's secure and something you'll remember.</p>
    </div>
@endsection

@section('form')
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
@endsection

@section('bottom-links')
    <div class="register-link-container">
        <p>Remember your password? <a href="{{ route('login') }}" class="register-link">Back to login</a></p>
    </div>
@endsection