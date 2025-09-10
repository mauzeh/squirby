@props(['exercises', 'selectedExercise' => null])

<div class="form-container">
    <h3>Add Lift Log</h3>
    <form action="{{ route('lift-logs.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="exercise_id">Exercise:</label>
            @if ($selectedExercise)
                <strong>{{ $selectedExercise->title }}</strong>
                <input type="hidden" name="exercise_id" value="{{ $selectedExercise->id }}">
            @else
                <select name="exercise_id" id="exercise_id" class="form-control" required>
                    @foreach ($exercises as $exercise)
                        <option value="{{ $exercise->id }}" data-is-bodyweight="{{ $exercise->is_bodyweight ? 'true' : 'false' }}">{{ $exercise->title }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div class="form-group" id="weight-group">
            <label for="weight">Weight (lbs):</label>
            <input type="number" name="weight" id="weight" class="form-control" required inputmode="decimal">
        </div>
        <div class="form-group">
            <label for="reps">Reps:</label>
            <input type="number" name="reps" id="reps" class="form-control" value="5" required inputmode="numeric">
        </div>
        <div class="form-group">
            <label for="rounds">Rounds:</label>
            <input type="number" name="rounds" id="rounds" class="form-control" value="3" required inputmode="numeric">
        </div>
        <div class="form-group">
            <label for="comments">Comments:</label>
            <textarea name="comments" id="comments" class="form-control" rows="5"></textarea>
        </div>
        <div class="form-group">
            <label for="date">Date:</label>
            <x-date-select name="date" id="date" :selectedDate="now()->format('Y-m-d')" required />
        </div>
        <div class="form-group">
            <label for="logged_at">Time:</label>
            <x-time-select name="logged_at" id="logged_at" required />
        </div>
        <button type="submit" class="button create">Add Lift Log</button>
    </form>
</div>
