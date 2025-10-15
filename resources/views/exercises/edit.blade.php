@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Exercise</h1>
        <form action="{{ route('exercises.update', $exercise->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" name="title" id="title" class="form-control" value="{{ $exercise->title }}" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" class="form-control" rows="5">{{ $exercise->description }}</textarea>
            </div>
            <div class="form-group">
                <label for="band_type">Band Type:</label>
                <select name="band_type" id="band_type" class="form-control">
                    <option value="">None</option>
                    <option value="resistance" {{ $exercise->band_type == 'resistance' ? 'selected' : '' }}>Resistance</option>
                    <option value="assistance" {{ $exercise->band_type == 'assistance' ? 'selected' : '' }}>Assistance</option>
                </select>
            </div>
            <div class="form-group form-group-checkbox">
                <input type="checkbox" name="is_bodyweight" id="is_bodyweight" value="1" {{ $exercise->is_bodyweight ? 'checked' : '' }}>
                <label for="is_bodyweight">Bodyweight Exercise</label>
            </div>
            @if($canCreateGlobal)
                <div class="form-group form-group-checkbox">
                    <input type="checkbox" name="is_global" id="is_global" value="1" {{ $exercise->isGlobal() ? 'checked' : '' }}>
                    <label for="is_global">Global Exercise (Available to all users)</label>
                </div>
            @endif
            <button type="submit" class="button">Update Exercise</button>
        </form>
    </div>
@endsection
