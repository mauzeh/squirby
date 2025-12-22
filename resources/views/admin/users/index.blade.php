@extends('app')

@section('content')
    <div class="container">
        <h1>User Administration</h1>
        <a href="{{ route('users.create') }}" class="button">Add User</a>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('users.edit', $user->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                <a href="{{ route('users.impersonate', $user->id) }}" class="button">Impersonate</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
