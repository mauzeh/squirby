@extends('app')

@section('content')
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>{{ $measurementType->name }}</h1>
            <a href="{{ route('body-logs.create', ['measurement_type_id' => $measurementType->id]) }}" class="button">Add Body Log</a>
        </div>

        <div class="form-container">
            <canvas id="measurementChart"></canvas>
        </div>

        @if ($bodyLogs->isEmpty())
            <p>No body logs found for {{ $measurementType->name }}.</p>
        @else
            <table class="log-entries-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-body-logs"></th>
                    <th>Value</th>
                    <th>Date</th>
                    <th class="hide-on-mobile">Comments</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bodyLogs as $bodyLog)
                    <tr>
                        <td><input type="checkbox" name="body_log_ids[]" value="{{ $bodyLog->id }}" class="body-checkbox"></td>
                        <td>{{ $bodyLog->value }} {{ $bodyLog->measurementType->default_unit }}</td>
                        <td>{{ $bodyLog->logged_at->format('m/d/Y H:i') }}</td>
                        <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $bodyLog->comments }}">{{ $bodyLog->comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('body-logs.edit', $bodyLog->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('body-logs.destroy', $bodyLog->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this body log?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" style="text-align:left; font-weight:normal;">
                        <form action="{{ route('body-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected body logs?');" style="display:inline;">
                            @csrf
                            <button type="submit" class="button delete">Delete Selected</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('measurementChart').getContext('2d');
            var measurementChart = new Chart(ctx, {
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
                    spanGaps: true
                }
            });

            document.getElementById('select-all-body-logs').addEventListener('change', function(e) {
                document.querySelectorAll('.body-checkbox').forEach(function(checkbox) {
                    checkbox.checked = e.target.checked;
                });
            });

            document.getElementById('delete-selected-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                var form = e.target;
                var checkedLogs = document.querySelectorAll('.body-checkbox:checked');

                if (checkedLogs.length === 0) {
                    alert('Please select at least one body log to delete.');
                    return;
                }

                checkedLogs.forEach(function(checkbox) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'body_log_ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });

                form.submit();
            });
        });
    </script>
@endsection