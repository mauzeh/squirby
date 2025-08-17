@extends('app')

@section('content')
    @if (session('success'))
        <div class="container" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="container">
        <h1>Ingredients List</h1>
        <a href="{{ route('ingredients.create') }}" class="button">Add New Ingredient</a>

        @if ($ingredients->isEmpty())
            <p>No ingredients found. Please add some!</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Calories</th>
                        <th>Protein (g)</th>
                        <th>Carbs (g)</th>
                        <th>Added Sugars (g)</th>
                        <th>Fats (g)</th>
                        <th>Sodium (mg)</th>
                        <th>Iron (mg)</th>
                        <th>Potassium (mg)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->calories }}</td>
                            <td>{{ $ingredient->protein }}</td>
                            <td>{{ $ingredient->carbs }}</td>
                            <td>{{ $ingredient->added_sugars }}</td>
                            <td>{{ $ingredient->fats }}</td>
                            <td>{{ $ingredient->sodium }}</td>
                            <td>{{ $ingredient->iron }}</td>
                            <td>{{ $ingredient->potassium }}</td>
                            <td class="action-buttons">
                                <a href="{{ route('ingredients.edit', $ingredient->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('ingredients.destroy', $ingredient->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this ingredient?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection