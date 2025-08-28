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
                    <select name="name" id="name" required>
                        <option value="Waist" @if(old('name') == 'Waist') selected @endif>Waist</option>
                        <option value="Arm" @if(old('name') == 'Arm') selected @endif>Arm</option>
                        <option value="Chest" @if(old('name') == 'Chest') selected @endif>Chest</option>
                        <option value="Bodyweight" @if(old('name') == 'Bodyweight') selected @endif>Bodyweight</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="value">Value:</label>
                    <input type="number" name="value" id="value" step="0.01" value="{{ old('value') }}" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit:</label>
                    <select name="unit" id="unit" required>
                        <option value="lbs" @if(old('unit') == 'lbs') selected @endif>lbs</option>
                        <option value="kg" @if(old('unit') == 'kg') selected @endif>kg</option>
                        <option value="in" @if(old('unit') == 'in') selected @endif>in</option>
                        <option value="cm" @if(old('unit') == 'cm') selected @endif>cm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ old('date', now()->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="logged_at">Time:</label>
                    <x-time-select name="logged_at" id="logged_at" required />
                </div>
                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea name="comments" id="comments" class="form-control" rows="5">{{ old('comments') }}</textarea>
                </div>
                <button type="submit" class="button">Add Measurement</button>
            </form>
        </div>
    </div>
@endsection
