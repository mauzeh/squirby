@extends('app')

@section('content')
    @if (session('success'))
        <div class="container success-message-box">
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
                        <th>Summary</th>
                        <th>Macros</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meals as $meal)
                        <tr>
                            <td>{{ $meal->name }}</td>
                            <td>
                                @foreach ($meal->ingredients as $ingredient)
                                    {{ $ingredient->pivot->quantity }} {{ $ingredient->baseUnit->abbreviation }} {{ $ingredient->name }}<br>
                                @endforeach
                            </td>
                            <td>
                                Calories: {{ round($meal->total_macros['calories']) }}<br>
                                Protein: {{ round($meal->total_macros['protein']) }}g<br>
                                Carbs: {{ round($meal->total_macros['carbs']) }}g<br>
                                Fats: {{ round($meal->total_macros['fats']) }}g<br>
                                Cost: ${{ number_format($meal->total_macros['cost'], 2) }}
                            </td>
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