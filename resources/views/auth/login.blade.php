@extends('app')

@section('content')
    <div class="container">
        <div class="form-container" style="max-width: 400px; margin: 50px auto;">
            <h3 style="text-align: center; margin-bottom: 20px;">Log In</h3>

            <!-- Session Status -->
            @if (session('status'))
                <div class="success-message-box mb-4">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
                    @error('email')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" class="form-control" type="password" name="password" required autocomplete="current-password" />
                    @error('password')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="form-group" style="justify-content: flex-start;">
                    <label>&nbsp;</label>
                    <label for="remember_me" style="flex: none; text-align: left;">
                        <input id="remember_me" type="checkbox" name="remember" checked>
                        <span class="ms-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <div class="form-group" style="justify-content: flex-start;">
                    <label>&nbsp;</label>
                    <button type="submit" class="button ms-3">
                        Log in
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection