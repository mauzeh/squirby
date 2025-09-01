@extends('app')

@section('content')
    <div class="container">
        <h1>Measurement Types</h1>
        <a href="{{ route('measurement-types.create') }}" class="button">Add Measurement Type</a>
        @if ($measurementTypes->isEmpty())
            <p>No measurement types found. Add one to get started!</p>
        @else
            <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Default Unit</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($measurementTypes as $measurementType)
                    <tr>
                        <td>{{ $measurementType->name }}</td>
                        <td>{{ $measurementType->default_unit }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('measurement-types.edit', $measurementType->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('measurement-types.destroy', $measurementType->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this measurement type?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
@endsection
