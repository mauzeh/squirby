@props(['containerId', 'formId', 'inputId', 'linkId', 'selectedDate', 'recommendations', 'exercises'])

<div class="item-list-container hidden" id="{{ $containerId }}">
    <div class="exercise-autocomplete-container">
        <button type="button" class="item-list-item exercise-list-item close-exercise-list" onclick="hideAllExerciseLists()">✕ Cancel</button>
        
        <input type="text" 
               id="exercise-search-{{ $containerId }}" 
               class="large-input large-input-text exercise-search-input" 
               placeholder="Search exercises..."
               autocomplete="off">
        
        <div id="exercise-suggestions-{{ $containerId }}" class="exercise-suggestions">
            @if (!empty($recommendations))
                @foreach ($recommendations as $recommendation)
                    <a href="{{ route('programs.quick-add', ['exercise' => $recommendation['exercise']->id, 'date' => $selectedDate->toDateString(), 'redirect_to' => 'mobile-entry']) }}" 
                       class="item-list-item exercise-list-item recommended-exercise exercise-suggestion" 
                       data-exercise-name="{{ $recommendation['exercise']->title }}">
                        <span class="item-name exercise-name">{{ $recommendation['exercise']->title }}</span>
                        <span class="item-label exercise-label">⭐ <em>Recommended</em></span>
                    </a>
                @endforeach
            @endif
            
            @foreach ($exercises as $exercise)
                <a href="{{ route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $selectedDate->toDateString(), 'redirect_to' => 'mobile-entry']) }}" 
                   class="item-list-item exercise-list-item {{ $exercise->is_user_created ? 'user-exercise' : '' }} exercise-suggestion" 
                   data-exercise-name="{{ $exercise->title }}">
                    <span class="item-name exercise-name">{{ $exercise->title }}</span>
                    @if($exercise->is_user_created)
                        <span class="item-label exercise-label"><em>Created by you</em></span>
                    @endif
                </a>
            @endforeach
            
            <div id="no-exercises-found-{{ $containerId }}" class="item-list-item no-exercises-found hidden">
                <span class="item-name">No exercises found</span>
            </div>
        </div>
        
        <button type="button" 
                id="save-as-new-{{ $containerId }}" 
                class="button-large button-green save-as-new-button hidden"
                data-selected-date="{{ $selectedDate->toDateString() }}">
            Save as new exercise
        </button>
    </div>
</div>