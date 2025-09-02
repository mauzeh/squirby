@extends('app')

@section('content')
<div class="form-container">
    <h1>Create Permission</h1>
    <form action="{{ route('admin.permissions.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control">
        </div>
        <button type="submit" class="button">Create</button>
    </form>
</div>
@endsection
