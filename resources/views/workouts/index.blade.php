@extends('app')

@section('content')
    @if (session('success'))
        <div class="container success-message-box">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="container error-message-box">
            {{ session('error') }}
        </div>
    @endif
    <div class="container">
        <div class="form-container">
            <h3>1RM Progress</h3>
            <canvas id="oneRepMaxChart"></canvas>
        </div>

        <div class="form-container">
            <h3>Add Workout</h3>
            <form action="{{ route('workouts.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="exercise_id">Exercise:</label>
                    <select name="exercise_id" id="exercise_id" class="form-control" required>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}">{{ $exercise->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="weight">Weight (lbs):</label>
                    <input type="number" name="weight" id="weight" class="form-control" required inputmode="decimal">
                </div>
                <div class="form-group">
                    <label for="reps">Reps:</label>
                    <input type="number" name="reps" id="reps" class="form-control" value="5" required inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="rounds">Rounds:</label>
                    <input type="number" name="rounds" id="rounds" class="form-control" value="3" required inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea name="comments" id="comments" class="form-control" rows="5"></textarea>
                </div>
                <div class="form-group">
                    <label for="date">Date:</label>
                    <x-date-select name="date" id="date" :selectedDate="now()->format('Y-m-d')" required />
                </div>
                <div class="form-group">
                    <label for="logged_at">Time:</label>
                    <x-time-select name="logged_at" id="logged_at" required />
                </div>
                <button type="submit" class="button">Add Workout</button>
            </form>
        </div>

        @if ($workouts->isEmpty())
            <p>No workouts found. Add one to get started!</p>
        @else
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-workouts"></th>
                    <th>Date</th>
                    <th>Exercise</th>
                    <th>Weight (reps x rounds)</th>
                    <th>1RM</th>
                    <th class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Comments</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workouts as $workout)
                    <tr>
                        <td><input type="checkbox" name="workout_ids[]" value="{{ $workout->id }}" class="workout-checkbox"></td>
                        <td>{{ $workout->logged_at->format('m/d') }}</td>
                        <td><a href="{{ route('exercises.show-logs', $workout->exercise) }}">{{ $workout->exercise->title }}</a></td>
                        <td>
                            <span style="font-weight: bold; font-size: 1.2em;">{{ $workout->weight }}&nbsp;lbs</span><br>
                            {{ $workout->reps }}&nbsp;x&nbsp;{{ $workout->rounds }}
                        </td>
                        <td>{{ round($workout->one_rep_max) }} lbs</td>
                        <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $workout->comments }}">{{ $workout->comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('workouts.edit', $workout->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('workouts.destroy', $workout->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this workout?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" style="text-align:left; font-weight:normal;">
                        <form action="{{ route('workouts.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected workouts?');" style="display:inline;">
                            @csrf
                            <button type="submit" class="button delete">Delete Selected</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>

        <div class="form-container">
            <h3>TSV Export</h3>
            <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">@foreach ($workouts as $workout)
{{ $workout->logged_at->format('Y-m-d') }}	{{ $workout->logged_at->format('H:i') }}	{{ $workout->exercise->title }}	{{ $workout->weight }}	{{ $workout->reps }}	{{ $workout->rounds }}	{{ preg_replace('/(\n|\r)+/', ' ', $workout->comments) }}
@endforeach
            </textarea>
            <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('oneRepMaxChart').getContext('2d');
                var oneRepMaxChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($chartData['labels']),
                        datasets: @json($chartData['datasets'])
                    },
                    options: {
                        scales: {
                            y: {
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                    }
                });

                document.getElementById('select-all-workouts').addEventListener('change', function(e) {
                    document.querySelectorAll('.workout-checkbox').forEach(function(checkbox) {
                        checkbox.checked = e.target.checked;
                    });
                });

                document.getElementById('delete-selected-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var form = e.target;
                    var checkedLogs = document.querySelectorAll('.workout-checkbox:checked');

                    if (checkedLogs.length === 0) {
                        alert('Please select at least one workout to delete.');
                        return;
                    }

                    checkedLogs.forEach(function(checkbox) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'workout_ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });

                    form.submit();
                });

                document.getElementById('copy-tsv-button').addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });
            });
        </script>
        @endif

        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('workouts.import-tsv') }}" method="POST">
                @csrf
                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>

    </div>
@endsection