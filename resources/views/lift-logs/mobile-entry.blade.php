@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry-lift.css') }}">
@endsection

@section('content')
    <div class="mobile-entry-container">
        <div class="date-navigation-mobile">
            @php
                $today = \Carbon\Carbon::today();
                $prevDay = $selectedDate->copy()->subDay();
                $nextDay = $selectedDate->copy()->addDay();
            @endphp
            <a href="{{ route('lift-logs.mobile-entry', ['date' => $prevDay->toDateString()]) }}" class="nav-button">&lt; Prev</a>
            <a href="{{ route('lift-logs.mobile-entry', ['date' => $today->toDateString()]) }}" class="nav-button">Today</a>
            <a href="{{ route('lift-logs.mobile-entry', ['date' => $nextDay->toDateString()]) }}" class="nav-button">Next &gt;</a>
        </div>

        <h1>
            @if ($selectedDate->isToday())
                Today
            @elseif ($selectedDate->isYesterday())
                Yesterday
            @elseif ($selectedDate->isTomorrow())
                Tomorrow
            @else
                {{ $selectedDate->format('M d, Y') }}
            @endif
        </h1>

        {{-- Message system - Error, success, and validation messages --}}
        @if(session('error'))
            <div class="message-container message-error" id="error-message">
                <div class="message-content">
                    <span class="message-text">{{ session('error') }}</span>
                    <button type="button" class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="message-container message-success" id="success-message">
                <div class="message-content">
                    <span class="message-text">{{ session('success') }}</span>
                    <button type="button" class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                </div>
            </div>
        @endif

        {{-- Client-side validation error display --}}
        <div id="validation-errors" class="message-container message-validation hidden">
            <div class="message-content">
                <span class="message-text"></span>
                <button type="button" class="message-close" onclick="document.getElementById('validation-errors').classList.add('hidden')">&times;</button>
            </div>
        </div>

        <div class="add-exercise-container">
            <button type="button" id="add-exercise-button" class="button-large button-green">Add exercise</button>
        </div>

        <x-lift-logs.mobile-entry.exercise-list 
            container-id="exercise-list-container"
            form-id="new-exercise-form-container"
            input-id="exercise_name"
            link-id="new-exercise-link"
            :selected-date="$selectedDate"
            :recommendations="$recommendations"
            :exercises="$exercises" />

        @if ($programs->isEmpty())
            <p class="no-program-message">No program entries for this day.</p>
        @else
            @foreach ($programs as $program)
                <div class="program-card">
                    <div class="program-card-actions">
                        @if(!$loop->first)
                            <a href="{{ route('programs.move-up', $program->id) }}" class="program-action-button">&uarr;</a>
                        @endif
                        @if(!$loop->last)
                            <a href="{{ route('programs.move-down', $program->id) }}" class="program-action-button">&darr;</a>
                        @endif
                        <form action="{{ route('programs.destroy', $program->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this exercise from the program?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="redirect_to" value="mobile-entry">
                            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                            <button type="submit" class="program-action-button delete-program-button"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                    @php
                        $loggedLift = $dailyLiftLogs->get($program->exercise->id);
                    @endphp
                    <h2>{{ $program->exercise->title }}
                        @if ($loggedLift)
                            (<x-lift-logs.lift-reps-sets-display :reps="$loggedLift->display_reps" :sets="$loggedLift->display_rounds" />)
                        @else
                            (<x-lift-logs.lift-reps-sets-display :reps="$program->reps" :sets="$program->sets" />)
                        @endif
                    </h2>
                    @if($program->comments)
                        <p class="details"><strong>Notes:</strong> {{ $program->comments }}</p>
                    @endif

                    @if ($loggedLift)
                        <div class="logged-summary completed-badge">
                            <div class="badge-icon">&#10004;</div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <h3>Completed!</h3>
                                <form action="{{ route('lift-logs.destroy', $loggedLift->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to undo this?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="mobile-entry">
                                    <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                    <button type="submit" class="button-small button-danger">Undo</button>
                                </form>
                            </div>
                            <p><x-lift-logs.lift-weight-display :liftLog="$loggedLift" /> (<x-lift-logs.lift-reps-sets-display :reps="$loggedLift->display_reps" :sets="$loggedLift->display_rounds" />)</p>
                            @if($loggedLift->comments)
                                <p><strong>Comments:</strong> {{ $loggedLift->comments }}</p>
                            @endif
                        </div>
                    @else
                        @if(isset($program->lastWorkoutWeight))
                            <p class="workout-info-box">
                                <span class="label-green">Last time:</span>
                                <span class="workout-details">
                                    @if(isset($program->lastWorkoutIsBanded) && $program->lastWorkoutIsBanded)
                                        Band: {{ $program->lastWorkoutWeight }}
                                    @else
                                        {{ number_format($program->lastWorkoutWeight) }} lbs
                                    @endif
                                    ({{ $program->lastWorkoutSets }} × {{ $program->lastWorkoutReps }})
                                    <br><span class="time-ago">{{ $program->lastWorkoutTimeAgo }}</span>
                                </span>
                            </p>
                        @endif

                        @if(isset($program->suggestedNextWeight) || isset($program->suggestedBandColor))
                            <p class="workout-info-box">
                                <span class="label-green">Suggested:</span>
                                <span class="workout-details">
                                    @if(isset($program->suggestedBandColor)) 
                                        Band: {{ $program->suggestedBandColor }}
                                    @else
                                        {{ number_format($program->suggestedNextWeight) }} lbs
                                    @endif
                                    (<x-lift-logs.lift-reps-sets-display :reps="$program->reps" :sets="$program->sets" />)
                                </span>
                            </p>
                        @endif
                        <form action="{{ route('lift-logs.store') }}" method="POST" class="lift-log-form">
                            @csrf
                            <input type="hidden" name="exercise_id" value="{{ $program->exercise->id }}">
                            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">

                            <input type="hidden" name="redirect_to" value="mobile-entry">
                            <input type="hidden" name="program_id" value="{{ $program->id }}"> {{-- Pass program_id --}}

                            <div id="form-fields-{{ $program->id }}" class="lift-log-form-fields">
                                @if ($program->exercise->is_bodyweight)
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="button-change toggle-weight-field" data-program-id="{{ $program->id }}">Add additional weight</button>
                                    </div>
                                @endif
                                <div class="form-group weight-form-group @if($program->exercise->is_bodyweight) hidden @endif" id="weight-form-group-{{ $program->id }}">
                                    @if ($program->exercise->band_type)
                                        <label class="form-label-centered">Band Color:</label>
                                        <div class="band-color-selector" id="band-color-selector-{{ $program->id }}">
                                            <input type="hidden" name="band_color" id="band_color_{{ $program->id }}" value="{{ isset($program->suggestedBandColor) ? $program->suggestedBandColor : '' }}">
                                            @foreach(config('bands.colors') as $color => $data)
                                                <button type="button"
                                                        class="band-color-button {{ isset($program->suggestedBandColor) && $program->suggestedBandColor === $color ? 'selected' : '' }} {{ isset($program->suggestedBandColor) && $program->suggestedBandColor === $color ? 'suggested' : '' }}"
                                                        style="background-color: {{ $color }};"
                                                        data-color="{{ $color }}"
                                                        data-program-id="{{ $program->id }}">
                                                    {{ ucfirst($color) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <label for="weight_{{ $program->id }}" class="form-label-centered">@if($program->exercise->is_bodyweight) Extra Weight (lbs): @else Weight (lbs): @endif</label>
                                        <div class="input-group">
                                            <button type="button" class="decrement-button" data-field="weight_{{ $program->id }}">-</button>
                                            <input type="number" name="weight" id="weight_{{ $program->id }}" class="large-input" inputmode="decimal" value="{{ $program->suggestedNextWeight ?? ($program->exercise->is_bodyweight ? 0 : '') }}" @if(!$program->exercise->is_bodyweight) required @endif>
                                            <button type="button" class="increment-button" data-field="weight_{{ $program->id }}">+</button>
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="rounds_{{ $program->id }}" class="form-label-centered">Sets:</label>
                                    <div class="input-group">
                                        <button type="button" class="decrement-button" data-field="rounds_{{ $program->id }}">-</button>
                                        <input type="number" name="rounds" id="rounds_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->sets }}">
                                        <button type="button" class="increment-button" data-field="rounds_{{ $program->id }}">+</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="reps_{{ $program->id }}" class="form-label-centered">Reps:</label>
                                    <div class="input-group">
                                        <button type="button" class="decrement-button" data-field="reps_{{ $program->id }}">-</button>
                                        <input type="number" name="reps" id="reps_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->reps }}">
                                        <button type="button" class="increment-button" data-field="reps_{{ $program->id }}">+</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="comments_{{ $program->id }}">Comments:</label>
                                    <textarea name="comments" id="comments_{{ $program->id }}" class="large-textarea" rows="3"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="button-large button-blue submit-button">✔ Complete this lift</button>
                        </form>
                    @endif
                </div>
            @endforeach
            
            {{-- Duplicate "Add exercise" button at the bottom when there are programs --}}
            <div class="add-exercise-container">
                <button type="button" id="add-exercise-button-bottom" class="button-large button-green">Add exercise</button>
            </div>

            <x-lift-logs.mobile-entry.exercise-list 
                container-id="exercise-list-container-bottom"
                form-id="new-exercise-form-container-bottom"
                input-id="exercise_name_bottom"
                link-id="new-exercise-link-bottom"
                :selected-date="$selectedDate"
                :recommendations="$recommendations"
                :exercises="$exercises" />
        @endif
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.band-color-button').forEach(button => {
                button.addEventListener('click', function() {
                    const programId = this.dataset.programId;
                    const selectedColor = this.dataset.color;
                    const hiddenInput = document.getElementById('band_color_' + programId);
                    hiddenInput.value = selectedColor;

                    // Remove 'selected' class from all buttons for this program
                    document.querySelectorAll(`#band-color-selector-${programId} .band-color-button`).forEach(btn => {
                        btn.classList.remove('selected');
                    });

                    // Add 'selected' class to the clicked button
                    this.classList.add('selected');
                });
            });

            document.querySelectorAll('.toggle-weight-field').forEach(button => {
                button.addEventListener('click', function() {
                    const programId = this.dataset.programId;
                    const weightField = document.getElementById('weight-form-group-' + programId);
                    weightField.classList.toggle('hidden');
                    const weightInput = document.getElementById('weight_' + programId);
                    if (!weightField.classList.contains('hidden')) {
                        weightInput.required = true;
                    } else {
                        weightInput.required = false;
                    }
                    this.parentElement.style.display = 'none';
                });
            });

            // Message system functionality - Auto-hide success/error messages after 5 seconds
            setTimeout(function() {
                const errorMessage = document.getElementById('error-message');
                const successMessage = document.getElementById('success-message');
                
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }
                if (successMessage) {
                    successMessage.style.display = 'none';
                }
            }, 5000);
        });
    </script>

    <script>
        // Generic function to handle add-exercise buttons
        function setupAddExerciseButton(buttonId, containerId) {
            const button = document.getElementById(buttonId);
            if (button) {
                button.addEventListener('click', function() {
                    // Hide all other exercise lists and show their buttons to prevent conflicts
                    hideAllExerciseLists();
                    
                    // Show this specific exercise list and hide this button
                    const container = document.getElementById(containerId);
                    if (container) {
                        container.classList.remove('hidden');
                    }
                    this.style.display = 'none';
                });
            }
        }

        // Function to hide all exercise lists and show all buttons
        function hideAllExerciseLists() {
            // Hide all exercise lists
            const containers = ['exercise-list-container', 'exercise-list-container-bottom'];
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.classList.add('hidden');
                }
            });

            // Show all buttons
            const buttons = ['add-exercise-button', 'add-exercise-button-bottom'];
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (button) {
                    button.style.display = '';
                }
            });

            // Reset all autocomplete interfaces to prevent conflicts
            const autocompleteContainers = ['exercise-list-container', 'exercise-list-container-bottom'];
            autocompleteContainers.forEach(containerId => {
                const searchInput = document.getElementById(`exercise-search-${containerId}`);
                const saveAsNewButton = document.getElementById(`save-as-new-${containerId}`);
                const noExercisesFound = document.getElementById(`no-exercises-found-${containerId}`);
                
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.blur(); // Remove focus to prevent conflicts
                }
                if (saveAsNewButton) {
                    saveAsNewButton.classList.add('hidden');
                }
                if (noExercisesFound) {
                    noExercisesFound.classList.add('hidden');
                }
                
                // Show all exercise suggestions and remove keyboard selection
                const suggestions = document.querySelectorAll(`#${containerId} .exercise-suggestion`);
                suggestions.forEach(suggestion => {
                    suggestion.style.display = '';
                    suggestion.classList.remove('keyboard-selected');
                });
            });
            
            // Reset autocomplete instances' state to prevent conflicts
            if (window.topAutocomplete) {
                window.topAutocomplete.resetState();
            }
            if (window.bottomAutocomplete) {
                window.bottomAutocomplete.resetState();
            }
        }

        // Exercise Autocomplete Class - Enhanced with debouncing and improved functionality
        class ExerciseAutocomplete {
            constructor(containerId, selectedDate) {
                this.containerId = containerId;
                this.selectedDate = selectedDate;
                this.searchInput = document.getElementById(`exercise-search-${containerId}`);
                this.suggestionsContainer = document.getElementById(`exercise-suggestions-${containerId}`);
                this.saveAsNewButton = document.getElementById(`save-as-new-${containerId}`);
                this.noExercisesFound = document.getElementById(`no-exercises-found-${containerId}`);
                this.allSuggestions = document.querySelectorAll(`#${containerId} .exercise-suggestion`);
                this.currentQuery = '';
                this.debounceTimer = null;
                this.debounceDelay = 150; // 150ms debounce for optimal performance
                this.selectedIndex = -1; // For keyboard navigation
                this.visibleSuggestions = [];
                
                this.init();
            }
            
            init() {
                if (!this.searchInput) return;
                
                // Add event listeners with debounced input handling
                this.searchInput.addEventListener('input', (e) => {
                    this.handleDebouncedInput(e.target.value);
                });
                
                this.searchInput.addEventListener('focus', () => {
                    this.showInitialRecommendations();
                });
                
                // Setup save as new button
                if (this.saveAsNewButton) {
                    this.saveAsNewButton.addEventListener('click', () => {
                        this.handleSaveAsNew();
                    });
                }
                
                // Enhanced keyboard navigation
                this.searchInput.addEventListener('keydown', (e) => {
                    this.handleKeydown(e);
                });
                
                // Setup click handlers for exercise suggestions
                this.setupSuggestionClickHandlers();
            }
            
            // Debounced input handling for performance optimization
            handleDebouncedInput(query) {
                // Clear existing timer
                if (this.debounceTimer) {
                    clearTimeout(this.debounceTimer);
                }
                
                // Set new timer
                this.debounceTimer = setTimeout(() => {
                    this.handleInput(query);
                }, this.debounceDelay);
            }
            
            handleInput(query) {
                // Handle edge cases: empty strings, whitespace-only queries
                const trimmedQuery = query.trim();
                this.currentQuery = trimmedQuery.toLowerCase();
                this.selectedIndex = -1; // Reset keyboard selection
                
                // Limit query length to prevent performance issues
                if (trimmedQuery.length > 100) {
                    this.searchInput.value = trimmedQuery.substring(0, 100);
                    this.currentQuery = trimmedQuery.substring(0, 100).toLowerCase();
                }
                
                this.filterExercises();
                this.updateSaveAsNewButton();
            }
            
            // Real-time filtering with case-insensitive substring matching
            filterExercises() {
                let visibleCount = 0;
                this.visibleSuggestions = [];
                
                this.allSuggestions.forEach((suggestion, index) => {
                    const exerciseName = suggestion.dataset.exerciseName.toLowerCase();
                    const matches = this.currentQuery === '' || exerciseName.includes(this.currentQuery);
                    
                    if (matches) {
                        suggestion.style.display = '';
                        suggestion.classList.remove('keyboard-selected');
                        this.visibleSuggestions.push(suggestion);
                        visibleCount++;
                    } else {
                        suggestion.style.display = 'none';
                        suggestion.classList.remove('keyboard-selected');
                    }
                });
                
                // Handle empty search results
                this.handleEmptyResults(visibleCount);
            }
            
            // Handle empty search results display
            handleEmptyResults(visibleCount) {
                if (visibleCount === 0 && this.currentQuery !== '') {
                    this.noExercisesFound.classList.remove('hidden');
                    // Update the content to show what was searched for
                    const searchTerm = this.searchInput.value.trim();
                    if (searchTerm.length > 20) {
                        this.noExercisesFound.textContent = `No exercises found for "${searchTerm.substring(0, 20)}..."`;
                    } else {
                        this.noExercisesFound.textContent = `No exercises found for "${searchTerm}"`;
                    }
                } else {
                    this.noExercisesFound.classList.add('hidden');
                }
            }
            
            updateSaveAsNewButton() {
                const trimmedValue = this.searchInput.value.trim();
                
                // Hide button if query is empty or only whitespace
                if (this.currentQuery === '' || trimmedValue === '') {
                    this.saveAsNewButton.classList.add('hidden');
                    return;
                }
                
                // Check if query exactly matches any existing exercise (case-insensitive)
                const exactMatch = Array.from(this.allSuggestions).some(suggestion => {
                    const exerciseName = suggestion.dataset.exerciseName.toLowerCase();
                    return exerciseName === this.currentQuery && suggestion.style.display !== 'none';
                });
                
                if (exactMatch) {
                    this.saveAsNewButton.classList.add('hidden');
                } else {
                    this.saveAsNewButton.classList.remove('hidden');
                    // Truncate long exercise names in button text
                    if (trimmedValue.length > 30) {
                        this.saveAsNewButton.textContent = `Save as new exercise "${trimmedValue.substring(0, 30)}..."`;
                    } else {
                        this.saveAsNewButton.textContent = `Save as new exercise "${trimmedValue}"`;
                    }
                }
            }
            
            // Show initial recommendations when search field gains focus
            showInitialRecommendations() {
                // Clear any existing query and show all suggestions
                this.currentQuery = '';
                this.selectedIndex = -1;
                this.visibleSuggestions = [];
                
                this.allSuggestions.forEach(suggestion => {
                    suggestion.style.display = '';
                    suggestion.classList.remove('keyboard-selected');
                    this.visibleSuggestions.push(suggestion);
                });
                
                this.noExercisesFound.classList.add('hidden');
                this.saveAsNewButton.classList.add('hidden');
            }
            
            // Reset autocomplete state - used to prevent conflicts between instances
            resetState() {
                this.currentQuery = '';
                this.selectedIndex = -1;
                this.visibleSuggestions = [];
                
                if (this.searchInput) {
                    this.searchInput.value = '';
                }
                if (this.saveAsNewButton) {
                    this.saveAsNewButton.classList.add('hidden');
                }
                if (this.noExercisesFound) {
                    this.noExercisesFound.classList.add('hidden');
                }
                
                this.allSuggestions.forEach(suggestion => {
                    suggestion.style.display = '';
                    suggestion.classList.remove('keyboard-selected');
                });
            }
            
            // Setup click handlers for exercise suggestions
            setupSuggestionClickHandlers() {
                this.allSuggestions.forEach(suggestion => {
                    suggestion.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.handleExerciseSelection(suggestion);
                    });
                });
            }
            
            // Handle exercise selection from suggestions
            handleExerciseSelection(suggestion) {
                // Hide the autocomplete interface immediately for better UX
                hideAllExerciseLists();
                
                // Navigate to the href of the clicked suggestion (programs.quick-add route)
                // This maintains existing Program_Quick_Add functionality and redirect behavior
                window.location.href = suggestion.href;
            }
            
            handleSaveAsNew() {
                const exerciseName = this.searchInput.value.trim();
                
                // Validate input - must not be empty and must be reasonable length
                if (!exerciseName || exerciseName.length === 0) {
                    return;
                }
                
                // Prevent extremely long exercise names
                if (exerciseName.length > 100) {
                    return;
                }
                
                // Create form and submit to quick-create endpoint
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/programs/quick-create/${this.selectedDate}`;
                
                // Add CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken.getAttribute('content');
                    form.appendChild(csrfInput);
                }
                
                // Add exercise name
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'exercise_name';
                nameInput.value = exerciseName;
                form.appendChild(nameInput);
                
                // Add redirect parameter
                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_to';
                redirectInput.value = 'mobile-entry';
                form.appendChild(redirectInput);
                
                // Submit form
                document.body.appendChild(form);
                form.submit();
            }
            
            // Enhanced keyboard navigation
            handleKeydown(e) {
                if (e.key === 'Escape') {
                    // Hide all exercise lists and reset state
                    hideAllExerciseLists();
                    return;
                }
                
                if (e.key === 'Enter') {
                    e.preventDefault();
                    
                    // If a suggestion is selected via keyboard, navigate to it
                    if (this.selectedIndex >= 0 && this.visibleSuggestions[this.selectedIndex]) {
                        this.handleExerciseSelection(this.visibleSuggestions[this.selectedIndex]);
                        return;
                    }
                    
                    // Otherwise, handle save as new if button is visible
                    if (!this.saveAsNewButton.classList.contains('hidden')) {
                        this.handleSaveAsNew();
                    }
                    return;
                }
                
                // Arrow key navigation
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.handleArrowNavigation(e.key);
                    return;
                }
            }
            
            // Handle arrow key navigation through suggestions
            handleArrowNavigation(key) {
                if (this.visibleSuggestions.length === 0) return;
                
                // Remove current selection
                if (this.selectedIndex >= 0 && this.visibleSuggestions[this.selectedIndex]) {
                    this.visibleSuggestions[this.selectedIndex].classList.remove('keyboard-selected');
                }
                
                // Update selected index
                if (key === 'ArrowDown') {
                    this.selectedIndex = (this.selectedIndex + 1) % this.visibleSuggestions.length;
                } else if (key === 'ArrowUp') {
                    this.selectedIndex = this.selectedIndex <= 0 ? 
                        this.visibleSuggestions.length - 1 : 
                        this.selectedIndex - 1;
                }
                
                // Add selection to new item
                if (this.selectedIndex >= 0 && this.visibleSuggestions[this.selectedIndex]) {
                    const selectedSuggestion = this.visibleSuggestions[this.selectedIndex];
                    selectedSuggestion.classList.add('keyboard-selected');
                    
                    // Scroll into view if needed
                    selectedSuggestion.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }
        }

        // Initialize autocomplete for both containers - make globally accessible to prevent conflicts
        window.topAutocomplete = null;
        window.bottomAutocomplete = null;

        // Setup top exercise controls
        setupAddExerciseButton('add-exercise-button', 'exercise-list-container');
        
        // Setup bottom exercise controls  
        setupAddExerciseButton('add-exercise-button-bottom', 'exercise-list-container-bottom');
        
        // Initialize autocomplete instances when containers are shown
        document.getElementById('add-exercise-button')?.addEventListener('click', function() {
            setTimeout(() => {
                // Ensure only one autocomplete instance is active at a time
                if (!window.topAutocomplete) {
                    window.topAutocomplete = new ExerciseAutocomplete('exercise-list-container', '{{ $selectedDate->toDateString() }}');
                }
                // Focus on the search input for immediate interaction
                const searchInput = document.getElementById('exercise-search-exercise-list-container');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 100);
        });
        
        document.getElementById('add-exercise-button-bottom')?.addEventListener('click', function() {
            setTimeout(() => {
                // Ensure only one autocomplete instance is active at a time
                if (!window.bottomAutocomplete) {
                    window.bottomAutocomplete = new ExerciseAutocomplete('exercise-list-container-bottom', '{{ $selectedDate->toDateString() }}');
                }
                // Focus on the search input for immediate interaction
                const searchInput = document.getElementById('exercise-search-exercise-list-container-bottom');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 100);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.increment-button, .decrement-button').forEach(button => {
                button.addEventListener('click', function() {
                    const fieldId = this.dataset.field;
                    const input = document.getElementById(fieldId);
                    const step = this.classList.contains('increment-button') ? 1 : -1;
                    let value = parseFloat(input.value) || 0;
                    if (fieldId.includes('weight')) {
                        value += step * 5;
                    } else {
                        value += step;
                    }
                    if (value < 0) {
                        value = 0;
                    }
                    input.value = value;
                });
            });
        });

        // Message system functions for validation errors
        function showValidationError(message) {
            const errorContainer = document.getElementById('validation-errors');
            const errorText = errorContainer.querySelector('.message-text');
            
            errorText.textContent = message;
            errorContainer.classList.remove('hidden');
            
            // Scroll to error message
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function hideValidationError() {
            const errorContainer = document.getElementById('validation-errors');
            errorContainer.classList.add('hidden');
        }

        // Form validation function for lift logs
        function validateLiftForm(formData) {
            const weight = formData.get('weight');
            const reps = formData.get('reps');
            const sets = formData.get('sets');
            
            if (weight && (isNaN(parseFloat(weight)) || parseFloat(weight) < 0)) {
                return { isValid: false, message: 'Weight must be a positive number.' };
            }
            
            if (reps && (isNaN(parseInt(reps)) || parseInt(reps) <= 0)) {
                return { isValid: false, message: 'Reps must be a positive number.' };
            }
            
            if (sets && (isNaN(parseInt(sets)) || parseInt(sets) <= 0)) {
                return { isValid: false, message: 'Sets must be a positive number.' };
            }
            
            return { isValid: true };
        }
    </script>
@endsection
