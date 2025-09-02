@extends('app')

@section('content')
<div class="form-container">
    <h1>Edit Permission</h1>
    <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $permission->name }}">
        </div>
        <button type="submit" class="button">Update</button>
    </form>
</div>
@endsection
