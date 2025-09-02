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
                <label for="weight">Weight (lbs):</label>
                <input type="number" name="weight" id="weight" class="form-control" value="{{ $workout->display_weight }}" required inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="reps">Reps:</label>
                <input type="number" name="reps" id="reps" class="form-control" value="{{ $workout->display_reps }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="rounds">Rounds:</label>
                <input type="number" name="rounds" id="rounds" class="form-control" value="{{ $workout->display_rounds }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="comments">Comments:</label>
                <textarea name="comments" id="comments" class="form-control" rows="5">{{ $workout->comments }}</textarea>
            </div>
            <div class="form-group">
                <label for="date">Date:</label>
                <x-date-select name="date" id="date" :selectedDate="$workout->logged_at->format('Y-m-d')" required />
            </div>
            <div class="form-group">
                <label for="logged_at">Time:</label>
                <x-time-select name="logged_at" id="logged_at" :selectedTime="$workout->logged_at->ceilMinute(15)->format('H:i')" required />
            </div>
            <button type="submit" class="button">Update Workout</button>
        </form>
    </div>
@endsection
