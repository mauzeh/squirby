@extends('app')

@section('content')
    <div class="container">
        <h1>Body Logs</h1>
        <a href="{{ route('body-logs.create') }}" class="button create">Add Body Log</a>
        <a href="{{ route('measurement-types.index') }}" class="button">Manage Measurement Types</a>
        @if ($bodyLogs->isEmpty())
            <p>No body logs found. Add one to get started!</p>
        @else
            <table class="log-entries-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-body-logs"></th>
                    <th>Type</th>
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
                        <td>
                            @if ($bodyLog->measurementType)
                                <a href="{{ route('body-logs.show-by-type', ['measurementType' => $bodyLog->measurementType->id]) }}">{{ $bodyLog->measurementType->name }}</a>
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $bodyLog->value }} {{ $bodyLog->measurementType ? $bodyLog->measurementType->default_unit : '' }}</td>
                        <td>{{ $bodyLog->logged_at->format('m/d/Y H:i') }}</td>
                        <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $bodyLog->comments }}">{{ $bodyLog->comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('body-logs.edit', $bodyLog->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                <form action="{{ route('body-logs.destroy', $bodyLog->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this body log?');"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" style="text-align:left; font-weight:normal;">
                        <form action="{{ route('body-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected body logs?');" style="display:inline;">
                            @csrf
                            <button type="submit" class="button delete">Delete Selected</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>
        @endif

        @if (!$bodyLogs->isEmpty())
        <div class="form-container">
            <h3>TSV Export</h3>
            <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">{{ $tsv }}</textarea>
            <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
        </div>
        @endif

        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('body-logs.import-tsv') }}" method="POST">
                @csrf
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>
    </div>

    <script>
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

        document.getElementById('copy-tsv-button').addEventListener('click', function() {
            var tsvOutput = document.getElementById('tsv-output');
            tsvOutput.select();
            document.execCommand('copy');
            alert('TSV data copied to clipboard!');
        });
    </script>
@endsection