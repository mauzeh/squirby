<div class="form-group">
    <label for="exercise_id">Exercise</label>
    <select name="exercise_id" id="exercise_id" class="form-control">
        <option value="">Select an exercise</option>
        @foreach ($exercises as $exercise)
            <option value="{{ $exercise->id }}" {{ isset($program) && $program->exercise_id == $exercise->id ? 'selected' : '' }}>{{ $exercise->title }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="new_exercise_name">Or Add New Exercise</label>
    <input type="text" name="new_exercise_name" id="new_exercise_name" class="form-control">
</div>

<div class="form-group">
    <label for="date">Date</label>
    <input type="date" name="date" id="date" class="form-control" value="{{ isset($program) ? $program->date->format('Y-m-d') : $date->format('Y-m-d') }}" required>
</div>

<div class="form-group">
    <label for="band_type">Band Type:</label>
    <select name="band_type" id="band_type" class="form-control">
        <option value="">None</option>
        <option value="resistance" {{ isset($program) && $program->exercise->band_type == 'resistance' ? 'selected' : '' }}>Resistance</option>
        <option value="assistance" {{ isset($program) && $program->exercise->band_type == 'assistance' ? 'selected' : '' }}>Assistance</option>
    </select>
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
    <label for="priority">Priority</label>
    <input type="number" name="priority" id="priority" class="form-control" value="{{ isset($program) ? $program->priority : $defaultPriority ?? 0 }}" step="1">
</div>

<div class="form-group">
    <label for="comments">Comments</label>
    <textarea name="comments" id="comments" class="form-control">{{ isset($program) ? $program->comments : '' }}</textarea>
</div>
