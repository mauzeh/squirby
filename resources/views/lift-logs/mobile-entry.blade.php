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
            <span class="current-date">{{ $selectedDate->format('M d, Y') }}</span>
            <a href="{{ route('lift-logs.mobile-entry', ['date' => $nextDay->toDateString()]) }}" class="nav-button">Next &gt;</a>
        </div>

        <h1>Today's Program</h1>

        @if ($programs->isEmpty())
            <p class="no-program-message">No program entries for this day.</p>
        @else
            @foreach ($programs as $program)
                <div class="program-card">
                    <h2>{{ $program->exercise->title }}</h2>
                    <p class="details">Target: {{ $program->sets }} sets of {{ $program->reps }} reps</p>

                    @php
                        $loggedLift = $dailyLiftLogs->get($program->exercise->id);
                    @endphp

                    @if ($loggedLift)
                        <div class="logged-summary completed-badge">
                            <div class="badge-icon">&#10004;</div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <h3>Completed!</h3>
                                <form action="{{ route('lift-logs.destroy', $loggedLift->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this log?');">
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
                            <p class="suggested-weight">Suggested: {{ number_format($program->suggestedNextWeight) }} lbs</p>
                        @endif
                        <form action="{{ route('lift-logs.store') }}" method="POST" class="lift-log-form">
                            @csrf
                            <input type="hidden" name="exercise_id" value="{{ $program->exercise->id }}">
                            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                            <input type="hidden" name="logged_at" value="{{ now()->format('H:i') }}">
                            <input type="hidden" name="redirect_to" value="mobile-entry">
                            <input type="hidden" name="program_id" value="{{ $program->id }}"> {{-- Pass program_id --}}

                            <div class="form-group">
                                <label for="weight_{{ $program->id }}">Weight (lbs):</label>
                                <input type="number" name="weight" id="weight_{{ $program->id }}" class="large-input" inputmode="decimal" value="{{ $program->suggestedNextWeight ?? '' }}">
                            </div>

                            <div class="form-group">
                                <label for="reps_{{ $program->id }}">Reps:</label>
                                <input type="number" name="reps" id="reps_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->reps }}">
                            </div>

                            <div class="form-group">
                                <label for="rounds_{{ $program->id }}">Sets:</label>
                                <input type="number" name="rounds" id="rounds_{{ $program->id }}" class="large-input" inputmode="numeric" value="{{ $program->sets }}">
                            </div>

                            <div class="form-group">
                                <label for="comments_{{ $program->id }}">Comments:</label>
                                <textarea name="comments" id="comments_{{ $program->id }}" class="large-textarea" rows="3"></textarea>
                            </div>

                            <button type="submit" class="large-button submit-button">I did this!</button>
                        </form>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <style>
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
        }
        .program-card h2 {
            color: #007bff;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.8em;
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
    </style>

    <script>
        // No JavaScript for core functionality, but a small script for "Use Suggested" button
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.set-suggested-weight').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const suggestedWeight = this.dataset.weight;
                    document.getElementById(targetId).value = suggestedWeight;
                });
            });
        });
    </script>
@endsection