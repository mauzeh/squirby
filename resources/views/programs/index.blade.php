@extends('app')

@section('content')
    <div class="container">
        <h1>Program</h1>

        {{-- Create Form --}}
        <div class="form-container">
            <h3>Add New Program Entry</h3>
            <form action="{{ route('programs.store') }}" method="POST">
                @csrf
                <div class="form-row">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-row">
                    <label for="exercise_id">Exercise:</label>
                    <select name="exercise_id" id="exercise_id" required>
                        <option value="">Select an Exercise</option>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="sets">Sets:</label>
                    <input type="number" name="sets" id="sets" required>
                </div>
                <div class="form-row">
                    <label for="reps">Reps:</label>
                    <input type="number" name="reps" id="reps" required>
                </div>
                <div class="form-row">
                    <label for="weight">Weight:</label>
                    <input type="text" name="weight" id="weight">
                </div>
                <button type="submit" class="button create">Add Program Entry</button>
            </form>
        </div>

        {{-- Program List --}}
        <h2>Program for {{ date('M d, Y') }}</h2>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Exercise</th>
                    <th>Sets</th>
                    <th>Reps</th>
                    <th>Weight</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($programs as $program)
                    <tr>
                        <td>{{ $program->exercise->name }}</td>
                        <td>{{ $program->sets }}</td>
                        <td>{{ $program->reps }}</td>
                        <td>{{ $program->weight }}</td>
                        <td>
                            <a href="{{ route('programs.edit', $program->id) }}" class="button edit">Edit</a>
                            <form action="{{ route('programs.destroy', $program->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No program entries for this day.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
