@extends('app')

@section('content')
    <div class="container">
        <h1>Program</h1>

        <a href="{{ route('programs.create') }}" class="button create"><i class="fas fa-plus"></i> Add Program Entry</a>

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
