@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Ingredient</h1>

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
        <form action="{{ route('ingredients.update', $ingredient->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" value="{{ old('name', $ingredient->name) }}" required>
            </div>
            <div class="form-group">
                <label for="calories">Calories:</label>
                <input type="number" name="calories" id="calories" step="0.01" value="{{ old('calories', $ingredient->calories) }}" required>
            </div>
            <div class="form-group">
                <label for="protein">Protein (g):</label>
                <input type="number" name="protein" id="protein" step="0.01" value="{{ old('protein', $ingredient->protein) }}" required>
            </div>
            <div class="form-group">
                <label for="carbs">Carbs (g):</label>
                <input type="number" name="carbs" id="carbs" step="0.01" value="{{ old('carbs', $ingredient->carbs) }}" required>
            </div>
            <div class="form-group">
                <label for="added_sugars">Added Sugars (g):</label>
                <input type="number" name="added_sugars" id="added_sugars" step="0.01" value="{{ old('added_sugars', $ingredient->added_sugars) }}" required>
            </div>
            <div class="form-group">
                <label for="fats">Fats (g):</label>
                <input type="number" name="fats" id="fats" step="0.01" value="{{ old('fats', $ingredient->fats) }}" required>
            </div>
            <div class="form-group">
                <label for="sodium">Sodium (mg):</label>
                <input type="number" name="sodium" id="sodium" step="0.01" value="{{ old('sodium', $ingredient->sodium) }}" required>
            </div>
            <div class="form-group">
                <label for="iron">Iron (mg):</label>
                <input type="number" name="iron" id="iron" step="0.01" value="{{ old('iron', $ingredient->iron) }}" required>
            </div>
            <div class="form-group">
                <label for="potassium">Potassium (mg):</label>
                <input type="number" name="potassium" id="potassium" step="0.01" value="{{ old('potassium', $ingredient->potassium) }}" required>
            </div>
            <div class="form-group">
                <label for="base_quantity">Base Quantity:</label>
                <input type="number" name="base_quantity" id="base_quantity" step="0.01" value="{{ old('base_quantity', $ingredient->base_quantity) }}" required>
            </div>
            <div class="form-group">
                <label for="base_unit_id">Base Unit:</label>
                <select name="base_unit_id" id="base_unit_id" required>
                    <option value="">Select a Unit</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('base_unit_id', $ingredient->base_unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Update Ingredient</button>
        </form>
        </div>
        </form>
        <a href="{{ route('ingredients.index') }}" class="back-button">Back to Ingredients List</a>
    </div>
@endsection