{{-- Shared form partial for workout template create/edit --}}

<div class="form-group">
    <label for="name">Template Name <span style="color: red;">*</span></label>
    <input type="text" 
           class="form-control @error('name') is-invalid @enderror" 
           id="name" 
           name="name" 
           value="{{ old('name', $template->name ?? '') }}" 
           required 
           maxlength="255"
           placeholder="e.g., Push Day, Full Body Workout">
    @error('name')
        <div class="error-message">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="description">Description (Optional)</label>
    <textarea class="form-control @error('description') is-invalid @enderror" 
              id="description" 
              name="description" 
              rows="3"
              placeholder="Add notes about this template...">{{ old('description', $template->description ?? '') }}</textarea>
    @error('description')
        <div class="error-message">{{ $message }}</div>
    @enderror
</div>

<div class="form-section">
    <h2>Exercises <span style="color: red;">*</span></h2>
    
    @error('exercises')
        <div class="error-message">{{ $message }}</div>
    @enderror
    
    {{-- Selected exercises list --}}
    @php
        // Get selected exercises from session or template
        $exerciseIds = old('exercises', $selectedExercises ?? []);
        
        // Ensure $exerciseIds is always an array
        if (!is_array($exerciseIds)) {
            $exerciseIds = [];
        }
        
        $exercises = [];
        
        if (!empty($exerciseIds)) {
            $exercises = \App\Models\Exercise::whereIn('id', $exerciseIds)
                ->get()
                ->keyBy('id');
            
            // Apply aliases for display
            $user = Auth::user();
            $aliasService = app(\App\Services\ExerciseAliasService::class);
            foreach ($exercises as $exercise) {
                $exercise->display_name = $aliasService->getDisplayName($exercise, $user);
            }
        }
    @endphp
    
    @if(count($exerciseIds) > 0)
        <div class="selected-exercises-list">
            @foreach($exerciseIds as $index => $exerciseId)
                @php
                    $exercise = $exercises->get($exerciseId);
                @endphp
                @if($exercise)
                    <div class="selected-exercise-item">
                        <div class="exercise-order">{{ $index + 1 }}</div>
                        <div class="exercise-name">{{ $exercise->display_name }}</div>
                        <div class="exercise-actions">
                            {{-- Move Up Button --}}
                            @if($index > 0)
                                <form method="POST" action="{{ route('workout-templates.reorder') }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="exercises" value="{{ json_encode($exerciseIds) }}">
                                    <input type="hidden" name="move_index" value="{{ $index }}">
                                    <input type="hidden" name="direction" value="up">
                                    <input type="hidden" name="return_to" value="{{ request()->is('*/edit') ? 'edit' : 'create' }}">
                                    @if(request()->is('*/edit'))
                                        <input type="hidden" name="template_id" value="{{ $template->id }}">
                                    @endif
                                    <input type="hidden" name="name" value="{{ old('name', $template->name ?? '') }}">
                                    <input type="hidden" name="description" value="{{ old('description', $template->description ?? '') }}">
                                    <button type="submit" class="button edit" title="Move up">
                                        <i class="fa-solid fa-arrow-up"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="button edit" disabled title="Move up">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                            @endif
                            
                            {{-- Move Down Button --}}
                            @if($index < count($exerciseIds) - 1)
                                <form method="POST" action="{{ route('workout-templates.reorder') }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="exercises" value="{{ json_encode($exerciseIds) }}">
                                    <input type="hidden" name="move_index" value="{{ $index }}">
                                    <input type="hidden" name="direction" value="down">
                                    <input type="hidden" name="return_to" value="{{ request()->is('*/edit') ? 'edit' : 'create' }}">
                                    @if(request()->is('*/edit'))
                                        <input type="hidden" name="template_id" value="{{ $template->id }}">
                                    @endif
                                    <input type="hidden" name="name" value="{{ old('name', $template->name ?? '') }}">
                                    <input type="hidden" name="description" value="{{ old('description', $template->description ?? '') }}">
                                    <button type="submit" class="button edit" title="Move down">
                                        <i class="fa-solid fa-arrow-down"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="button edit" disabled title="Move down">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                            @endif
                            
                            {{-- Remove Button --}}
                            <form method="POST" action="{{ route('workout-templates.remove-exercise') }}" style="display: inline;">
                                @csrf
                                <input type="hidden" name="exercises" value="{{ json_encode($exerciseIds) }}">
                                <input type="hidden" name="remove_index" value="{{ $index }}">
                                <input type="hidden" name="return_to" value="{{ request()->is('*/edit') ? 'edit' : 'create' }}">
                                @if(request()->is('*/edit'))
                                    <input type="hidden" name="template_id" value="{{ $template->id }}">
                                @endif
                                <input type="hidden" name="name" value="{{ old('name', $template->name ?? '') }}">
                                <input type="hidden" name="description" value="{{ old('description', $template->description ?? '') }}">
                                <button type="submit" class="button delete" title="Remove exercise">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <input type="hidden" name="exercises[]" value="{{ $exerciseId }}">
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <p style="color: #999; font-style: italic; padding: 20px; text-align: center; border: 1px solid #ddd; border-radius: 4px;">
            No exercises added yet. Click "Add Exercise" to get started.
        </p>
    @endif
    
    {{-- Add Exercise Button --}}
    <div style="margin-top: 15px;">
        @php
            // Store form data in session before navigating to exercise selection
            $queryParams = [
                'return_to' => request()->is('*/edit') ? 'edit' : 'create',
            ];
            if (request()->is('*/edit')) {
                $queryParams['template_id'] = $template->id;
            }
            
            // Store current form state in session
            session([
                'template_form_data' => [
                    'name' => old('name', $template->name ?? ''),
                    'description' => old('description', $template->description ?? ''),
                    'exercises' => $exerciseIds
                ]
            ]);
        @endphp
        <a href="{{ route('workout-templates.show-exercise-selection', $queryParams) }}" class="button create">
            <i class="fa-solid fa-plus"></i> Add Exercise
        </a>
    </div>
</div>

<div class="form-group" style="margin-top: 30px;">
    <button type="submit" class="button create">
        <i class="fa-solid fa-save"></i> {{ $submitButtonText }}
    </button>
    <a href="{{ route('workout-templates.index') }}" class="button">
        <i class="fa-solid fa-times"></i> Cancel
    </a>
</div>

<style>
.selected-exercises-list {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 15px;
}

.selected-exercise-item {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.exercise-order {
    font-weight: bold;
    margin-right: 15px;
    min-width: 30px;
    text-align: center;
    color: #6c757d;
    font-size: 1.1em;
}

.exercise-name {
    flex: 1;
    font-weight: 500;
}

.exercise-actions {
    display: flex;
    gap: 5px;
}

.exercise-actions form {
    margin: 0;
}

.exercise-actions .button {
    padding: 6px 10px;
    font-size: 0.9em;
    min-width: auto;
}

.exercise-actions .button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .selected-exercise-item {
        flex-wrap: wrap;
    }
    
    .exercise-order {
        min-width: 25px;
        margin-right: 10px;
    }
    
    .exercise-name {
        flex: 1 1 100%;
        margin-bottom: 8px;
    }
    
    .exercise-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
