@extends('app')

@section('content')
    <div class="container">
        <div class="form-container" style="max-width: 400px; margin: 50px auto;">
            <h3 style="text-align: center; margin-bottom: 20px;">Confirm Password</h3>

            <div class="mb-4 text-sm text-gray-600">
                This is a secure area of the application. Please confirm your password before continuing.
            </div>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" class="form-control" type="password" name="password" required autocomplete="current-password" />
                    @error('password')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="button">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection