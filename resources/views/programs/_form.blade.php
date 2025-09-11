<div class="form-group">
    <label for="exercise_id">Exercise</label>
    <select name="exercise_id" id="exercise_id" class="form-control">
        @foreach ($exercises as $exercise)
            <option value="{{ $exercise->id }}" {{ isset($program) && $program->exercise_id == $exercise->id ? 'selected' : '' }}>{{ $exercise->name }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="date">Date</label>
    <input type="date" name="date" id="date" class="form-control" value="{{ isset($program) ? $program->date->format('Y-m-d') : '' }}">
</div>

<div class="form-group">
    <label for="sets">Sets</label>
    <input type="number" name="sets" id="sets" class="form-control" value="{{ isset($program) ? $program->sets : '' }}">
</div>

<div class="form-group">
    <label for="reps">Reps</label>
    <input type="number" name="reps" id="reps" class="form-control" value="{{ isset($program) ? $program->reps : '' }}">
</div>

<div class="form-group">
    <label for="weight">Weight</label>
    <input type="number" name="weight" id="weight" class="form-control" value="{{ isset($program) ? $program->weight : '' }}">
</div>
