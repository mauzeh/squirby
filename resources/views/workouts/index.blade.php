@extends('app')

@section('content')
    <div class="container">
        <h1>Workouts</h1>
        <a href="{{ route('workouts.create') }}" class="button">Add Workout</a>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Exercise</th>
                    <th>Working Set</th>
                    <th>Rounds</th>
                    <th>Warmup Sets</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workouts as $workout)
                    <tr>
                        <td>{{ $workout->logged_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $workout->exercise->title }}</td>
                        <td>{{ $workout->working_set_weight }} lbs x {{ $workout->working_set_reps }} reps</td>
                        <td>{{ $workout->working_set_rounds }}</td>
                        <td>{{ $workout->warmup_sets_comments }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('workouts.edit', $workout->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('workouts.destroy', $workout->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this workout?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
