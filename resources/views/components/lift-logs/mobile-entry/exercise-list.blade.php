@props(['containerId', 'formId', 'inputId', 'linkId', 'selectedDate', 'recommendations', 'exercises'])

<div class="item-list-container hidden" id="{{ $containerId }}">
    <div class="item-list exercise-list">
        <button type="button" class="item-list-item exercise-list-item close-exercise-list" onclick="hideAllExerciseLists()">✕ Cancel</button>
        
        <div class="exercise-search-container">
            <input type="text" class="exercise-search" placeholder="Search exercises..." autocomplete="off">
        </div>
        
        <a href="#" id="{{ $linkId }}" class="item-list-item exercise-list-item new-exercise-item">✚ Create new exercise</a>
        <div id="{{ $formId }}" class="hidden">
            <form action="{{ route('programs.quick-create', ['date' => $selectedDate->toDateString()]) }}" method="POST" class="new-exercise-form">
                @csrf
                <input type="hidden" name="redirect_to" value="mobile-entry">
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" name="exercise_name" id="{{ $inputId }}" class="large-input large-input-text" required>
                    </div>
                </div>
                <button type="submit" class="button-large button-green">Add Exercise</button>
            </form>
        </div>
        
        @if (!empty($recommendations))
            @foreach ($recommendations as $recommendation)
                <a href="{{ route('programs.quick-add', ['exercise' => $recommendation['exercise']->id, 'date' => $selectedDate->toDateString(), 'redirect_to' => 'mobile-entry']) }}" class="item-list-item exercise-list-item recommended-exercise">
                    <span class="item-name exercise-name">{{ $recommendation['exercise']->title }}</span>
                    <span class="item-label exercise-label">⭐ <em>Recommended</em></span>
                </a>
            @endforeach
        @endif
        
        @foreach ($exercises as $exercise)
            <a href="{{ route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $selectedDate->toDateString(), 'redirect_to' => 'mobile-entry']) }}" class="item-list-item exercise-list-item {{ $exercise->is_user_created ? 'user-exercise' : '' }}">
                <span class="item-name exercise-name">{{ $exercise->title }}</span>
                @if($exercise->is_user_created)
                    <span class="item-label exercise-label"><em>Created by you</em></span>
                @endif
            </a>
        @endforeach
    </div>
</div>