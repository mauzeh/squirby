@extends('app')

@section('content')
<div class="form-container">
    <h1>Create Role</h1>
    <form action="{{ route('admin.roles.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control">
        </div>
        <div class="form-group">
            <label for="permissions">Permissions</label>
            <div>
                @foreach ($permissions as $permission)
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="permission_{{ $permission->id }}">
                    <label for="permission_{{ $permission->id }}">{{ $permission->name }}</label><br>
                @endforeach
            </div>
        </div>
        <button type="submit" class="button">Create</button>
    </form>
</div>
@endsection
