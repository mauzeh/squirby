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
                    @foreach ($meal->ingredients as $index => $mealIngredient)
                        <div class="ingredient-item">
                            <div class="form-group">
                                <label for="ingredients[{{ $index }}][ingredient_id]">Ingredient:</label>
                                <select name="ingredients[{{ $index }}][ingredient_id]" required>
                                    <option value="">Select an Ingredient</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}" {{ old('ingredients.' . $index . '.ingredient_id', $mealIngredient->id) == $ingredient->id ? 'selected' : '' }}>{{ $ingredient->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ingredients[{{ $index }}][quantity]">Quantity:</label>
                                <input type="number" name="ingredients[{{ $index }}][quantity]" step="0.01" min="0.01" value="{{ old('ingredients.' . $index . '.quantity', $mealIngredient->pivot->quantity) }}" required>
                            </div>
                            <button type="button" class="remove-ingredient button delete">Remove</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" id="add-ingredient" class="button">Add Another Ingredient</button>
                <button type="submit" class="button">Update Meal</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let ingredientIndex = {{ count($meal->ingredients) }};
            document.getElementById('add-ingredient').addEventListener('click', function () {
                const container = document.getElementById('ingredients-container');
                const newItem = document.createElement('div');
                newItem.classList.add('ingredient-item');
                newItem.innerHTML = `
                    <div class="form-group">
                        <label for="ingredients[${ingredientIndex}][ingredient_id]">Ingredient:</label>
                        <select name="ingredients[${ingredientIndex}][ingredient_id]" required>
                            <option value="">Select an Ingredient</option>
                            @foreach ($ingredients as $ingredient)
                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ingredients[${ingredientIndex}][quantity]">Quantity:</label>
                        <input type="number" name="ingredients[${ingredientIndex}][quantity]" step="0.01" min="0.01" required>
                    </div>
                    <button type="button" class="remove-ingredient button delete">Remove</button>
                `;
                container.appendChild(newItem);
                ingredientIndex++;

                newItem.querySelector('.remove-ingredient').addEventListener('click', function () {
                    newItem.remove();
                });
            });

            document.querySelectorAll('.remove-ingredient').forEach(button => {
                button.addEventListener('click', function () {
                    button.closest('.ingredient-item').remove();
                });
            });
        });
    </script>
@endsection