@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Log Entry</h1>

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
            <form action="{{ route('daily-logs.update', $dailyLog->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="ingredient_id">Ingredient:</label>
                    <x-ingredient-select name="ingredient_id" id="ingredient_id" :ingredients="$ingredients" :selected="old('ingredient_id', $dailyLog->ingredient_id)" required />
                </div>
                <div class="form-group">
                    <label for="date">Date:</label>
                    <x-date-select name="date" id="date" :selectedDate="$dailyLog->logged_at->format('Y-m-d')" required />
                </div>
                <div class="form-group">
                    <label for="logged_at">Time:</label>
                    <x-time-select name="logged_at" id="logged_at" :selectedTime="$dailyLog->logged_at->format('H:i')" required />
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <x-quantity-input name="quantity" id="quantity" :value="old('quantity', $dailyLog->quantity)" required />
                </div>
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <input type="text" name="notes" id="notes" value="{{ old('notes', $dailyLog->notes) }}">
                    @error('notes')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="button">Update Log Entry</button>
            </form>
        </div>
    </div>
@endsection