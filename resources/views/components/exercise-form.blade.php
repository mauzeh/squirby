<form action="{{ $action }}" method="POST">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    
    @if ($shouldShowField('title'))
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" class="form-control" 
                   value="{{ old('title', $exercise->title) }}" required>
        </div>
    @endif

    @if ($shouldShowField('description'))
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea name="description" id="description" class="form-control" rows="5">{{ old('description', $exercise->description) }}</textarea>
        </div>
    @endif

    @if ($shouldShowField('exercise_type'))
        <div class="form-group">
            <label for="exercise_type">Exercise Type:</label>
            <select name="exercise_type" id="exercise_type" class="form-control">
                @foreach($getExerciseTypes() as $value => $label)
                    <option value="{{ $value }}" {{ old('exercise_type', $exercise->exercise_type) == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($shouldShowField('band_type'))
        <div class="form-group">
            <label for="band_type">Band Type:</label>
            <select name="band_type" id="band_type" class="form-control">
                @foreach($getBandTypes() as $value => $label)
                    <option value="{{ $value }}" {{ old('band_type', $exercise->band_type) == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($shouldShowField('is_bodyweight'))
        <div class="form-group form-group-checkbox">
            <input type="checkbox" name="is_bodyweight" id="is_bodyweight" value="1" 
                   {{ old('is_bodyweight', $exercise->is_bodyweight) ? 'checked' : '' }}>
            <label for="is_bodyweight">Bodyweight Exercise</label>
        </div>
    @endif

    @if($canCreateGlobal)
        <div class="form-group form-group-checkbox">
            <input type="checkbox" name="is_global" id="is_global" value="1" 
                   {{ old('is_global', $exercise->exists && $exercise->isGlobal()) ? 'checked' : '' }}>
            <label for="is_global">Global Exercise (Available to all users)</label>
        </div>
    @endif

    <button type="submit" class="button">
        {{ $exercise->exists ? 'Update Exercise' : 'Add Exercise' }}
    </button>
</form>