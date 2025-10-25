@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-entry-lift.css') }}">
@endsection

@section('content')
    <div class="mobile-entry-container">
        <x-mobile-entry.date-navigation 
            :selected-date="$selectedDate"
            route-name="lift-logs.mobile-entry" />

        <x-mobile-entry.page-title :selected-date="$selectedDate" />

        <x-mobile-entry.message-system :errors="$errors ?? null" />

        <x-mobile-entry.add-item-button
            id="add-exercise-button"
            label="Add exercise"
            target-container="exercise-list-container" />

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
                @php
                    $loggedLift = $dailyLiftLogs->get($program->exercise->id);
                    
                    // Build move actions HTML
                    $moveActions = '';
                    if (!$loop->first) {
                        $moveActions .= '<a href="' . route('programs.move-up', $program->id) . '" class="program-action-button">&uarr;</a>';
                    }
                    if (!$loop->last) {
                        $moveActions .= '<a href="' . route('programs.move-down', $program->id) . '" class="program-action-button">&darr;</a>';
                    }
                    
                    // Build card title with reps/sets display
                    $cardTitle = $program->exercise->title;
                    if ($loggedLift) {
                        $cardTitle .= ' (<x-lift-logs.lift-reps-sets-display :reps="' . $loggedLift->display_reps . '" :sets="' . $loggedLift->display_rounds . '" />)';
                    } else {
                        $cardTitle .= ' (<x-lift-logs.lift-reps-sets-display :reps="' . $program->reps . '" :sets="' . $program->sets . '" />)';
                    }
                @endphp
                
                @php
                    // Build complete title with reps/sets display
                    $titleWithReps = $program->exercise->title . ' ';
                    if ($loggedLift) {
                        $titleWithReps .= '(' . $loggedLift->display_reps . ' reps × ' . $loggedLift->display_rounds . ' sets)';
                    } else {
                        $titleWithReps .= '(' . $program->reps . ' reps × ' . $program->sets . ' sets)';
                    }
                @endphp
                
                <x-mobile-entry.item-card
                    :title="$titleWithReps"
                    :delete-route="route('programs.destroy', $program->id)"
                    delete-confirm-text="Are you sure you want to remove this exercise from the program?"
                    :hidden-fields="[
                        'redirect_to' => 'mobile-entry',
                        'date' => $selectedDate->toDateString()
                    ]"
                    :move-actions="$moveActions">
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
                                        <x-mobile-entry.number-input
                                            name="weight"
                                            id="weight_{{ $program->id }}"
                                            :value="$program->suggestedNextWeight ?? ($program->exercise->is_bodyweight ? 0 : '')"
                                            label="@if($program->exercise->is_bodyweight) Extra Weight (lbs): @else Weight (lbs): @endif"
                                            :step="5"
                                            :min="0"
                                            :required="!$program->exercise->is_bodyweight" />
                                    @endif
                                </div>

                                <x-mobile-entry.number-input
                                    name="rounds"
                                    id="rounds_{{ $program->id }}"
                                    :value="$program->sets"
                                    label="Sets:"
                                    :step="1"
                                    :min="1" />

                                <x-mobile-entry.number-input
                                    name="reps"
                                    id="reps_{{ $program->id }}"
                                    :value="$program->reps"
                                    label="Reps:"
                                    :step="1"
                                    :min="1" />

                                <div class="form-group">
                                    <label for="comments_{{ $program->id }}">Comments:</label>
                                    <textarea name="comments" id="comments_{{ $program->id }}" class="large-textarea" rows="3"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="button-large button-blue submit-button">✔ Complete this lift</button>
                        </form>
                    @endif
                </x-mobile-entry.item-card>
            @endforeach
            
            {{-- Bottom add exercise button - reuses the same exercise list --}}
            <x-mobile-entry.add-item-button
                id="add-exercise-button-bottom"
                label="Add exercise"
                target-container="exercise-list-container" />
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


        });
    </script>

    <script>


        // Function to hide all exercise lists and show all buttons
        function hideAllExerciseLists() {
            // Hide exercise list
            const container = document.getElementById('exercise-list-container');
            if (container) {
                container.classList.add('hidden');
            }

            // Show all buttons
            const buttons = ['add-exercise-button', 'add-exercise-button-bottom'];
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (button) {
                    button.style.display = '';
                }
            });

            // Hide new exercise form
            const form = document.getElementById('new-exercise-form-container');
            if (form) {
                form.classList.add('hidden');
            }

            // Show new exercise link
            const link = document.getElementById('new-exercise-link');
            if (link) {
                link.style.display = '';
            }
        }

        // Generic function to handle new-exercise links
        function setupNewExerciseLink(linkId, formId, inputId) {
            const link = document.getElementById(linkId);
            if (link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    document.getElementById(formId).classList.remove('hidden');
                    document.getElementById(inputId).focus();
                    this.style.display = 'none';
                    
                    // Scroll to the form to ensure it's visible
                    setTimeout(() => {
                        document.getElementById(formId).scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }, 100);
                });
            }
        }

        // Setup new exercise link
        setupNewExerciseLink('new-exercise-link', 'new-exercise-form-container', 'exercise_name');
        
        // Listen for add item button clicks to hide other buttons
        document.addEventListener('addItemClicked', function(event) {
            if (event.detail.buttonId === 'add-exercise-button' || event.detail.buttonId === 'add-exercise-button-bottom') {
                hideAllExerciseLists();
            }
        });
    </script>
    <script>




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
