@extends('app')

@section('content')
    <div class="container">
        <h1>Measurements</h1>
        <a href="{{ route('measurements.create') }}" class="button">Add Measurement</a>
        @if ($measurements->isEmpty())
            <p>No measurements found. Add one to get started!</p>
        @else
            <table class="log-entries-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-measurements"></th>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Date</th>
                    <th class="hide-on-mobile">Comments</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($measurements as $measurement)
                    <tr>
                        <td><input type="checkbox" name="measurement_ids[]" value="{{ $measurement->id }}" class="measurement-checkbox"></td>
                        <td>{{ $measurement->name }}</td>
                        <td>{{ $measurement->value }} {{ $measurement->unit }}</td>
                        <td>{{ $measurement->logged_at->format('m/d/Y H:i') }}</td>
                        <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $measurement->comments }}">{{ $measurement->comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('measurements.edit', $measurement->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('measurements.destroy', $measurement->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this measurement?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" style="text-align:left; font-weight:normal;">
                        <form action="{{ route('measurements.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected measurements?');" style="display:inline;">
                            @csrf
                            <button type="submit" class="button delete">Delete Selected</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>
        @endif

        <div class="form-container">
            <h3>TSV Export</h3>
            <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">{{ $tsv }}</textarea>
            <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
        </div>
    </div>

    <script>
        document.getElementById('select-all-measurements').addEventListener('change', function(e) {
            document.querySelectorAll('.measurement-checkbox').forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
        });

        document.getElementById('delete-selected-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var form = e.target;
            var checkedLogs = document.querySelectorAll('.measurement-checkbox:checked');

            if (checkedLogs.length === 0) {
                alert('Please select at least one measurement to delete.');
                return;
            }

            checkedLogs.forEach(function(checkbox) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'measurement_ids[]';
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
    </script>
@endsection
