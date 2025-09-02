@extends('app')

@section('content')
<div class="container">
    <h1>User Details</h1>
    <table class="table">
        <tbody>
            <tr>
                <th>Name</th>
                <td>{{ $user->name }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <th>Roles</th>
                <td>{{ $user->getRoleNames()->implode(', ') }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
