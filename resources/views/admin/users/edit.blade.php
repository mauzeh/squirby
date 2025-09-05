@extends('app')

@section('content')
    <div class="container">
        <h1>Edit User Roles</h1>
        <form action="{{ route('users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Name:</label>
                <p>{{ $user->name }}</p>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <p>{{ $user->email }}</p>
            </div>
            <div class="form-group">
                <label for="roles">Roles:</label>
                <select name="roles[]" id="roles" class="form-control" multiple>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ $user->roles->contains($role) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button">Update Roles</button>
        </form>
    </div>
@endsection
