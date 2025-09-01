@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Measurement Type</h1>

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
            <form action="{{ route('measurement-types.update', $measurementType->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $measurementType->name) }}" required>
                </div>
                <div class="form-group">
                    <label for="default_unit">Default Unit:</label>
                    <input type="text" name="default_unit" id="default_unit" value="{{ old('default_unit', $measurementType->default_unit) }}" required>
                </div>
                <button type="submit" class="button">Update Measurement Type</button>
            </form>
        </div>
    </div>
@endsection
