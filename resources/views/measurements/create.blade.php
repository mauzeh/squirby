@extends('app')

@section('content')
    <div class="container">
        <h1>Add Measurement</h1>

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
            <form action="{{ route('measurements.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label for="value">Value:</label>
                    <input type="number" name="value" id="value" step="0.01" value="{{ old('value') }}" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit:</label>
                    <select name="unit" id="unit" required>
                        <option value="lbs" @if(old('unit') == 'lbs') selected @endif>lbs</option>
                        <option value="in" @if(old('unit') == 'in') selected @endif>in</option>
                        <option value="cm" @if(old('unit') == 'cm') selected @endif>cm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="logged_at">Date:</label>
                    <input type="date" name="logged_at" id="logged_at" value="{{ old('logged_at', now()->format('Y-m-d')) }}" required>
                </div>
                <button type="submit" class="button">Add Measurement</button>
            </form>
        </div>
    </div>
@endsection
