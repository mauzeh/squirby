<!-- resources/views/components/ingredient-select.blade.php -->
<select name="{{ $name }}" id="{{ $id }}" {{ $attributes }}>
    <option value="">Select an Ingredient</option>
    @foreach ($ingredients as $ingredient)
        <option value="{{ $ingredient->id }}" {{ $ingredient->id == $selected ? 'selected' : '' }}>
            {{ $ingredient->name }} ({{ $ingredient->baseUnit->abbreviation }})
        </option>
    @endforeach
</select>