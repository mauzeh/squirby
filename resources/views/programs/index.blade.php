@extends('app')

@section('content')
    <div class="container">
        <h1>Program</h1>

        <a href="{{ route('programs.create') }}" class="button create"><i class="fas fa-plus"></i> Add Program Entry</a>

        {{-- Program List --}}
        <h2>Program for {{ date('M d, Y') }}</h2>
        <form action="{{ route('programs.destroy-selected') }}" method="POST" id="delete-selected-form">
            @csrf
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-programs"></th>
                        <th>Exercise</th>
                        <th>Sets</th>
                        <th>Reps</th>
                        <th>Comments</th>
                        <th>Actions</th>
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
                                <a href="{{ route('programs.edit', $program->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('programs.destroy', $program->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No program entries for this day.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <button type="submit" class="button delete">Delete Selected</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all-programs');
            const programCheckboxes = document.querySelectorAll('.program-checkbox');

            selectAllCheckbox.addEventListener('change', function() {
                programCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            const deleteSelectedForm = document.getElementById('delete-selected-form');
            deleteSelectedForm.addEventListener('submit', function(e) {
                const checkedPrograms = document.querySelectorAll('.program-checkbox:checked');
                if (checkedPrograms.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one program to delete.');
                }
            });
        });
    </script>
@endsection