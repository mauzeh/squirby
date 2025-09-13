@extends('app')

@section('content')
    <div class="container">
        <h1>Exercises</h1>
        <a href="{{ route('exercises.create') }}" class="button create">Add Exercise</a>
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
                        <td><a href="{{ route('exercises.show-logs', $exercise) }}" class="text-white">{{ $exercise->title }}</a></td>
                        <td>{{ $exercise->description }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('exercises.edit', $exercise->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                <form action="{{ route('exercises.destroy', $exercise->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this exercise?');"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
