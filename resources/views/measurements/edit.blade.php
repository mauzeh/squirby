@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Measurement</h1>

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
            <form action="{{ route('measurements.update', $measurement->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $measurement->name) }}" required>
                </div>
                <div class="form-group">
                    <label for="value">Value:</label>
                    <input type="number" name="value" id="value" step="0.01" value="{{ old('value', $measurement->value) }}" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit:</label>
                    <select name="unit" id="unit" required>
                        <option value="lbs" @if(old('unit', $measurement->unit) == 'lbs') selected @endif>lbs</option>
                        <option value="in" @if(old('unit', $measurement->unit) == 'in') selected @endif>in</option>
                        <option value="cm" @if(old('unit', $measurement->unit) == 'cm') selected @endif>cm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="logged_at">Date:</label>
                    <input type="date" name="logged_at" id="logged_at" value="{{ old('logged_at', $measurement->logged_at->format('Y-m-d')) }}" required>
                </div>
                <button type="submit" class="button">Update Measurement</button>
            </form>
        </div>
    </div>
@endsection
