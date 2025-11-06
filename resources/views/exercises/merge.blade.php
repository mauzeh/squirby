@extends('app')

@section('content')
    <div class="container">
        <h1>Merge Exercise</h1>
        
        @if (session('success'))
            <div class="container success-message-box">
                {!! session('success') !!}
            </div>
        @endif
        
        @if ($errors->any())
            <div class="container error-message-box">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        <div class="form-container">
            <h3>Source Exercise</h3>
            <div style="background-color: #3a3a3a; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #f2f2f2;">{{ $exercise->title }}</h4>
                @if($exercise->description)
                    <p style="color: #ccc; margin: 5px 0;">{{ $exercise->description }}</p>
                @endif
                <div style="display: flex; gap: 15px; margin: 10px 0; font-size: 0.9em; color: #aaa;">
                    <span>{{ ucfirst(str_replace('_', ' ', $exercise->exercise_type)) }}</span>
                    <span>Owner: {{ $exercise->user->name }}</span>
                </div>
                <div style="display: flex; gap: 20px; margin: 10px 0; font-size: 0.9em; color: #ddd;">
                    <span><strong>{{ $sourceStats['lift_logs_count'] }}</strong> lift logs</span>
                    <span><strong>{{ $sourceStats['program_entries_count'] }}</strong> program entries</span>
                    <span><strong>{{ $sourceStats['users_count'] }}</strong> users</span>
                    @if($sourceStats['has_intelligence'])
                        <span style="color: #4CAF50;"><i class="fas fa-brain"></i> Has AI insights</span>
                    @endif
                </div>
            </div>

            @if($targetsWithInfo->isEmpty())
                <div class="error-message-box">
                    <p>No compatible global exercises found for merging. The source exercise must have compatible global exercises with the same bodyweight setting and compatible band types.</p>
                </div>
                <a href="{{ route('exercises.index') }}" class="button back-button">Back to Exercises</a>
            @else
                <form action="{{ route('exercises.merge', $exercise) }}" method="POST" onsubmit="return confirm('Are you sure you want to merge this exercise? This action cannot be undone and will transfer all workout data to the selected target exercise.');">
                    @csrf
                    
                    <h3>Select Target Exercise</h3>
                    <p style="color: #ccc; margin-bottom: 20px;">Choose which global exercise to merge the data into:</p>
                    
                    @foreach($targetsWithInfo as $targetInfo)
                        @php
                            $target = $targetInfo['exercise'];
                            $stats = $targetInfo['stats'];
                            $compatibility = $targetInfo['compatibility'];
                        @endphp
                        
                        <div style="background-color: #2a2a2a; border: 2px solid #444; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;">
                            <label style="display: flex; align-items: flex-start; cursor: pointer; width: 100%;">
                                <input type="radio" name="target_exercise_id" value="{{ $target->id }}" required style="margin-right: 15px; margin-top: 5px; transform: scale(1.2);">
                                
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <h4 style="margin: 0; color: #f2f2f2;">{{ $target->title }}</h4>
                                        <span class="badge" style="background-color: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7em;">Global</span>
                                    </div>
                                    
                                    @if($target->description)
                                        <p style="color: #ccc; margin: 5px 0; font-size: 0.9em;">{{ $target->description }}</p>
                                    @endif
                                    
                                    <div style="display: flex; gap: 15px; margin: 8px 0; font-size: 0.85em; color: #aaa;">
                                        <span>{{ ucfirst(str_replace('_', ' ', $target->exercise_type)) }}</span>
                                    </div>
                                    
                                    <div style="display: flex; gap: 20px; margin: 8px 0; font-size: 0.85em; color: #ddd;">
                                        <span><strong>{{ $stats['lift_logs_count'] }}</strong> lift logs</span>
                                        <span><strong>{{ $stats['program_entries_count'] }}</strong> program entries</span>
                                        <span><strong>{{ $stats['users_count'] }}</strong> users</span>
                                        @if($stats['has_intelligence'])
                                            <span style="color: #4CAF50;"><i class="fas fa-brain"></i> Has AI insights</span>
                                        @endif
                                    </div>
                                    
                                    @if(!empty($compatibility['warnings']))
                                        <div style="background-color: #856404; border: 1px solid #ffc107; border-radius: 4px; padding: 8px; margin-top: 10px;">
                                            <div style="color: #fff3cd; font-size: 0.85em;">
                                                <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>
                                                <strong>Warning:</strong>
                                                @foreach($compatibility['warnings'] as $warning)
                                                    <div style="margin-left: 20px;">{{ $warning }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </label>
                        </div>
                    @endforeach
                    
                    <div style="margin-top: 30px; display: flex; gap: 10px;">
                        <button type="submit" class="button" style="background-color: #FF9800; color: white;">
                            <i class="fas fa-code-branch" style="margin-right: 5px;"></i>
                            Merge Exercise
                        </button>
                        <a href="{{ route('exercises.index') }}" class="button back-button">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script>
        // Add visual feedback when radio buttons are selected
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="target_exercise_id"]');
            
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    // Remove selected styling from all containers
                    document.querySelectorAll('div[style*="border: 2px solid"]').forEach(function(container) {
                        container.style.borderColor = '#444';
                    });
                    
                    // Add selected styling to the chosen container
                    if (this.checked) {
                        const container = this.closest('div[style*="border: 2px solid"]');
                        if (container) {
                            container.style.borderColor = '#007bff';
                        }
                    }
                });
            });
        });
    </script>
@endsection