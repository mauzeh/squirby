@extends('app')

@section('content')
    <div class="date-navigation flex items-center">
        @php
            $today = \Carbon\Carbon::today();
        @endphp
        @for ($i = -3; $i <= 1; $i++)
            @php
                $date = $today->copy()->addDays($i);
                $dateString = $date->toDateString();
            @endphp
            <a href="{{ route('workout-programs.index', ['date' => $dateString]) }}" class="date-link {{ $selectedDate->toDateString() == $dateString ? 'active' : '' }}">
                {{ $date->format('D M d') }}
            </a>
        @endfor
        <label for="date_picker" class="date-pick-label ml-4 mr-2">Or Pick a Date:</label>
        <input type="date" id="date_picker" onchange="window.location.href = '{{ route('workout-programs.index') }}?date=' + this.value;" value="{{ $selectedDate->format('Y-m-d') }}">
    </div>

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

    <div class="container forms-container-wrapper">
        <div class="form-container">
            <h3>Create New Program</h3>
            <form action="{{ route('workout-programs.store') }}" method="POST" id="create-program-form">
                @csrf
                <div class="form-row">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ $selectedDate->format('Y-m-d') }}" required>
                </div>
                <div class="form-row">
                    <label for="name">Program Name (optional):</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g., Heavy Squat & Bench">
                    @error('name')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-row">
                    <label for="notes">Program Notes (optional):</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="General program notes...">{{ old('notes') }}</textarea>
                    @error('notes')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div id="exercises-container">
                    <h4>Exercises</h4>
                    <div class="exercise-entry" data-index="0">
                        <div class="form-row">
                            <label>Exercise:</label>
                            <select name="exercises[0][exercise_id]" required>
                                <option value="">Select an Exercise</option>
                                @foreach ($exercises as $exercise)
                                    <option value="{{ $exercise->id }}">{{ $exercise->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Type:</label>
                            <select name="exercises[0][exercise_type]" required>
                                <option value="">Select Type</option>
                                <option value="main">Main Lift</option>
                                <option value="accessory">Accessory</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Sets:</label>
                            <input type="number" name="exercises[0][sets]" min="1" max="20" required>
                        </div>
                        <div class="form-row">
                            <label>Reps:</label>
                            <input type="number" name="exercises[0][reps]" min="1" max="100" required>
                        </div>
                        <div class="form-row">
                            <label>Notes:</label>
                            <input type="text" name="exercises[0][notes]" placeholder="e.g., heavy, 75-80% of Day 1 weight">
                        </div>
                        <button type="button" class="button delete remove-exercise" style="display: none;">Remove Exercise</button>
                    </div>
                </div>

                <button type="button" id="add-exercise" class="button">Add Another Exercise</button>
                <button type="submit" class="button create">Create Program</button>
            </form>
        </div>
    </div>

    <div class="container">
        <h2>Workout Programs for {{ $selectedDate->format('M d, Y') }}</h2>
        @if ($workoutPrograms->isEmpty())
            <p>No programs for this day.</p>
        @else
            @foreach ($workoutPrograms as $program)
                <div class="program-container" style="margin-bottom: 2rem; border: 1px solid #555; padding: 1rem; border-radius: 8px;">
                    <div class="program-header" style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                        <div>
                            @if($program->name)
                                <h3 style="margin: 0;">{{ $program->name }}</h3>
                            @else
                                <h3 style="margin: 0;">Workout Program</h3>
                            @endif
                            @if($program->notes)
                                <p style="margin: 0.5rem 0 0 0; color: #aaa; font-style: italic;">{{ $program->notes }}</p>
                            @endif
                        </div>
                        <div class="program-actions" style="display: flex; gap: 0.5rem;">
                            <a href="{{ route('workout-programs.edit', $program->id) }}" class="button edit">
                                <i class="fa-solid fa-pencil"></i> Edit
                            </a>
                            <form action="{{ route('workout-programs.destroy', $program->id) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this program?');">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>

                    @php
                        $exercisesByType = $program->exercises->groupBy('pivot.exercise_type');
                    @endphp

                    @if($exercisesByType->has('main'))
                        <div class="exercise-group" style="margin-bottom: 1.5rem;">
                            <h4 style="color: #4CAF50; margin-bottom: 0.5rem;">Main Lifts</h4>
                            <div class="exercises-list">
                                @foreach($exercisesByType['main'] as $exercise)
                                    <div class="exercise-item" style="background: #2a2a2a; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 4px;">
                                        <div style="display: flex; justify-content: between; align-items: center;">
                                            <div>
                                                <strong>{{ $exercise->title }}</strong>
                                                <span style="color: #aaa;">- {{ $exercise->pivot->sets }}x{{ $exercise->pivot->reps }}</span>
                                                @if($exercise->pivot->notes)
                                                    <span style="color: #ffa500; font-style: italic;"> ({{ $exercise->pivot->notes }})</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($exercise->description)
                                            <div style="font-size: 0.9em; color: #ccc; margin-top: 0.25rem;">
                                                {{ $exercise->description }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($exercisesByType->has('accessory'))
                        <div class="exercise-group">
                            <h4 style="color: #FF9800; margin-bottom: 0.5rem;">Accessory Work</h4>
                            <div class="exercises-list">
                                @foreach($exercisesByType['accessory'] as $exercise)
                                    <div class="exercise-item" style="background: #2a2a2a; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 4px;">
                                        <div style="display: flex; justify-content: between; align-items: center;">
                                            <div>
                                                <strong>{{ $exercise->title }}</strong>
                                                <span style="color: #aaa;">- {{ $exercise->pivot->sets }}x{{ $exercise->pivot->reps }}</span>
                                                @if($exercise->pivot->notes)
                                                    <span style="color: #ffa500; font-style: italic;"> ({{ $exercise->pivot->notes }})</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($exercise->description)
                                            <div style="font-size: 0.9em; color: #ccc; margin-top: 0.25rem;">
                                                {{ $exercise->description }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let exerciseIndex = 1;
            
            // Add exercise functionality
            document.getElementById('add-exercise').addEventListener('click', function() {
                const container = document.getElementById('exercises-container');
                const exerciseEntry = document.querySelector('.exercise-entry').cloneNode(true);
                
                // Update the data-index and form field names
                exerciseEntry.setAttribute('data-index', exerciseIndex);
                exerciseEntry.querySelectorAll('select, input').forEach(function(field) {
                    const name = field.getAttribute('name');
                    if (name) {
                        field.setAttribute('name', name.replace(/\[\d+\]/, '[' + exerciseIndex + ']'));
                        field.value = ''; // Clear the value
                    }
                });
                
                // Show the remove button
                const removeButton = exerciseEntry.querySelector('.remove-exercise');
                removeButton.style.display = 'inline-block';
                
                container.appendChild(exerciseEntry);
                exerciseIndex++;
                
                // Update remove button visibility
                updateRemoveButtons();
            });
            
            // Remove exercise functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-exercise')) {
                    e.target.closest('.exercise-entry').remove();
                    updateRemoveButtons();
                }
            });
            
            function updateRemoveButtons() {
                const exercises = document.querySelectorAll('.exercise-entry');
                exercises.forEach(function(exercise, index) {
                    const removeButton = exercise.querySelector('.remove-exercise');
                    if (exercises.length > 1) {
                        removeButton.style.display = 'inline-block';
                    } else {
                        removeButton.style.display = 'none';
                    }
                });
            }
            
            // Initialize remove button visibility
            updateRemoveButtons();
        });
    </script>
@endsection