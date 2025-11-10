@extends('app')

@section('content')
    <div class="container">
        <h1>Workout Templates</h1>
        <a href="{{ route('workout-templates.create') }}" class="button create">Create New Template</a>
        
        @if (session('success'))
            <div class="container success-message-box">
                {!! session('success') !!}
            </div>
        @endif
        @if (session('error'))
            <div class="container error-message-box">
                {{ session('error') }}
            </div>
        @endif

        @if ($templates->isEmpty())
            <p>No templates found. Create one to get started!</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="hide-on-mobile">Description</th>
                        <th class="hide-on-mobile">Exercises</th>
                        <th class="hide-on-mobile">Created</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>
                                {{ $template->name }}
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    {{ $template->exercises_count }} {{ Str::plural('exercise', $template->exercises_count) }}
                                    @if($template->description)
                                        <br><small style="font-size: 0.8em; color: #aaa;">{{ \Illuminate\Support\Str::limit($template->description, 50) }}</small>
                                    @endif
                                    <br><small style="font-size: 0.8em; color: #aaa;">Created {{ $template->created_at->format('M j, Y') }}</small>
                                </div>
                            </td>
                            <td class="hide-on-mobile">{{ $template->description ? \Illuminate\Support\Str::limit($template->description, 100) : '-' }}</td>
                            <td class="hide-on-mobile">{{ $template->exercises_count }} {{ Str::plural('exercise', $template->exercises_count) }}</td>
                            <td class="hide-on-mobile">{{ $template->created_at->format('M j, Y') }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('workout-templates.edit', $template) }}" class="button edit" title="Edit template"><i class="fa-solid fa-pencil"></i></a>
                                    <form action="{{ route('workout-templates.destroy', $template) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this template?');" title="Delete template"><i class="fa-solid fa-trash"></i></button>
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
