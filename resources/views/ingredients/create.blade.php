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
            <table>
                <tr>
                    <td><label for="name">Name:</label></td>
                    <td><input type="text" name="name" id="name" value="{{ old('name') }}" required></td>
                </tr>
                <tr>
                    <td><label for="calories">Calories:</label></td>
                    <td><input type="number" name="calories" id="calories" step="0.01" value="{{ old('calories') }}" required></td>
                </tr>
                <tr>
                    <td><label for="protein">Protein (g):</label></td>
                    <td><input type="number" name="protein" id="protein" step="0.01" value="{{ old('protein') }}" required></td>
                </tr>
                <tr>
                    <td><label for="carbs">Carbs (g):</label></td>
                    <td><input type="number" name="carbs" id="carbs" step="0.01" value="{{ old('carbs') }}" required></td>
                </tr>
                <tr>
                    <td><label for="added_sugars">Added Sugars (g):</label></td>
                    <td><input type="number" name="added_sugars" id="added_sugars" step="0.01" value="{{ old('added_sugars') }}" required></td>
                </tr>
                <tr>
                    <td><label for="fats">Fats (g):</label></td>
                    <td><input type="number" name="fats" id="fats" step="0.01" value="{{ old('fats') }}" required></td>
                </tr>
                <tr>
                    <td><label for="sodium">Sodium (mg):</label></td>
                    <td><input type="number" name="sodium" id="sodium" step="0.01" value="{{ old('sodium') }}" required></td>
                </tr>
                <tr>
                    <td><label for="iron">Iron (mg):</label></td>
                    <td><input type="number" name="iron" id="iron" step="0.01" value="{{ old('iron') }}" required></td>
                </tr>
                <tr>
                    <td><label for="potassium">Potassium (mg):</label></td>
                    <td><input type="number" name="potassium" id="potassium" step="0.01" value="{{ old('potassium') }}" required></td>
                </tr>
                <tr>
                    <td colspan="2"><button type="submit">Add Ingredient</button></td>
                </tr>
            </table>
        </form>
        <a href="{{ route('ingredients.index') }}" class="back-button">Back to Ingredients List</a>
    </div>
@endsection