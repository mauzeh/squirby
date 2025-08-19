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
                        <th class="hide-on-mobile">Base Quantity</th>
                        <th class="hide-on-mobile">Base Unit</th>
                        <th class="hide-on-mobile">Cost</th>
                        <th class="hide-on-mobile">Calories</th>
                        <th class="hide-on-mobile">Protein (g)</th>
                        <th class="hide-on-mobile">Carbs (g)</th>
                        <th class="hide-on-mobile">Added Sugars (g)</th>
                        <th class="hide-on-mobile">Fats (g)</th>
                        <th class="hide-on-mobile">Sodium (mg)</th>
                        <th class="hide-on-mobile">Iron (mg)</th>
                        <th class="hide-on-mobile">Potassium (mg)</th>
                        <th class="hide-on-mobile">Fiber (g)</th>
                        <th class="hide-on-mobile">Calcium (mg)</th>
                        <th class="hide-on-mobile">Caffeine (mg)</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->base_quantity }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->baseUnit->abbreviation }}</td>
                            <td class="hide-on-mobile">{{ number_format($ingredient->cost_per_unit, 2) }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->calories }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->protein }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->carbs }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->added_sugars }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->fats }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->sodium }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->iron }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->potassium }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->fiber }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->calcium }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->caffeine }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('ingredients.edit', $ingredient->id) }}" class="button edit">Edit</a>
                                    <form action="{{ route('ingredients.destroy', $ingredient->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this ingredient?');">Delete</button>
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