@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Lift Log</h1>
        <form action="{{ route('lift-logs.update', $liftLog->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="exercise_id">Exercise:</label>
                <select name="exercise_id" id="exercise_id" class="form-control" required>
                    @foreach ($exercises as $exercise)
                        <option value="{{ $exercise->id }}" {{ $liftLog->exercise_id == $exercise->id ? 'selected' : '' }} data-is-bodyweight="{{ $exercise->is_bodyweight ? 'true' : 'false' }}">{{ $exercise->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" id="weight-group">
                @if ($liftLog->exercise->band_type)
                    <label for="band_color">Band Color:</label>
                    <select name="band_color" id="band_color" class="form-control">
                        <option value="">Select Band</option>
                        @foreach(config('bands.colors') as $color => $data)
                            <option value="{{ $color }}" {{ $liftLog->liftSets->first()->band_color == $color ? 'selected' : '' }}>{{ ucfirst($color) }}</option>
                        @endforeach
                    </select>
                @else
                    <label for="weight">Weight (lbs):</label>
                    <input type="number" name="weight" id="weight" class="form-control" value="{{ $liftLog->display_weight }}" required inputmode="decimal">
                @endif
            </div>
            <div class="form-group">
                <label for="reps">Reps:</label>
                <input type="number" name="reps" id="reps" class="form-control" value="{{ $liftLog->display_reps }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="rounds">Rounds:</label>
                <input type="number" name="rounds" id="rounds" class="form-control" value="{{ $liftLog->display_rounds }}" required inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="comments">Comments:</label>
                <textarea name="comments" id="comments" class="form-control" rows="5">{{ $liftLog->comments }}</textarea>
            </div>
            <div class="form-group">
                <label for="date">Date:</label>
                <x-date-select name="date" id="date" :selectedDate="$liftLog->logged_at->format('Y-m-d')" required />
            </div>
            <div class="form-group">
                <label for="logged_at">Time:</label>
                <x-time-select name="logged_at" id="logged_at" :selectedTime="$liftLog->logged_at->ceilMinute(15)->format('H:i')" required />
            </div>
            <button type="submit" class="button">Update Lift Log</button>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exerciseSelect = document.getElementById('exercise_id');
            const weightGroup = document.getElementById('weight-group');
            const weightInput = document.getElementById('weight');

            function toggleWeightInput() {
                const selectedOption = exerciseSelect.options[exerciseSelect.selectedIndex];
                const isBodyweight = selectedOption.dataset.isBodyweight === 'true';

                if (isBodyweight) {
                    weightGroup.style.display = 'none';
                    weightInput.removeAttribute('required');
                    weightInput.value = 0; // Set weight to 0 for bodyweight exercises
                } else {
                    weightGroup.style.display = 'flex';
                    weightInput.setAttribute('required', 'required');
                }
            }

            // Initial call to set state based on default selected option
            toggleWeightInput();

            // Listen for changes on the exercise select dropdown
            exerciseSelect.addEventListener('change', toggleWeightInput);
        });
    </script>
@endsection
