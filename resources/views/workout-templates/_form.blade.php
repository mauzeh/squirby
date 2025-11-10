{{-- Shared form partial for workout template create/edit --}}

<div class="form-group">
    <label for="name">Template Name <span class="text-danger">*</span></label>
    <input type="text" 
           class="form-control @error('name') is-invalid @enderror" 
           id="name" 
           name="name" 
           value="{{ old('name', $workoutTemplate->name ?? '') }}" 
           required 
           maxlength="255"
           placeholder="e.g., Push Day, Full Body Workout">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="description">Description (Optional)</label>
    <textarea class="form-control @error('description') is-invalid @enderror" 
              id="description" 
              name="description" 
              rows="3"
              placeholder="Add notes about this template...">{{ old('description', $workoutTemplate->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label>Exercises <span class="text-danger">*</span></label>
    <p class="text-muted small">Click "Add Exercise" to select exercises for this template. You can reorder them after adding.</p>
    
    @error('exercises')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    
    {{-- Selected exercises list --}}
    <div id="selected-exercises-list" class="selected-exercises-list mb-3">
        @if(isset($workoutTemplate) && $workoutTemplate->exercises->count() > 0)
            @foreach($workoutTemplate->exercises as $index => $exercise)
                <div class="selected-exercise-item" data-exercise-id="{{ $exercise->id }}">
                    <div class="exercise-order">{{ $index + 1 }}</div>
                    <div class="exercise-name">{{ $exercise->title }}</div>
                    <div class="exercise-actions">
                        <button type="button" class="btn btn-sm btn-secondary move-up" {{ $index === 0 ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary move-down" {{ $index === $workoutTemplate->exercises->count() - 1 ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger remove-exercise">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <input type="hidden" name="exercises[]" value="{{ $exercise->id }}">
                </div>
            @endforeach
        @endif
    </div>
    
    {{-- Add Exercise Button --}}
    <button type="button" class="btn btn-success" id="add-exercise-btn">
        <i class="fas fa-plus"></i> Add Exercise
    </button>
</div>

{{-- Exercise Selection Modal/Section (reusing mobile entry UI) --}}
<div id="exercise-selection-section" class="exercise-selection-section" style="display: none;">
    <div class="exercise-selection-header">
        <h3>Select Exercise</h3>
        <button type="button" class="btn btn-secondary" id="cancel-selection-btn">
            <i class="fas fa-times"></i> Cancel
        </button>
    </div>
    
    <div class="item-filter-container">
        <div class="item-filter-group">
            <div class="item-filter-input-wrapper">
                <input type="text" 
                       class="item-filter-input form-control" 
                       id="exercise-filter-input"
                       placeholder="{{ $exerciseSelectionList['filterPlaceholder'] }}" 
                       autocomplete="off" 
                       autocorrect="off" 
                       autocapitalize="off" 
                       spellcheck="false">
                <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
            </div>
            @if(isset($exerciseSelectionList['createForm']))
            <form method="{{ $exerciseSelectionList['createForm']['method'] }}" 
                  action="{{ $exerciseSelectionList['createForm']['action'] }}" 
                  class="create-item-form d-inline-block">
                @csrf
                <input type="hidden" name="{{ $exerciseSelectionList['createForm']['inputName'] }}" class="create-item-input" value="">
                @if(isset($exerciseSelectionList['createForm']['hiddenFields']))
                    @foreach($exerciseSelectionList['createForm']['hiddenFields'] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                @endif
                <button type="submit" class="btn btn-primary" aria-label="{{ $exerciseSelectionList['createForm']['ariaLabel'] }}">
                    <span class="plus-icon">{{ $exerciseSelectionList['createForm']['submitText'] }}</span>
                </button>
            </form>
            @endif
        </div>
    </div>
    
    <ul class="item-selection-list exercise-selection-list">
        @foreach($exerciseSelectionList['items'] as $item)
        <li>
            <a href="javascript:void(0)" 
               class="item-selection-card item-selection-card--{{ $item['type']['cssClass'] }} exercise-selection-item" 
               data-exercise-id="{{ str_replace('exercise-', '', $item['id']) }}"
               data-exercise-name="{{ $item['name'] }}"
               aria-label="Select {{ $item['name'] }}">
                <span class="item-name">{{ $item['name'] }}</span>
                <span class="item-type">{!! $item['type']['label'] !!}</span>
            </a>
        </li>
        @endforeach
        <li class="no-results-item" style="display: none;">
            <div class="item-selection-card item-selection-card--no-results">
                <span class="item-name">{{ $exerciseSelectionList['noResultsMessage'] }}</span>
                <span class="item-type">No matches</span>
            </div>
        </li>
    </ul>
</div>

<div class="form-group mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> {{ $submitButtonText }}
    </button>
    <a href="{{ route('workout-templates.index') }}" class="btn btn-secondary">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

@push('styles')
<style>
.selected-exercises-list {
    border: 1px solid #ddd;
    border-radius: 4px;
    min-height: 100px;
    padding: 10px;
}

.selected-exercises-list:empty::before {
    content: 'No exercises added yet. Click "Add Exercise" to get started.';
    color: #999;
    font-style: italic;
    display: block;
    text-align: center;
    padding: 30px;
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
}

.exercise-name {
    flex: 1;
    font-weight: 500;
}

.exercise-actions {
    display: flex;
    gap: 5px;
}

.exercise-actions .btn {
    padding: 4px 8px;
}

.exercise-selection-section {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: white;
    z-index: 1050;
    overflow-y: auto;
    padding: 20px;
}

.exercise-selection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
}

.exercise-selection-list {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.item-selection-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}

.item-selection-card:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    transform: translateX(5px);
}

.item-selection-card--in-program {
    background: #d4edda;
    border-color: #c3e6cb;
}

.item-selection-card--recent {
    background: #d1ecf1;
    border-color: #bee5eb;
}

.item-selection-card--custom {
    background: #fff3cd;
    border-color: #ffeaa7;
}

.item-name {
    flex: 1;
    font-weight: 500;
}

.item-type {
    font-size: 0.85em;
    color: #6c757d;
    margin-left: 10px;
}

.item-filter-container {
    margin-bottom: 15px;
}

.item-filter-group {
    display: flex;
    gap: 10px;
}

.item-filter-input-wrapper {
    flex: 1;
    position: relative;
}

.btn-clear-filter {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 20px;
    color: #6c757d;
    cursor: pointer;
    padding: 0 5px;
}

.create-item-form .plus-icon {
    font-size: 18px;
    font-weight: bold;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addExerciseBtn = document.getElementById('add-exercise-btn');
    const cancelSelectionBtn = document.getElementById('cancel-selection-btn');
    const exerciseSelectionSection = document.getElementById('exercise-selection-section');
    const selectedExercisesList = document.getElementById('selected-exercises-list');
    const exerciseFilterInput = document.getElementById('exercise-filter-input');
    const clearFilterBtn = document.querySelector('.btn-clear-filter');
    const createItemForm = document.querySelector('.create-item-form');
    const createItemInput = document.querySelector('.create-item-input');
    
    // Show exercise selection
    addExerciseBtn.addEventListener('click', function() {
        exerciseSelectionSection.style.display = 'block';
        exerciseFilterInput.focus();
    });
    
    // Hide exercise selection
    cancelSelectionBtn.addEventListener('click', function() {
        exerciseSelectionSection.style.display = 'none';
        exerciseFilterInput.value = '';
        filterExercises('');
    });
    
    // Handle exercise selection
    document.querySelectorAll('.exercise-selection-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const exerciseId = this.dataset.exerciseId;
            const exerciseName = this.dataset.exerciseName;
            
            // Check if already added
            if (document.querySelector(`.selected-exercise-item[data-exercise-id="${exerciseId}"]`)) {
                alert('This exercise is already in the template.');
                return;
            }
            
            addExerciseToList(exerciseId, exerciseName);
            exerciseSelectionSection.style.display = 'none';
            exerciseFilterInput.value = '';
            filterExercises('');
        });
    });
    
    // Filter exercises
    exerciseFilterInput.addEventListener('input', function() {
        const filterValue = this.value.toLowerCase();
        filterExercises(filterValue);
        clearFilterBtn.style.display = filterValue ? 'block' : 'none';
        
        // Update create form input
        createItemInput.value = this.value;
    });
    
    clearFilterBtn.addEventListener('click', function() {
        exerciseFilterInput.value = '';
        filterExercises('');
        this.style.display = 'none';
        createItemInput.value = '';
    });
    
    function filterExercises(filterValue) {
        const items = document.querySelectorAll('.exercise-selection-list > li:not(.no-results-item)');
        const noResultsItem = document.querySelector('.no-results-item');
        let visibleCount = 0;
        
        items.forEach(function(item) {
            const name = item.querySelector('.item-name').textContent.toLowerCase();
            if (name.includes(filterValue)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        noResultsItem.style.display = visibleCount === 0 ? '' : 'none';
    }
    
    function addExerciseToList(exerciseId, exerciseName) {
        const currentCount = selectedExercisesList.querySelectorAll('.selected-exercise-item').length;
        const orderNumber = currentCount + 1;
        
        const itemHtml = `
            <div class="selected-exercise-item" data-exercise-id="${exerciseId}">
                <div class="exercise-order">${orderNumber}</div>
                <div class="exercise-name">${exerciseName}</div>
                <div class="exercise-actions">
                    <button type="button" class="btn btn-sm btn-secondary move-up" ${orderNumber === 1 ? 'disabled' : ''}>
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary move-down">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger remove-exercise">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="hidden" name="exercises[]" value="${exerciseId}">
            </div>
        `;
        
        selectedExercisesList.insertAdjacentHTML('beforeend', itemHtml);
        updateMoveButtons();
    }
    
    // Handle move up/down and remove
    selectedExercisesList.addEventListener('click', function(e) {
        const target = e.target.closest('button');
        if (!target) return;
        
        const item = target.closest('.selected-exercise-item');
        
        if (target.classList.contains('move-up')) {
            const prev = item.previousElementSibling;
            if (prev) {
                item.parentNode.insertBefore(item, prev);
                updateOrderNumbers();
                updateMoveButtons();
            }
        } else if (target.classList.contains('move-down')) {
            const next = item.nextElementSibling;
            if (next) {
                item.parentNode.insertBefore(next, item);
                updateOrderNumbers();
                updateMoveButtons();
            }
        } else if (target.classList.contains('remove-exercise')) {
            item.remove();
            updateOrderNumbers();
            updateMoveButtons();
        }
    });
    
    function updateOrderNumbers() {
        const items = selectedExercisesList.querySelectorAll('.selected-exercise-item');
        items.forEach(function(item, index) {
            item.querySelector('.exercise-order').textContent = index + 1;
        });
    }
    
    function updateMoveButtons() {
        const items = selectedExercisesList.querySelectorAll('.selected-exercise-item');
        items.forEach(function(item, index) {
            const moveUpBtn = item.querySelector('.move-up');
            const moveDownBtn = item.querySelector('.move-down');
            
            moveUpBtn.disabled = index === 0;
            moveDownBtn.disabled = index === items.length - 1;
        });
    }
});
</script>
@endpush
