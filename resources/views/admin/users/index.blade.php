@extends('app')

@section('content')
    @if (session('success'))
        <div class="container success-message-box">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="container error-message-box">
            {{ session('error') }}
        </div>
    @endif
<div class="container">
    <h1>Users</h1>
    <a href="{{ route('admin.users.create') }}" class="button">Create User</a>
    <a href="{{ route('admin.roles.index') }}" class="button">Manage Roles</a>
    <a href="{{ route('admin.permissions.index') }}" class="button">Manage Permissions</a>
    <table class="log-entries-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->getRoleNames()->implode(', ') }}</td>
                    <td>
                        <a href="{{ route('admin.users.edit', $user) }}" class="button edit">Edit</a>
                        @canBeImpersonated($user)
                            <a href="{{ route('impersonate', $user) }}" class="button">Impersonate</a>
                        @endCanBeImpersonated
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display: inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button delete">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection