@extends('app')

@section('content')
    <div class="container">
        <h1>Edit User</h1>
        <form action="{{ route('users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ $user->name }}" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ $user->email }}" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-field-container">
                    <input type="password" name="password" id="password" class="form-control">
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password:</label>
                <div class="password-field-container">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
                    <button type="button" class="password-toggle" data-target="password_confirmation" aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="roles">Roles:</label>
                <select name="roles[]" id="roles" class="form-control" multiple required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ $user->roles->contains($role) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button">Update User</button>
        </form>
    </div>
@endsection
