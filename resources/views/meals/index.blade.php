@extends('app')

@section('content')
    @if (session('success'))
        <div class="container" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="container">
        <h1>Meals List</h1>
        <a href="{{ route('meals.create') }}" class="button">Add New Meal</a>

        @if ($meals->isEmpty())
            <p>No meals found. Please add some!</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meals as $meal)
                        <tr>
                            <td>{{ $meal->name }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('meals.edit', $meal->id) }}" class="button edit">Edit</a>
                                    <form action="{{ route('meals.destroy', $meal->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this meal?');">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection