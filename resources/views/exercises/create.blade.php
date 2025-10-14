@extends('app')

@section('content')
    <div class="container">
        <h1>Add Exercise</h1>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('exercises.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" class="form-control" rows="5">{{ old('description') }}</textarea>
            </div>
            <div class="form-group form-group-checkbox">
                <input type="checkbox" name="is_bodyweight" id="is_bodyweight" value="1" {{ old('is_bodyweight') ? 'checked' : '' }}>
                <label for="is_bodyweight">Bodyweight Exercise</label>
            </div>
            <div class="form-group">
                <label for="band_type">Band Type:</label>
                <select name="band_type" id="band_type" class="form-control">
                    <option value="">None</option>
                    <option value="resistance" {{ old('band_type') == 'resistance' ? 'selected' : '' }}>Resistance</option>
                    <option value="assistance" {{ old('band_type') == 'assistance' ? 'selected' : '' }}>Assistance</option>
                </select>
            </div>
            @if($canCreateGlobal)
                <div class="form-group form-group-checkbox">
                    <input type="checkbox" name="is_global" id="is_global" value="1" {{ old('is_global') ? 'checked' : '' }}>
                    <label for="is_global">Global Exercise (Available to all users)</label>
                </div>
            @endif
            <button type="submit" class="button">Add Exercise</button>
        </form>
    </div>
@endsection
