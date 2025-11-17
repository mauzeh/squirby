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

        <div class="form-container">
        <form action="{{ route('ingredients.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <h2>General Information</h2>
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $prefilledName ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label for="base_quantity">Base Quantity:</label>
                    <input type="number" name="base_quantity" id="base_quantity" step="0.01" value="{{ old('base_quantity', 1) }}" required>
                </div>
                <div class="form-group">
                    <label for="base_unit_id">Base Unit:</label>
                    <select name="base_unit_id" id="base_unit_id" required>
                        <option value="">Select a Unit</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" {{ old('base_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="cost_per_unit">Cost Per Unit:</label>
                    <input type="number" name="cost_per_unit" id="cost_per_unit" step="0.01" value="{{ old('cost_per_unit', 0) }}" required>
                </div>
            </div>

            <div class="form-section">
                <h2>Nutritional Information</h2>
                <div class="form-group">
                    <label for="protein">Protein (g):</label>
                    <input type="number" name="protein" id="protein" step="0.01" value="{{ old('protein') }}" required>
                </div>
                <div class="form-group">
                    <label for="carbs">Carbs (g):</label>
                    <input type="number" name="carbs" id="carbs" step="0.01" value="{{ old('carbs') }}" required>
                </div>
                <div class="form-group">
                    <label for="fats">Fats (g):</label>
                    <input type="number" name="fats" id="fats" step="0.01" value="{{ old('fats') }}" required>
                </div>
                <div class="form-group">
                    <label for="sodium">Sodium (mg):</label>
                    <input type="number" name="sodium" id="sodium" step="0.01" value="{{ old('sodium') }}">
                </div>
                <div class="form-group">
                    <label for="fiber">Fiber (g):</label>
                    <input type="number" name="fiber" id="fiber" step="0.01" value="{{ old('fiber') }}">
                </div>
                <div class="form-group">
                    <label for="added_sugars">Added Sugars (g):</label>
                    <input type="number" name="added_sugars" id="added_sugars" step="0.01" value="{{ old('added_sugars') }}">
                </div>
            </div>

            <div class="form-section">
                <h2>Micronutrients</h2>
                <div class="form-group">
                    <label for="calcium">Calcium (mg):</label>
                    <input type="number" name="calcium" id="calcium" step="0.01" value="{{ old('calcium') }}">
                </div>
                <div class="form-group">
                    <label for="iron">Iron (mg):</label>
                    <input type="number" name="iron" id="iron" step="0.01" value="{{ old('iron') }}">
                </div>
                <div class="form-group">
                    <label for="potassium">Potassium (mg):</label>
                    <input type="number" name="potassium" id="potassium" step="0.01" value="{{ old('potassium') }}">
                </div>
                <div class="form-group">
                    <label for="caffeine">Caffeine (mg):</label>
                    <input type="number" name="caffeine" id="caffeine" step="0.01" value="{{ old('caffeine') }}">
                </div>
            </div>
            <button type="submit" class="button create">Add Ingredient</button>
        </form>
        </div>
        </form>
        <a href="{{ route('ingredients.index') }}" class="button">Back to Ingredients List</a>
    </div>
@endsection