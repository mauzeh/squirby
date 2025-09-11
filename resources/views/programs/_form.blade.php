<div class="form-group">
    <label for="exercise_id">Exercise</label>
    <select name="exercise_id" id="exercise_id" class="form-control" required>
        @foreach ($exercises as $exercise)
            <option value="{{ $exercise->id }}" {{ isset($program) && $program->exercise_id == $exercise->id ? 'selected' : '' }}>{{ $exercise->title }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="date">Date</label>
    <input type="date" name="date" id="date" class="form-control" value="{{ isset($program) ? $program->date->format('Y-m-d') : today()->format('Y-m-d') }}" required>
</div>

<div class="form-group">
    <label for="sets">Sets</label>
    <input type="number" name="sets" id="sets" class="form-control" value="{{ isset($program) ? $program->sets : 3 }}" required>
</div>

<div class="form-group">
    <label for="reps">Reps</label>
    <input type="number" name="reps" id="reps" class="form-control" value="{{ isset($program) ? $program->reps : 5 }}" required>
</div>

<div class="form-group">
    <label for="comments">Comments</label>
    <textarea name="comments" id="comments" class="form-control">{{ isset($program) ? $program->comments : '' }}</textarea>
</div>
