@extends('app')

@section('content')
    <div class="container">
        <h1>Ingredient Details: {{ $ingredient->name }}</h1>

        <div class="detail-item">
            <strong>Name:</strong> {{ $ingredient->name }}
        </div>
        <div class="detail-item">
            <strong>Calories:</strong> {{ $ingredient->calories }}
        </div>
        <div class="detail-item">
            <strong>Protein (g):</strong> {{ $ingredient->protein }}
        </div>
        <div class="detail-item">
            <strong>Carbs (g):</strong> {{ $ingredient->carbs }}
        </div>
        <div class="detail-item">
            <strong>Added Sugars (g):</strong> {{ $ingredient->added_sugars }}
        </div>
        <div class="detail-item">
            <strong>Fats (g):</strong> {{ $ingredient->fats }}
        </div>
        <div class="detail-item">
            <strong>Sodium (mg):</strong> {{ $ingredient->sodium }}
        </div>
        <div class="detail-item">
            <strong>Iron (mg):</strong> {{ $ingredient->iron }}
        </div>
        <div class="detail-item">
            <strong>Potassium (mg):</strong> {{ $ingredient->potassium }}
        </div>

        <a href="{{ route('ingredients.index') }}" class="back-button">Back to Ingredients List</a>
    </div>
@endsection