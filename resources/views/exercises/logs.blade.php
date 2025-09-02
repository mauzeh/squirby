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
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>{{ $exercise->title }}</h1>
            <a href="{{ route('workouts.index', ['exercise_id' => $exercise->id]) }}" class="button">Add Workout</a>
        </div>
        

        @if ($workouts->isEmpty())
            <p>No workouts found for this exercise.</p>
        @else
        <div class="form-container">
            <h3>1RM Progress</h3>
            <canvas id="oneRepMaxChart"></canvas>
        </div>

        <table class="log-entries-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-workouts"></th>
                    <th>Date</th>
                    <th>Weight (reps x rounds)</th>
                    <th>1RM (est.)</th>
                    <th class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Comments</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workouts as $workout)
                    <tr>
                        <td><input type="checkbox" name="workout_ids[]" value="{{ $workout->id }}" class="workout-checkbox"></td>
                        <td>{{ $workout->logged_at->format('m/d/y H:i') }}</td>
                        <td>
                            <span style="font-weight: bold; font-size: 1.2em;">{{ $workout->display_weight }} lbs</span><br>
                            {{ $workout->display_reps }} x {{ $workout->display_rounds }}
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
                    <th colspan="6" style="text-align:left; font-weight:normal;">
                        <form action="{{ route('workouts.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected workouts?');" style="display:inline;">
                            @csrf
                            <button type="submit" class="button delete">Delete Selected</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('oneRepMaxChart').getContext('2d');
                var oneRepMaxChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        datasets: @json($chartData['datasets'])
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day'
                                }
                            },
                            y: {
                            }
                        }
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
            });
        </script>
        @endif
    </div>
@endsection
