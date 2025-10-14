<div class="form-group">
    <label for="exercise_id">Exercise</label>
    <select name="exercise_id" id="exercise_id" class="form-control">
        <option value="">Select an exercise</option>
        @foreach ($exercises as $exercise)
            <option value="{{ $exercise->id }}">{{ $exercise->title }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="new_exercise_name">Or Add New Exercise</label>
    <input type="text" name="new_exercise_name" id="new_exercise_name" class="form-control">
</div>

<div class="form-group">
    <label for="date">Date</label>
    <input type="date" name="date" id="date" class="form-control" value="{{ $date->format('Y-m-d') }}" required>
</div>

<div class="form-group">
    <label>Sets & Reps</label>
    <div class="auto-calculation-info">
        @if(isset($suggestedNextWeight) || isset($suggestedBandColor))
            <p style="margin: 0; color: #b3d7ff; font-style: italic;">
                Suggested: 
                @if(isset($suggestedBandColor))
                    Band: {{ $suggestedBandColor }}
                @else
                    {{ number_format($suggestedNextWeight) }} lbs
                @endif
                for {{ $suggestedReps }} reps, {{ $suggestedSets }} sets.
            </p>
        @else
            <p style="margin: 0; color: #b3d7ff; font-style: italic;">
                Sets and reps will be automatically calculated based on your training progression history. 
                If no training history is available, default values will be used (3 sets, 10 reps).
            </p>
        @endif
    </div>
</div>

<div class="form-group">
    <label for="priority">Priority</label>
    <input type="number" name="priority" id="priority" class="form-control" value="{{ $defaultPriority ?? 0 }}" step="1">
</div>

<div class="form-group">
    <label for="comments">Comments</label>
    <textarea name="comments" id="comments" class="form-control"></textarea>
</div>