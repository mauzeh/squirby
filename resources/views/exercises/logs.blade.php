@extends('app')

@section('content')

    <x-top-exercises-buttons :exercises="$displayExercises" :allExercises="$exercises" :current-exercise-id="$exercise->id" /> 

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
        </div>

        <x-add-lift-log-form :exercises="$exercises" :selectedExercise="$exercise" :sets="$sets ?? null" :reps="$reps ?? null" :weight="$weight ?? null" />

        @if ($liftLogs->isEmpty())
            <p>No lift logs found for this exercise.</p>
        @else
            @if (!empty($chartData['datasets'][0]['data']))
                <div class="form-container">
                    <h3>1RM Progress</h3>
                    <canvas id="oneRepMaxChart"></canvas>
                </div>

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
                    });
                </script>
            @else
                <div class="form-container">
                    <h3>1RM Progress</h3>
                    <p>1RM chart not available for banded exercises.</p>
                </div>
            @endif
        @endif
    </div>
@endsection
