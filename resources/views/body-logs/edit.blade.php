@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Body Log</h1>

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
            <form action="{{ route('body-logs.update', $bodyLog->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="measurement_type_id">Type:</label>
                    <select name="measurement_type_id" id="measurement_type_id" required>
                        @foreach ($measurementTypes as $type)
                            <option value="{{ $type->id }}" @if(old('measurement_type_id', $bodyLog->measurement_type_id) == $type->id) selected @endif>{{ $type->name }} ({{ $type->default_unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="value">Value:</label>
                    <input type="number" name="value" id="value" step="0.01" value="{{ old('value', $bodyLog->value) }}" required>
                </div>
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ old('date', $bodyLog->logged_at->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="logged_at">Time:</label>
                    <x-time-select name="logged_at" id="logged_at" :selectedTime="$bodyLog->logged_at->format('H:i')" required />
                </div>
                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea name="comments" id="comments" class="form-control" rows="5">{{ old('comments', $bodyLog->comments) }}</textarea>
                </div>
                <button type="submit" class="button">Update Body Log</button>
            </form>
        </div>
    </div>
@endsection