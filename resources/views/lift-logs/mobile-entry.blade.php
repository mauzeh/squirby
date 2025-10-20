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
                            <a href="{{ route('exercises.show-logs', $loggedLift->exercise_id) }}" class="button-large button-blue">View All Logs</a>
                        </div>
                    @else
                        @if(isset($program->lastWorkoutWeight))
                            <p class="suggested-weight">
                                <span class="last-weight">Last time: 
                                    @if(isset($program->lastWorkoutIsBanded) && $program->lastWorkoutIsBanded)
                                        Band: {{ $program->lastWorkoutWeight }}
                                    @else
                                        {{ number_format($program->lastWorkoutWeight) }} lbs
                                    @endif
                                    ({{ $program->lastWorkoutSets }} × {{ $program->lastWorkoutReps }}) - {{ $program->lastWorkoutTimeAgo }}
                                </span>
                            </p>
                        @endif

                        @if(isset($program->suggestedNextWeight) || isset($program->suggestedBandColor))
                            <p class="suggested-weight">
                                Suggested: @if(isset($program->suggestedBandColor)) 
                                    Band: {{ $program->suggestedBandColor }}
                                @else
                                    {{ number_format($program->suggestedNextWeight) }} lbs
                                @endif
                                (<x-lift-logs.lift-reps-sets-display :reps="$program->reps" :sets="$program->sets" />)
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
                                        <label>Band Color:</label>
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
                                        <label for="weight_{{ $program->id }}">@if($program->exercise->is_bodyweight) Extra Weight (lbs): @else Weight (lbs): @endif</label>
                                        <div class="input-group">
                                            <button type="button" class="decrement-button" data-field="weight_{{ $program->id }}">-</button>
                                            <input type="number" name="weight" id="weight_{{ $program->id }}" class="large-input" inputmode="decimal" value="{{ $program->suggestedNextWeight ?? ($program->exercise->is_bodyweight ? 0 : '') }}" @if(!$program->exercise->is_bodyweight) required @endif>
                                            <button type="button" class="increment-button" data-field="weight_{{ $program->id }}">+</button>
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="rounds_{{ $program->id }}">Sets:</label>
                                    <div class="input-group">
                                        <button type="button" class="decrement-button" data-field="rounds_{{ $program->id }}">-</button>
                                        <input type="number" name="rounds" id="rounds_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->sets }}">
                                        <button type="button" class="increment-button" data-field="rounds_{{ $program->id }}">+</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="reps_{{ $program->id }}">Reps:</label>
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
        @endif

        <div class="add-exercise-container">
            <button type="button" id="add-exercise-button" class="button-large button-green">Add exercise</button>
        </div>

        <div class="item-list-container hidden" id="exercise-list-container">
            <div class="item-list exercise-list">
                <a href="#" id="new-exercise-link" class="item-list-item exercise-list-item new-exercise-item">✚ Create new exercise</a>
                <div id="new-exercise-form-container" class="hidden">
                    <form action="{{ route('programs.quick-create', ['date' => $selectedDate->toDateString()]) }}" method="POST" class="new-exercise-form">
                        @csrf
                        <input type="hidden" name="redirect_to" value="mobile-entry">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" name="exercise_name" id="exercise_name" class="large-input large-input-text" placeholder="Enter new exercise name..." required>
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
        document.getElementById('add-exercise-button').addEventListener('click', function() {
            document.getElementById('exercise-list-container').classList.remove('hidden');
            this.style.display = 'none';
        });

        document.getElementById('new-exercise-link').addEventListener('click', function(event) {
            event.preventDefault();
            document.getElementById('new-exercise-form-container').classList.remove('hidden');
            document.getElementById('exercise_name').focus();
            this.style.display = 'none';
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
