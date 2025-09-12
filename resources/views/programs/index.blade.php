@extends('app')

@section('content')
    <div class="date-navigation flex items-center">
        @php
            $today = \Carbon\Carbon::today();
        @endphp
        @for ($i = -3; $i <= 1; $i++)
            @php
                $date = $today->copy()->addDays($i);
                $dateString = $date->toDateString();
            @endphp
            <a href="{{ route('programs.index', ['date' => $dateString]) }}" class="date-link {{ $selectedDate->toDateString() == $dateString ? 'active' : '' }} {{ $date->isToday() ? 'today-date' : '' }}">
                {{ $date->format('D M d') }}
            </a>
        @endfor
        <label for="date_picker" class="date-pick-label ml-4 mr-2">Or Pick a Date:</label>
        <input type="date" id="date_picker" onchange="window.location.href = '{{ route('programs.index') }}?date=' + this.value;" value="{{ $selectedDate->format('Y-m-d') }}">
    </div>
    <div class="container">
        <h1>Program for {{ $selectedDate->format('M d, Y') }}</h1>

        <a href="{{ route('programs.create', ['date' => $selectedDate->toDateString()]) }}" class="button create">Add Program Entry</a>

        @if ($programs->isNotEmpty())
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th style="width: 1%;"><input type="checkbox" id="select-all-programs"></th>
                        <th class="hide-on-mobile" style="width: 1%; white-space: nowrap; text-align: center;">Sets</th>
                        <th class="hide-on-mobile" style="width: 1%; white-space: nowrap; text-align: center;">Reps</th>
                        <th style="min-width: 150px;">Exercise</th>
                        <th style="width: 1%; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($programs as $program)
                        <tr>
                            <td><input type="checkbox" name="program_ids[]" value="{{ $program->id }}" class="program-checkbox"></td>
                            <td class="hide-on-mobile" style="text-align: center;">{{ $program->sets }}</td>
                            <td class="hide-on-mobile" style="text-align: center;">{{ $program->reps }}</td>
                            <td>
                                {{ $program->exercise->title }}
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    Sets: {{ $program->sets }} / Reps: {{ $program->reps }}
                                </div>
                                @if($program->comments)
                                    <br><small style="font-size: 0.8em; color: #aaa;">{{ $program->comments }}</small>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('programs.edit', $program->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                    <form action="{{ route('programs.destroy', $program->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this entry?');"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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