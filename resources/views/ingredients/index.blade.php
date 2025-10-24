@extends('app')

@section('content')
    @if (session('success'))
        <div class="container success-message-box">
            {!! session('success') !!}
        </div>
    @endif
    @if (session('error'))
        <div class="container error-message-box">
            {{ session('error') }}
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
                        <th class="hide-on-mobile">Quantity</th>
                        <th class="hide-on-mobile">Calories</th>
                        <th class="hide-on-mobile">P/C/F (g)</th>
                        <th class="hide-on-mobile">Cost Per Unit</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ingredients as $ingredient)
                        <tr>
                            <td>
                                {{ $ingredient->name }}
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    {{ $ingredient->base_quantity }}{{ $ingredient->baseUnit->abbreviation }} - P:{{ $ingredient->protein }} C:{{ $ingredient->carbs }} F:{{ $ingredient->fats }}
                                </div>
                            </td>
                            <td class="hide-on-mobile">{{ $ingredient->base_quantity }}{{ $ingredient->baseUnit->abbreviation }}</td>
                            <td class="hide-on-mobile">{{ round($ingredient->calories) }}</td>
                            <td class="hide-on-mobile">{{ $ingredient->protein }}/{{ $ingredient->carbs }}/{{ $ingredient->fats }}</td>
                            <td class="hide-on-mobile">{{ number_format($ingredient->cost_per_unit, 2) }}</td>
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