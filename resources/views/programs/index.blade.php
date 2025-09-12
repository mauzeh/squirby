@extends('app')

@section('content')
    <div class="container">
        <h1>Program</h1>

        <a href="{{ route('programs.create') }}" class="button create">Add Program Entry</a>

        {{-- Program List --}}
        <h2>Program for {{ date('M d, Y') }}</h2>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th style="width: 1%;"><input type="checkbox" id="select-all-programs"></th>
                    <th>Exercise</th>
                    <th>Sets</th>
                    <th>Reps</th>
                    <th>Comments</th>
                    <th style="width: 1%; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($programs as $program)
                    <tr>
                        <td><input type="checkbox" name="program_ids[]" value="{{ $program->id }}" class="program-checkbox"></td>
                        <td>{{ $program->exercise->title }}</td>
                        <td>{{ $program->sets }}</td>
                        <td>{{ $program->reps }}</td>
                        <td>{{ $program->comments }}</td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('programs.edit', $program->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                <form action="{{ route('programs.destroy', $program->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this entry?');"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No program entries for this day.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <form action="{{ route('programs.destroy-selected') }}" method="POST" id="delete-selected-form">
            @csrf
            <button type="submit" class="button delete">Delete Selected</button>
        </form>
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