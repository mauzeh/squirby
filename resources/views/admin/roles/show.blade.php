@extends('app')

@section('content')
<div class="container">
    <h1>Role Details</h1>
    <table class="table">
        <tbody>
            <tr>
                <th>Name</th>
                <td>{{ $role->name }}</td>
            </tr>
            <tr>
                <th>Permissions</th>
                <td>{{ $role->permissions->pluck('name')->implode(', ') }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
