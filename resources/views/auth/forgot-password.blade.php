@extends('layouts.auth')

@section('title', 'Reset Password')

@section('description')
    <div class="forgot-password-description">
        <p>Forgot your password? No problem. Just enter your email address and we'll send you a password reset link.</p>
    </div>
@endsection

@section('form')
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
@endsection

@section('bottom-links')
    <div class="register-link-container">
        <p>Remember your password? <a href="{{ route('login') }}" class="register-link">Back to login</a></p>
    </div>
@endsection

@section('scripts')
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
@endsection