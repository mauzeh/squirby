@extends('app')

@section('content')
    <div class="container">
        <h1>Exercise Intelligence Management</h1>
        
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
        @if (session('info'))
            <div class="container info-message-box">
                {{ session('info') }}
            </div>
        @endif

        @if ($exercises->isEmpty())
            <p>No global exercises found. Create some global exercises first to add intelligence data.</p>
        @else
            <div style="margin-bottom: 20px;">
                <p>Manage intelligence data for global exercises. Intelligence data enables smart workout recommendations based on muscle groups, movement patterns, and recovery requirements.</p>
            </div>

            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th>Exercise</th>
                        <th class="hide-on-mobile">Intelligence Status</th>
                        <th class="hide-on-mobile">Movement Type</th>
                        <th class="hide-on-mobile">Difficulty</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exercises as $exercise)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <span class="badge" style="background-color: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7em; white-space: nowrap; display: inline-block; flex-shrink: 0;">Global</span>
                                    <div style="flex: 1; word-wrap: break-word;">
                                        {{ $exercise->title }}
                                        @if($exercise->description)
                                            <br><small style="font-size: 0.8em; color: #aaa;">{{ \Illuminate\Support\Str::limit($exercise->description, 80) }}</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc; margin-top: 5px;">
                                    @if($exercise->hasIntelligence())
                                        <span style="color: #4CAF50;">✓ Has Intelligence</span>
                                        <br>{{ ucfirst(str_replace('_', ' ', $exercise->intelligence->movement_archetype)) }}
                                        • Level {{ $exercise->intelligence->difficulty_level }}
                                    @else
                                        <span style="color: #FFC107;">⚠ No Intelligence</span>
                                    @endif
                                </div>
                            </td>
                            <td class="hide-on-mobile">
                                @if($exercise->hasIntelligence())
                                    <span style="color: #4CAF50; font-weight: bold;">✓ Complete</span>
                                    <br><small style="color: #aaa;">
                                        {{ count($exercise->intelligence->muscle_data['muscles'] ?? []) }} muscles defined
                                    </small>
                                @else
                                    <span style="color: #FFC107; font-weight: bold;">⚠ Missing</span>
                                    <br><small style="color: #aaa;">No intelligence data</small>
                                @endif
                            </td>
                            <td class="hide-on-mobile">
                                @if($exercise->hasIntelligence())
                                    <strong>{{ ucfirst(str_replace('_', ' ', $exercise->intelligence->movement_archetype)) }}</strong>
                                    <br><small style="color: #aaa;">{{ ucfirst($exercise->intelligence->category) }}</small>
                                @else
                                    <span style="color: #666;">—</span>
                                @endif
                            </td>
                            <td class="hide-on-mobile">
                                @if($exercise->hasIntelligence())
                                    <strong>Level {{ $exercise->intelligence->difficulty_level }}</strong>
                                    <br><small style="color: #aaa;">{{ $exercise->intelligence->recovery_hours }}h recovery</small>
                                @else
                                    <span style="color: #666;">—</span>
                                @endif
                            </td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    @if($exercise->hasIntelligence())
                                        <a href="{{ route('exercise-intelligence.edit', $exercise->intelligence) }}" class="button edit" title="Edit Intelligence"><i class="fa-solid fa-pencil"></i></a>
                                        <form action="{{ route('exercise-intelligence.destroy', $exercise->intelligence) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete the intelligence data for {{ addslashes($exercise->title) }}?');" title="Delete Intelligence"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @else
                                        <a href="{{ route('exercise-intelligence.create', $exercise) }}" class="button create" title="Add Intelligence"><i class="fa-solid fa-plus"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top: 20px; padding: 15px; background-color: #2a2a2a; border-radius: 5px;">
                <h3 style="margin-top: 0;">Intelligence Data Summary</h3>
                @php
                    $totalExercises = $exercises->count();
                    $withIntelligence = $exercises->filter(fn($e) => $e->hasIntelligence())->count();
                    $percentage = $totalExercises > 0 ? round(($withIntelligence / $totalExercises) * 100) : 0;
                @endphp
                <p>
                    <strong>{{ $withIntelligence }}</strong> of <strong>{{ $totalExercises }}</strong> global exercises have intelligence data ({{ $percentage }}%)
                </p>
                <div style="background-color: #1a1a1a; height: 10px; border-radius: 5px; overflow: hidden;">
                    <div style="background-color: #4CAF50; height: 100%; width: {{ $percentage }}%; transition: width 0.3s ease;"></div>
                </div>
            </div>
        @endif
    </div>
@endsection