@props(['exercises', 'selectedExercise' => null, 'sets' => null, 'reps' => null, 'weight' => null])

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
            @if ($selectedExercise && $selectedExercise->band_type)
                <label for="band_color">Band Color:</label>
                <select name="band_color" id="band_color" class="form-control">
                    <option value="">Select Band</option>
                    @foreach(config('bands.colors') as $color => $data)
                        <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                    @endforeach
                </select>
            @else
                <label for="weight">Weight (lbs):</label>
                <input type="number" name="weight" id="weight" class="form-control" value="{{ $weight ?? null }}" required inputmode="decimal">
            @endif
        </div>
        <div class="form-group">
            <label for="reps">Reps:</label>
            <input type="number" name="reps" id="reps" class="form-control" value="{{ $reps ?? 5 }}" required inputmode="numeric">
        </div>
        <div class="form-group">
            <label for="rounds">Rounds:</label>
            <input type="number" name="rounds" id="rounds" class="form-control" value="{{ $sets ?? 3 }}" required inputmode="numeric">
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
