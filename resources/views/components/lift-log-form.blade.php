<form action="{{ $action }}" method="POST">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    
    @if ($shouldShowField('exercise_id'))
        <div class="form-group">
            <label for="exercise_id">Exercise:</label>
            <select name="exercise_id" id="exercise_id" class="form-control" required>
                @foreach ($exercises as $exercise)
                    <option value="{{ $exercise->id }}" 
                            {{ old('exercise_id', $liftLog->exercise_id) == $exercise->id ? 'selected' : '' }} 
                            data-exercise-type="{{ $exercise->exercise_type }}">
                        {{ $exercise->title }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="form-group" id="weight-group">
        @if ($isCurrentExerciseBanded())
            <label for="band_color">Band Color:</label>
            <select name="band_color" id="band_color" class="form-control">
                @foreach($getBandColors() as $value => $label)
                    <option value="{{ $value }}" {{ old('band_color', $getCurrentBandColor()) == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        @else
            <label for="weight">Weight (lbs):</label>
            <input type="number" name="weight" id="weight" class="form-control" 
                   value="{{ old('weight', $liftLog->exists ? $liftLog->display_weight : '') }}" required inputmode="decimal">
        @endif
    </div>

    @if ($shouldShowField('reps'))
        <div class="form-group">
            <label for="reps">Reps:</label>
            <input type="number" name="reps" id="reps" class="form-control" 
                   value="{{ old('reps', $liftLog->exists ? $liftLog->display_reps : '') }}" required inputmode="numeric">
        </div>
    @endif

    @if ($shouldShowField('rounds'))
        <div class="form-group">
            <label for="rounds">Rounds:</label>
            <input type="number" name="rounds" id="rounds" class="form-control" 
                   value="{{ old('rounds', $liftLog->exists ? $liftLog->display_rounds : '') }}" required inputmode="numeric">
        </div>
    @endif

    @if ($shouldShowField('comments'))
        <div class="form-group">
            <label for="comments">Comments:</label>
            <textarea name="comments" id="comments" class="form-control" rows="5">{{ old('comments', $liftLog->comments) }}</textarea>
        </div>
    @endif

    @if ($shouldShowField('date'))
        <div class="form-group">
            <label for="date">Date:</label>
            <x-date-select name="date" id="date" 
                          :selectedDate="old('date', $liftLog->exists ? $liftLog->logged_at->format('Y-m-d') : now()->format('Y-m-d'))" 
                          required />
        </div>
    @endif

    @if ($shouldShowField('logged_at'))
        <div class="form-group">
            <label for="logged_at">Time:</label>
            <x-time-select name="logged_at" id="logged_at" 
                          :selectedTime="old('logged_at', $liftLog->exists ? $liftLog->logged_at->ceilMinute(15)->format('H:i') : now()->ceilMinute(15)->format('H:i'))" 
                          required />
        </div>
    @endif

    <button type="submit" class="button">
        {{ $liftLog->exists ? 'Update Lift Log' : 'Add Lift Log' }}
    </button>
</form>