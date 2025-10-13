@extends('app')

@section('content')
    <x-date-navigation :navigationData="$navigationData" />
    <div class="container">
        <h1>Program for {{ $selectedDate->format('M d, Y') }}</h1>

        <a href="{{ route('programs.create', ['date' => $selectedDate->toDateString()]) }}" class="button create">Add Program Entry</a>

        @if ($programs->isNotEmpty())
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th style="width: 1%;"><input type="checkbox" id="select-all-programs"></th>
                        
                        <th class="hide-on-mobile" style="width: 1%; white-space: nowrap; text-align: center;">Prio</th>
                        <th>Exercise</th>
                        <th class="hide-on-mobile">Today</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($programs as $program)
                        <tr>
                            <td><input type="checkbox" name="program_ids[]" value="{{ $program->id }}" class="program-checkbox"></td>
                            
                            <td class="hide-on-mobile" style="text-align: center;">{{ $program->priority }}</td>
                            <td>
                                <div class="hide-on-mobile">
                                    <strong>{{ $program->exercise->title }}</strong>
                                    <br><x-lift-reps-sets-display :reps="$program->reps" :sets="$program->sets" />
                                </div>
                                <div class="show-on-mobile">
                                    <strong>{{ $program->exercise->title }}</strong>
                                    <br><x-lift-reps-sets-display :reps="$program->reps" :sets="$program->sets" />
                                    @if($program->suggestedNextWeight)
                                        (<i>{{ number_format($program->suggestedNextWeight) }} lbs</i>)
                                    @endif
                                </div>
                                @if($program->comments)
                                    <small style="font-size: 0.8em; color: #aaa;">{{ $program->comments }}</small>
                                @endif
                            </td>
                            <td class="hide-on-mobile" style="text-align: center;">
                                @if($program->suggestedNextWeight)
                                    {{ number_format($program->suggestedNextWeight) }} lbs
                                @else
                                    <span class="no-suggested-weight-available">N/A</span>
                                @endif
                            </td>
                            <td style="white-space: normal;">
                                <a href="{{ route('exercises.show-logs', ['exercise' => $program->exercise, 'sets' => $program->sets, 'reps' => $program->reps, 'weight' => $program->suggestedNextWeight]) }}" class="button"><i class="fa-solid fa-chart-line"></i></a>
                                <a href="{{ route('programs.edit', $program->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                <form action="{{ route('programs.destroy', $program->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this entry?');"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form action="{{ route('programs.destroy-selected') }}" method="POST" id="delete-selected-form">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                <button type="submit" class="button delete">Delete Selected</button>
            </form>
        @else
            <p>No program entries for this day.</p>
        @endif

        <div class="container">
            <div class="form-container">
                <h3>TSV Export</h3>
                @php
                    $exportOutput = "";

                    foreach ($programs as $program) {
                        $row = [
                            $program->date->format('Y-m-d'),
                            $program->exercise->title,
                            $program->sets,
                            $program->reps,
                            $program->priority,
                            str_replace(["\n", "\r", "\t"], [" ", " ", " "], $program->comments ?? ''), // Sanitize comments
                        ];
                        $exportOutput .= implode("\t", $row) . "\n";
                    }
                @endphp
                <textarea id="exportTsv" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">{{ $exportOutput }}</textarea>
                <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
            </div>
        </div>

        @if (!app()->environment(['production', 'staging']))
        <div class="container">
            <div class="form-container">
                <h3>TSV Import</h3>
                <form action="{{ route('programs.import') }}" method="POST">
                    @csrf
                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    <textarea name="tsv_content" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;" placeholder="Paste TSV content here..."></textarea>
                    <button type="submit" class="button">Import TSV</button>
                </form>
            </div>
        </div>
        @endif

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const copyTsvButton = document.getElementById('copy-tsv-button');
                if (copyTsvButton) {
                    copyTsvButton.addEventListener('click', function() {
                        var tsvOutput = document.getElementById('exportTsv');
                        tsvOutput.select();
                        document.execCommand('copy');
                        alert('TSV data copied to clipboard!');
                    });
                }

                const selectAllCheckbox = document.getElementById('select-all-programs');
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function(e) {
                        document.querySelectorAll('.program-checkbox').forEach(function(checkbox) {
                            checkbox.checked = e.target.checked;
                        });
                    });
                }

                const deleteSelectedForm = document.getElementById('delete-selected-form');
                if (deleteSelectedForm) {
                    deleteSelectedForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        var form = e.target;
                        var checkedPrograms = document.querySelectorAll('.program-checkbox:checked');

                        if (checkedPrograms.length === 0) {
                            alert('Please select at least one program to delete.');
                            return;
                        }

                        checkedPrograms.forEach(function(checkbox) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'program_ids[]';
                            input.value = checkbox.value;
                            form.appendChild(input);
                        });

                        if (confirm('Are you sure you want to delete the selected programs?')) {
                            form.submit();
                        }
                    });
                }
            });
        </script>
@endsection