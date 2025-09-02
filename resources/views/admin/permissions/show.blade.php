@extends('app')

@section('content')
<div class="container">
    <h1>Permission Details</h1>
    <table class="table">
        <tbody>
            <tr>
                <th>Name</th>
                <td>{{ $permission->name }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
