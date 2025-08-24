@extends('app')

@section('content')
    <div class="container">
        <h1>Exercises</h1>
        <a href="{{ route('exercises.create') }}" class="button">Add Exercise</a>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exercises as $exercise)
                    <tr>
                        <td>{{ $exercise->title }}</td>
                        <td>{{ $exercise->description }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('exercises.edit', $exercise->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('exercises.destroy', $exercise->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this exercise?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
