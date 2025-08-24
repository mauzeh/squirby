@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Workout</h1>
        <form action="{{ route('workouts.update', $workout->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="exercise_id">Exercise:</label>
                <select name="exercise_id" id="exercise_id" class="form-control" required>
                    @foreach ($exercises as $exercise)
                        <option value="{{ $exercise->id }}" {{ $workout->exercise_id == $exercise->id ? 'selected' : '' }}>{{ $exercise->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="working_set_weight">Working Set Weight (lbs):</label>
                <input type="number" name="working_set_weight" id="working_set_weight" class="form-control" value="{{ $workout->working_set_weight }}" required inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="working_set_reps">Working Set Reps:</label>
                <input type="number" name="working_set_reps" id="working_set_reps" class="form-control" value="{{ $workout->working_set_reps }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="working_set_rounds">Working Set Rounds:</label>
                <input type="number" name="working_set_rounds" id="working_set_rounds" class="form-control" value="{{ $workout->working_set_rounds }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="warmup_sets_comments">Warmup Sets:</label>
                <textarea name="warmup_sets_comments" id="warmup_sets_comments" class="form-control" rows="5">{{ $workout->warmup_sets_comments }}</textarea>
            </div>
            <div class="form-group">
                <label for="logged_at">Date:</label>
                <input type="datetime-local" name="logged_at" id="logged_at" class="form-control" value="{{ $workout->logged_at->format('Y-m-d\TH:i') }}" required>
            </div>
            <button type="submit" class="button">Update Workout</button>
        </form>
    </div>
@endsection
