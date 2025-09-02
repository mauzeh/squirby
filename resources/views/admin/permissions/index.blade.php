@extends('app')

@section('content')
<div class="container">
    <h1>Permissions</h1>
    <a href="{{ route('admin.users.index') }}" class="button">Manage Users</a>
    <a href="{{ route('admin.roles.index') }}" class="button">Manage Roles</a>
    <a href="{{ route('admin.permissions.create') }}" class="button">Create Permission</a>
    <table class="log-entries-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($permissions as $permission)
                <tr>
                    <td>{{ $permission->name }}</td>
                    <td>
                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="button edit">Edit</a>
                        <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" style="display: inline-block;">
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
