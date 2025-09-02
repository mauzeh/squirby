@extends('app')

@section('content')
<div class="container">
    <h1>Roles</h1>
    <a href="{{ route('admin.users.index') }}" class="button">Manage Users</a>
    <a href="{{ route('admin.roles.create') }}" class="button">Create Role</a>
    
    <table class="log-entries-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>
                        <a href="{{ route('admin.roles.edit', $role) }}" class="button edit">Edit</a>
                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" style="display: inline-block;">
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
