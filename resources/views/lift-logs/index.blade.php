@extends('app')

@section('content')
<style>
    @media (max-width: 768px) {
        .chart-container:not(:first-child) {
            display: none;
        }
        .charts-container {
            flex-direction: column;
        }
        .chart-container {
            width: 100% !important;
        }
    }
</style>
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
        <div class="charts-container" style="display: flex; justify-content: space-between; gap: 20px;">
            @foreach ($charts as $chart)
                <div class="form-container chart-container" style="width: 32%;">
                    <h3>
                        {{ $chart['title'] }}
                        &nbsp;
                        <a href="{{ route('exercises.show-logs', ['exercise' => $chart['exercise_id']]) }}" class="button">View Logs</a>
                    </h3>
                    <canvas id="oneRepMaxChart_{{ $loop->index }}"></canvas>
                </div>
            @endforeach
        </div>

        <div class="form-container">
            <h3>Add Lift Log</h3>
            <form action="{{ route('lift-logs.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="exercise_id">Exercise:</label>
                    <select name="exercise_id" id="exercise_id" class="form-control" required>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}" data-is-bodyweight="{{ $exercise->is_bodyweight ? 'true' : 'false' }}">{{ $exercise->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="weight-group">
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
                <button type="submit" class="button">Add Lift Log</button>
            </form>
        </div>

        @if ($liftLogs->isEmpty())
            <p>No lift logs found. Add one to get started!</p>
        @else
        <x-lift-logs-table :liftLogs="$liftLogs->reverse()" />

        <div class="form-container">
            <h3>TSV Export</h3>
            <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">@foreach ($liftLogs as $liftLog)
{{ $liftLog->logged_at->format('m/d/Y') }} 	 {{ $liftLog->logged_at->format('H:i') }} 	 {{ $liftLog->exercise->title }} 	 {{ $liftLog->display_weight }} 	 {{ $liftLog->display_reps }} 	 {{ $liftLog->display_rounds }} 	 {{ preg_replace('/(
|
)+/', ' ', $liftLog->comments) }}
@endforeach
            </textarea>
            <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
        </div>

        @endif

        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('lift-logs.import-tsv') }}" method="POST">
                @csrf
                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const exerciseId = urlParams.get('exercise_id');
            if (exerciseId) {
                document.getElementById('exercise_id').value = exerciseId;
            }

            const exerciseSelect = document.getElementById('exercise_id');
            const weightGroup = document.getElementById('weight-group');
            const weightInput = document.getElementById('weight');

            function toggleWeightInput() {
                const selectedOption = exerciseSelect.options[exerciseSelect.selectedIndex];
                const isBodyweight = selectedOption.dataset.isBodyweight === 'true';

                if (isBodyweight) {
                    weightGroup.style.display = 'none';
                    weightInput.removeAttribute('required');
                    weightInput.value = 0; // Set weight to 0 for bodyweight exercises
                } else {
                    weightGroup.style.display = 'flex';
                    weightInput.setAttribute('required', 'required');
                }
            }

            // Initial call to set state based on default selected option
            toggleWeightInput();

            // Listen for changes on the exercise select dropdown
            exerciseSelect.addEventListener('change', toggleWeightInput);

            @foreach ($charts as $chart)
                var ctx_{{ $loop->index }} = document.getElementById('oneRepMaxChart_{{ $loop->index }}').getContext('2d');
                var oneRepMaxChart_{{ $loop->index }} = new Chart(ctx_{{ $loop->index }}, {
                    type: 'line',
                    data: {
                        datasets: @json($chart['chartData']['datasets'])
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    displayFormats: {
                                        day: 'MMM d, yyyy'
                                    }
                                },
                                min: '{{ $chart["minDate"] }}',
                                max: '{{ $chart["maxDate"] }}'
                            },
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
            @endforeach

            

            const copyTsvButton = document.getElementById('copy-tsv-button');
            if (copyTsvButton) {
                copyTsvButton.addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });
            }
        });
    </script>
@endsection