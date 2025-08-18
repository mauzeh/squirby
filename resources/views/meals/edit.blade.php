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
                        <div class="form-group">
                            <label for="ingredients[{{ $i }}][ingredient_id]">Ingredient {{ $i + 1 }}:</label>
                            <x-ingredient-select name="ingredients[{{ $i }}][ingredient_id]" id="ingredients_{{ $i }}_ingredient_id" :ingredients="$ingredients" :selected="old('ingredients.' . $i . '.ingredient_id', $meal->ingredients->get($i)->id ?? '')" />
                            <label for="ingredients[{{ $i }}][quantity]" style="margin-left: 10px;">Quantity:</label>
                            <x-quantity-input name="ingredients[{{ $i }}][quantity]" id="ingredients_{{ $i }}_quantity" :value="old('ingredients.' . $i . '.quantity', $meal->ingredients->get($i)->pivot->quantity ?? '')" />
                        </div>
                    @endfor
                </div>
                <button type="submit" class="button">Update Meal</button>
            </form>
        </div>
    </div>
@endsection