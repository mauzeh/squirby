@extends('app')

@section('content')
    <div class="container">
        <h1>Add User</h1>
        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-field-container">
                    <input type="password" name="password" id="password" class="form-control" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password:</label>
                <div class="password-field-container">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                    <button type="button" class="password-toggle" data-target="password_confirmation" aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="roles">Roles:</label>
                <select name="roles[]" id="roles" class="form-control" multiple required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button">Add User</button>
        </form>
    </div>
@endsection
