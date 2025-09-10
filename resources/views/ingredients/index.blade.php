@extends('app')

@section('content')
    @if (session('success'))
        <div class="container success-message-box">
            {{ session('success') }}
        </div>
    @endif

    <div class="container">
        <h1>Ingredients List</h1>
        <a href="{{ route('ingredients.create') }}" class="button">Add New Ingredient</a>

        @if ($ingredients->isEmpty())
            <p>No ingredients found. Please add some!</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Calories</th>
                        <th>P/C/F (g)</th>
                        <th>Cost Per Unit</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->base_quantity }}{{ $ingredient->baseUnit->abbreviation }}</td>
                            <td>{{ round($ingredient->calories) }}</td>
                            <td>{{ $ingredient->protein }}/{{ $ingredient->carbs }}/{{ $ingredient->fats }}</td>
                            <td>{{ number_format($ingredient->cost_per_unit, 2) }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('ingredients.edit', $ingredient->id) }}" class="button edit">Edit</a>
                                    <form action="{{ route('ingredients.destroy', $ingredient->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this ingredient?');"><i class="fa-solid fa-trash"></i></button>
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