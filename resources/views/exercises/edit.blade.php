@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Exercise</h1>
        <form action="{{ route('exercises.update', $exercise->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" id="title" class="form-control" value="{{ $exercise->title }}" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" class="form-control" rows="5">{{ $exercise->description }}</textarea>
            </div>
            <button type="submit" class="button">Update Exercise</button>
        </form>
    </div>
@endsection
