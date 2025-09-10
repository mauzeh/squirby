@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Program Entry</h1>

        <div class="form-container">
            <form action="{{ route('programs.update', $program->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ $program->date->format('Y-m-d') }}" required>
                </div>
                <div class="form-row">
                    <label for="exercise_id">Exercise:</label>
                    <select name="exercise_id" id="exercise_id" required>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}" {{ $program->exercise_id == $exercise->id ? 'selected' : '' }}>
                                {{ $exercise->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="sets">Sets:</label>
                    <input type="number" name="sets" id="sets" value="{{ $program->sets }}" required>
                </div>
                <div class="form-row">
                    <label for="reps">Reps:</label>
                    <input type="number" name="reps" id="reps" value="{{ $program->reps }}" required>
                </div>
                <div class="form-row">
                    <label for="weight">Weight:</label>
                    <input type="text" name="weight" id="weight" value="{{ $program->weight }}">
                </div>
                <button type="submit" class="button create">Update Program Entry</button>
            </form>
        </div>
    </div>
@endsection
