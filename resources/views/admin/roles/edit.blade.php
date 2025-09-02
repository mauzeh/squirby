@extends('app')

@section('content')
<div class="form-container">
    <h1>Edit Role</h1>
    <form action="{{ route('admin.roles.update', $role) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $role->name }}">
        </div>
        <div class="form-group">
            <label for="permissions">Permissions</label>
            <div>
                @foreach ($permissions as $permission)
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="permission_{{ $permission->id }}" {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>
                    <label for="permission_{{ $permission->id }}">{{ $permission->name }}</label><br>
                @endforeach
            </div>
        </div>
        <button type="submit" class="button">Update</button>
    </form>
</div>
@endsection
