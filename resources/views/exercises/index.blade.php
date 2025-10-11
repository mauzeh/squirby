@extends('app')

@section('content')
    <div class="container">
        <h1>Exercises</h1>
        <a href="{{ route('exercises.create') }}" class="button create">Add Exercise</a>
        
        @if (session('success'))
            <div class="container success-message-box">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="container error-message-box">
                {{ session('error') }}
            </div>
        @endif

        @if ($exercises->isEmpty())
            <p>No exercises found. Add one to get started!</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th class="hide-on-mobile">Description</th>
                        <th class="hide-on-mobile">Type</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exercises as $exercise)
                        <tr>
                            <td>
                                <a href="{{ route('exercises.show-logs', $exercise) }}" class="text-white">{{ $exercise->title }}</a>
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    {{ $exercise->is_bodyweight ? 'Bodyweight' : 'Weighted' }}
                                    @if($exercise->description)
                                        <br><small style="font-size: 0.8em; color: #aaa;">{{ \Illuminate\Support\Str::limit($exercise->description, 50) }}</small>
                                    @endif
                                </div>
                            </td>
                            <td class="hide-on-mobile">{{ $exercise->description }}</td>
                            <td class="hide-on-mobile">{{ $exercise->is_bodyweight ? 'Bodyweight' : 'Weighted' }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('exercises.edit', $exercise->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                    <form action="{{ route('exercises.destroy', $exercise->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this exercise?');"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="form-container">
                <h3>TSV Export</h3>
                <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">@foreach ($exercises as $exercise){{ $exercise->title }}	{{ $exercise->description }}	{{ $exercise->is_bodyweight ? 'true' : 'false' }}
@endforeach
                </textarea>
                <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
            </div>

            <script>
                document.getElementById('copy-tsv-button').addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });
            </script>
        @endif

        @if (!app()->environment(['production', 'staging']))
        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('exercises.import-tsv') }}" method="POST">
                @csrf
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>
        @endif
    </div>
@endsection
