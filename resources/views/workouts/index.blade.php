@extends('app')

@section('content')
    <div class="container">
        <div class="form-container">
            <h3>Add Workout</h3>
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
                    <input type="datetime-local" name="logged_at" id="logged_at" class="form-control" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                </div>
                <button type="submit" class="button">Add Workout</button>
            </form>
        </div>

        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Exercise</th>
                    <th>Working Set</th>
                    <th>Comments</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workouts as $workout)
                    <tr>
                        <td>{{ $workout->logged_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $workout->exercise->title }}</td>
                        <td>{{ $workout->working_set_weight }} lbs x {{ $workout->working_set_reps }} reps x {{ $workout->working_set_rounds }} rounds</td>
                        <td>{{ $workout->comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('workouts.edit', $workout->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('workouts.destroy', $workout->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this workout?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection