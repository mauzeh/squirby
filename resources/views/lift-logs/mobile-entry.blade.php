@extends('app')

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
                    <h2>{{ $program->exercise->title }}</h2>
                    <p class="details">{{ $program->sets }} × {{ $program->reps }} reps</p>
                    @if($program->comments)
                        <p class="details"><strong>Notes:</strong> {{ $program->comments }}</p>
                    @endif

                    @php
                        $loggedLift = $dailyLiftLogs->get($program->exercise->id);
                    @endphp

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
                            <p><strong>Weight:</strong> {{ $loggedLift->display_weight }} lbs</p>
                            <p><strong>Reps x Sets:</strong> {{ $loggedLift->display_reps }} x {{ $loggedLift->display_rounds }}</p>
                            @if($loggedLift->comments)
                                <p><strong>Comments:</strong> {{ $loggedLift->comments }}</p>
                            @endif
                            <a href="{{ route('exercises.show-logs', $loggedLift->exercise_id) }}" class="button">View All Logs for {{ $loggedLift->exercise->title }}</a>
                        </div>
                    @else
                        @if($program->suggestedNextWeight)
                            <p class="suggested-weight">
                                Suggested: {{ number_format($program->suggestedNextWeight) }} lbs
                                <button type="button" class="button-change change-suggested-weight" data-program-id="{{ $program->id }}">Change</button>
                            </p>
                        @endif
                        <form action="{{ route('lift-logs.store') }}" method="POST" class="lift-log-form">
                            @csrf
                            <input type="hidden" name="exercise_id" value="{{ $program->exercise->id }}">
                            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                            <input type="hidden" name="logged_at" value="{{ now()->format('H:i') }}">
                            <input type="hidden" name="redirect_to" value="mobile-entry">
                            <input type="hidden" name="program_id" value="{{ $program->id }}"> {{-- Pass program_id --}}

                            <div id="form-fields-{{ $program->id }}" class="lift-log-form-fields @if($program->suggestedNextWeight) hidden @endif">
                                @if ($program->exercise->is_bodyweight)
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="button-change toggle-weight-field" data-program-id="{{ $program->id }}">Add additional weight</button>
                                    </div>
                                @endif
                                <div class="form-group weight-form-group @if($program->exercise->is_bodyweight) hidden @endif" id="weight-form-group-{{ $program->id }}">
                                    <label for="weight_{{ $program->id }}">@if($program->exercise->is_bodyweight) Extra Weight (lbs): @else Weight (lbs): @endif</label>
                                    <div class="input-group">
                                        <button type="button" class="decrement-button" data-field="weight_{{ $program->id }}">-</button>
                                        <input type="number" name="weight" id="weight_{{ $program->id }}" class="large-input" inputmode="decimal" value="{{ $program->suggestedNextWeight ?? ($program->exercise->is_bodyweight ? 0 : '') }}" @if(!$program->exercise->is_bodyweight) required @endif>
                                        <button type="button" class="increment-button" data-field="weight_{{ $program->id }}">+</button>
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
                                    <label for="rounds_{{ $program->id }}">Sets:</label>
                                    <div class="input-group">
                                        <button type="button" class="decrement-button" data-field="rounds_{{ $program->id }}">-</button>
                                        <input type="number" name="rounds" id="rounds_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->sets }}">
                                        <button type="button" class="increment-button" data-field="rounds_{{ $program->id }}">+</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="comments_{{ $program->id }}">Comments:</label>
                                    <textarea name="comments" id="comments_{{ $program->id }}" class="large-textarea" rows="3"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="large-button submit-button">Save</button>
                        </form>
                    @endif
                </div>
            @endforeach
        @endif

        <div class="add-exercise-container">
            <button type="button" id="add-exercise-button" class="button-large button-green">Add exercise</button>
        </div>

        <div id="exercise-list-container" class="hidden">
            <h3>Select an exercise to add:</h3>
            <div class="exercise-list">
                <a href="#" id="new-exercise-link" class="exercise-list-item new-exercise-item">New...</a>
                <div id="new-exercise-form-container" class="hidden">
                    <form action="{{ route('programs.quick-create', ['date' => $selectedDate->toDateString()]) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <input type="text" name="exercise_name" id="exercise_name" class="large-input" placeholder="Enter new exercise name..." required>
                        </div>
                        <button type="submit" class="large-button button-green">Add Exercise</button>
                    </form>
                </div>
                @foreach ($exercises as $exercise)
                    <a href="{{ route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $selectedDate->toDateString()]) }}" class="exercise-list-item">{{ $exercise->title }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group .large-input {
            text-align: center;
            flex-grow: 1;
            border-radius: 0; /* Remove radius from input to make it seamless with buttons */
            font-size: 1.5em;
            border: none;
            padding: 15px 10px;
        }
        .decrement-button, .increment-button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 1.5em;
            touch-action: manipulation;
        }
        .decrement-button {
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
        }
        .increment-button {
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
        }
        .add-exercise-container {
            margin-top: 20px;
        }
        .button-green {
            background-color: #28a745;
            color: white;
            text-align: center;
            display: block;
            width: 100%;
            box-sizing: border-box;
            border: none;
            padding: 15px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.5em;
            font-weight: bold;
        }
        .exercise-list-container {
            margin-top: 20px;
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 8px;
        }
        .exercise-list {
            display: flex;
            flex-direction: column;
        }
        .exercise-list-item {
            color: #f2f2f2;
            padding: 15px;
            text-decoration: none;
            border-bottom: 1px solid #555;
            font-size: 1.2em;
        }
        .exercise-list-item:hover {
            background-color: #4a4a4a;
        }
        .new-exercise-item {
            background-color: #4a4a4a;
        }
        .completed-badge {
            border-left: 5px solid #28a745; /* Green border */
            padding-left: 20px;
            position: relative;
        }
        .badge-icon {
            position: absolute;
            left: -15px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #28a745;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            font-weight: bold;
        }
        .mobile-entry-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 15px;
            background-color: #2a2a2a;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            color: #f2f2f2;
        }
        .date-navigation-mobile {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 1.2em;
        }
        .date-navigation-mobile .nav-button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        .date-navigation-mobile .current-date {
            font-weight: bold;
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 25px;
        }
        .no-program-message {
            text-align: center;
            color: #aaa;
            font-size: 1.1em;
            padding: 20px;
            border: 1px dashed #555;
            border-radius: 5px;
        }
        .program-card {
            background-color: #3a3a3a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .program-card-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
        }
        .program-action-button {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 1.2em;
            line-height: 30px;
            text-align: center;
            cursor: pointer;
            margin-left: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .delete-program-button {
            background-color: #dc3545;
        }
        .program-card h2 {
            color: orange;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.8em;
            padding-right: 80px; /* Make space for the buttons */
        }
        .program-card .details {
            font-size: 1.1em;
            color: #ccc;
            margin-bottom: 10px;
        }
        .program-card .suggested-weight {
            font-size: 1.2em;
            color: #28a745; /* Green for suggested weight */
            font-weight: bold;
            margin-bottom: 15px;
        }
        .lift-log-form .form-group {
            margin-bottom: 15px;
        }
        .lift-log-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 1.1em;
        }
        .large-input, .large-textarea {
            width: calc(100% - 20px);
            padding: 15px 10px;
            border: 1px solid #555;
            border-radius: 5px;
            background-color: #4a4a4a;
            color: #f2f2f2;
            font-size: 1.2em;
            box-sizing: border-box;
        }
        .large-textarea {
            resize: vertical;
        }
        .button-change {
            background-color: #6c757d;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            margin-top: 5px;
            display: inline-block;
        }
        .button-change:hover {
            background-color: #5a6268;
        }
        .button-small {
            background-color: #6c757d;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 5px;
            display: inline-block;
        }
        .button-small:hover {
            background-color: #5a6268;
        }
        .button-danger {
            background-color: #dc3545;
        }
        .button-danger:hover {
            background-color: #c82333;
        }
        .submit-button {
            background-color: #007bff;
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.5em;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
        }
        .submit-button:hover {
            background-color: #0056b3;
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .lift-log-form .form-group {
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }
            .lift-log-form label {
                flex: none;
                text-align: left;
                margin-bottom: 5px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.change-suggested-weight').forEach(button => {
                button.addEventListener('click', function() {
                    const programId = this.dataset.programId;
                    const formFields = document.getElementById('form-fields-' + programId);
                    formFields.classList.toggle('hidden');
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
    </script>
@endsection