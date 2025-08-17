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
                    <select name="ingredient_id" id="ingredient_id" required>
                        <option value="">Select an Ingredient</option>
                        @foreach ($ingredients as $ingredient)
                            <option value="{{ $ingredient->id }}" {{ old('ingredient_id', $dailyLog->ingredient_id) == $ingredient->id ? 'selected' : '' }}>{{ $ingredient->name }} ({{ $ingredient->baseUnit->abbreviation }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="logged_at">Time:</label>
                    <select name="logged_at" id="logged_at" required>
                    @php
                        $selectedTime = old('logged_at', $dailyLog->logged_at->format('H:i'));
                        for ($h = 0; $h < 24; $h++) {
                            for ($m = 0; $m < 60; $m += 15) {
                                $time = sprintf('%02d:%02d', $h, $m);
                                $isSelected = ($time === $selectedTime) ? 'selected' : '';
                                echo "<option value=\"" . $time . "\" " . $isSelected . ">" . $time . "</option>";
                            }
                        }
                    @endphp
                </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" value="{{ old('quantity', $dailyLog->quantity) }}" required>
                </div>
                <button type="submit" class="button">Update Log Entry</button>
            </form>
        </div>
    </div>
@endsection