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

        <x-workouts-table :workouts="$workouts" />

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
        @endif
    </div>
@endsection
