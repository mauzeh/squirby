@extends('app')

@section('content')
    <div class="container">
        <h1>Add Exercise</h1>
        <form action="{{ route('exercises.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" class="form-control" rows="5"></textarea>
            </div>
            <button type="submit" class="button">Add Exercise</button>
        </form>
    </div>
@endsection
