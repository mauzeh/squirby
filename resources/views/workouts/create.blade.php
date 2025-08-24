@extends('app')

@section('content')
    <div class="container">
        <h1>Add Workout</h1>
        <form action="{{ route('workouts.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="exercise_id">Exercise:</label>
                <select name="exercise_id" id="exercise_id" class="form-control" required>
                    @foreach ($exercises as $exercise)
                        <option value="{{ $exercise->id }}">{{ $exercise->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="working_set_weight">Working Set Weight (lbs):</label>
                <input type="number" name="working_set_weight" id="working_set_weight" class="form-control" required inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="working_set_reps">Working Set Reps:</label>
                <input type="number" name="working_set_reps" id="working_set_reps" class="form-control" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="working_set_rounds">Working Set Rounds:</label>
                <input type="number" name="working_set_rounds" id="working_set_rounds" class="form-control" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="comments">Comments:</label>
                <textarea name="comments" id="comments" class="form-control" rows="5"></textarea>
            </div>
            <div class="form-group">
                <label for="logged_at">Date:</label>
                <input type="datetime-local" name="logged_at" id="logged_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
            </div>
            <button type="submit" class="button">Add Workout</button>
        </form>
    </div>
@endsection
