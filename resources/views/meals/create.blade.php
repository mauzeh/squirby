@extends('app')

@section('content')
    <div class="container">
        <h1>Create New Meal</h1>

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
            <form action="{{ route('meals.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Meal Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea name="comments" id="comments" rows="3">{{ old('comments') }}</textarea>
                </div>

                <h2>Ingredients</h2>
                <div id="ingredients-container">
                    @for ($i = 0; $i < 10; $i++)
                        <div class="form-group">
                            <label for="ingredients[{{ $i }}][ingredient_id]">Ingredient {{ $i + 1 }}:</label>
                            <x-ingredient-select name="ingredients[{{ $i }}][ingredient_id]" id="ingredients_{{ $i }}_ingredient_id" :ingredients="$ingredients" :selected="old('ingredients.' . $i . '.ingredient_id')" />
                            <label for="ingredients[{{ $i }}][quantity]" style="margin-left: 10px;">Quantity:</label>
                            <input type="number" name="ingredients[{{ $i }}][quantity]" step="0.01" min="0.01" value="{{ old('ingredients.' . $i . '.quantity') }}" style="width: 80px;">
                        </div>
                    @endfor
                </div>
                <button type="submit" class="button">Create Meal</button>
            </form>
        </div>
    </div>
@endsection