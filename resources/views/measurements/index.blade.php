@extends('app')

@section('content')
    <div class="container">
        <h1>Measurements</h1>
        <a href="{{ route('measurements.create') }}" class="button">Add Measurement</a>
        <table class="log-entries-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Date</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($measurements as $measurement)
                    <tr>
                        <td>{{ $measurement->name }}</td>
                        <td>{{ $measurement->value }}</td>
                        <td>{{ $measurement->unit }}</td>
                        <td>{{ $measurement->logged_at->format('m/d/Y') }}</td>
                        <td class="actions-column">
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('measurements.edit', $measurement->id) }}" class="button edit">Edit</a>
                                <form action="{{ route('measurements.destroy', $measurement->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this measurement?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
