@extends('app')

@section('content')
    <div class="container">
        <h1>Create New Ingredient</h1>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('ingredients.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label for="calories">Calories:</label>
                <input type="number" name="calories" id="calories" step="0.01" value="{{ old('calories') }}" required>
            </div>
            <div class="form-group">
                <label for="protein">Protein (g):</label>
                <input type="number" name="protein" id="protein" step="0.01" value="{{ old('protein') }}" required>
            </div>
            <div class="form-group">
                <label for="carbs">Carbs (g):</label>
                <input type="number" name="carbs" id="carbs" step="0.01" value="{{ old('carbs') }}" required>
            </div>
            <div class="form-group">
                <label for="added_sugars">Added Sugars (g):</label>
                <input type="number" name="added_sugars" id="added_sugars" step="0.01" value="{{ old('added_sugars') }}" required>
            </div>
            <div class="form-group">
                <label for="fats">Fats (g):</label>
                <input type="number" name="fats" id="fats" step="0.01" value="{{ old('fats') }}" required>
            </div>
            <div class="form-group">
                <label for="sodium">Sodium (mg):</label>
                <input type="number" name="sodium" id="sodium" step="0.01" value="{{ old('sodium') }}" required>
            </div>
            <div class="form-group">
                <label for="iron">Iron (mg):</label>
                <input type="number" name="iron" id="iron" step="0.01" value="{{ old('iron') }}" required>
            </div>
            <div class="form-group">
                <label for="potassium">Potassium (mg):</label>
                <input type="number" name="potassium" id="potassium" step="0.01" value="{{ old('potassium') }}" required>
            </div>
            <button type="submit">Add Ingredient</button>
        </form>
        <a href="{{ route('ingredients.index') }}" class="back-button">Back to Ingredients List</a>
    </div>
@endsection