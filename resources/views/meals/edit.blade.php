@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Meal</h1>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-container">
            <form action="{{ route('meals.update', $meal->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Meal Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $meal->name) }}" required>
                </div>

                <h2>Ingredients</h2>
                <div id="ingredients-container">
                    @for ($i = 0; $i < 10; $i++)
                        <div class="ingredient-item">
                            <h3>Ingredient {{ $i + 1 }}</h3>
                            <div class="form-group">
                                <label for="ingredients[{{ $i }}][ingredient_id]">Ingredient:</label>
                                <select name="ingredients[{{ $i }}][ingredient_id]">
                                    <option value="">Select an Ingredient</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}" {{ old('ingredients.' . $i . '.ingredient_id', $meal->ingredients->get($i)->id ?? '') == $ingredient->id ? 'selected' : '' }}>{{ $ingredient->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ingredients[{{ $i }}][quantity]">Quantity:</label>
                                <input type="number" name="ingredients[{{ $i }}][quantity]" step="0.01" min="0.01" value="{{ old('ingredients.' . $i . '.quantity', $meal->ingredients->get($i)->pivot->quantity ?? '') }}">
                            </div>
                        </div>
                    @endfor
                </div>
                <button type="submit" class="button">Update Meal</button>
            </form>
        </div>
    </div>
@endsection