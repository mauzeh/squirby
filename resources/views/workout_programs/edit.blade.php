@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Workout Program</h1>

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
            <form action="{{ route('workout-programs.update', $workout_program->id) }}" method="POST" id="edit-program-form">
                @csrf
                @method('PUT')
                
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="{{ old('date', $workout_program->date->format('Y-m-d')) }}" required>
                    @error('date')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name">Program Name (optional):</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $workout_program->name) }}" placeholder="e.g., Heavy Squat & Bench">
                    @error('name')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="notes">Program Notes (optional):</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="General program notes...">{{ old('notes', $workout_program->notes) }}</textarea>
                    @error('notes')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <h2>Exercises</h2>
                <div id="exercises-container">
                    @php
                        $oldExercises = old('exercises');
                        if (!$oldExercises) {
                            $oldExercises = $workout_program->exercises->sortBy('pivot.exercise_order')->map(function($exercise) {
                                return [
                                    'exercise_id' => $exercise->id,
                                    'exercise_type' => $exercise->pivot->exercise_type,
                                    'sets' => $exercise->pivot->sets,
                                    'reps' => $exercise->pivot->reps,
                                    'notes' => $exercise->pivot->notes,
                                ];
                            })->toArray();
                        }
                    @endphp
                    
                    @foreach($oldExercises as $index => $exerciseData)
                        <div class="exercise-entry" data-index="{{ $index }}">
                            <div class="exercise-header">
                                <h3>Exercise {{ $index + 1 }}</h3>
                                @if($index > 0 || count($oldExercises) > 1)
                                    <button type="button" class="button delete remove-exercise">Remove Exercise</button>
                                @endif
                            </div>
                            
                            <div class="form-group">
                                <label for="exercises[{{ $index }}][exercise_id]">Exercise:</label>
                                <select name="exercises[{{ $index }}][exercise_id]" id="exercises_{{ $index }}_exercise_id" required>
                                    <option value="">Select an Exercise</option>
                                    @foreach ($exercises as $exercise)
                                        <option value="{{ $exercise->id }}" {{ $exerciseData['exercise_id'] == $exercise->id ? 'selected' : '' }}>
                                            {{ $exercise->title }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('exercises.' . $index . '.exercise_id')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="exercises[{{ $index }}][exercise_type]">Exercise Type:</label>
                                <select name="exercises[{{ $index }}][exercise_type]" id="exercises_{{ $index }}_exercise_type" required>
                                    <option value="">Select Type</option>
                                    <option value="main" {{ $exerciseData['exercise_type'] == 'main' ? 'selected' : '' }}>Main Lift</option>
                                    <option value="accessory" {{ $exerciseData['exercise_type'] == 'accessory' ? 'selected' : '' }}>Accessory</option>
                                </select>
                                @error('exercises.' . $index . '.exercise_type')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1; margin-right: 1rem;">
                                    <label for="exercises[{{ $index }}][sets]">Sets:</label>
                                    <input type="number" name="exercises[{{ $index }}][sets]" id="exercises_{{ $index }}_sets" 
                                           min="1" max="20" value="{{ $exerciseData['sets'] }}" required>
                                    @error('exercises.' . $index . '.sets')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group" style="flex: 1;">
                                    <label for="exercises[{{ $index }}][reps]">Reps:</label>
                                    <input type="number" name="exercises[{{ $index }}][reps]" id="exercises_{{ $index }}_reps" 
                                           min="1" max="100" value="{{ $exerciseData['reps'] }}" required>
                                    @error('exercises.' . $index . '.reps')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="exercises[{{ $index }}][notes]">Exercise Notes (optional):</label>
                                <input type="text" name="exercises[{{ $index }}][notes]" id="exercises_{{ $index }}_notes" 
                                       value="{{ $exerciseData['notes'] }}" 
                                       placeholder="e.g., heavy, 75-80% of Day 1 weight, speed work">
                                @error('exercises.' . $index . '.notes')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="exercise-order-controls">
                                <button type="button" class="button move-up" {{ $index == 0 ? 'disabled' : '' }}>Move Up</button>
                                <button type="button" class="button move-down" {{ $index == count($oldExercises) - 1 ? 'disabled' : '' }}>Move Down</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-actions">
                    <button type="button" id="add-exercise" class="button">Add Another Exercise</button>
                    <button type="submit" class="button create">Update Program</button>
                    <a href="{{ route('workout-programs.index', ['date' => $workout_program->date->format('Y-m-d')]) }}" class="button">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let exerciseIndex = {{ count($oldExercises) }};
            
            // Add exercise functionality
            document.getElementById('add-exercise').addEventListener('click', function() {
                const container = document.getElementById('exercises-container');
                const exerciseEntry = createExerciseEntry(exerciseIndex);
                container.appendChild(exerciseEntry);
                exerciseIndex++;
                updateExerciseNumbers();
                updateMoveButtons();
            });
            
            // Remove exercise functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-exercise')) {
                    const exercises = document.querySelectorAll('.exercise-entry');
                    if (exercises.length > 1) {
                        e.target.closest('.exercise-entry').remove();
                        updateExerciseNumbers();
                        updateMoveButtons();
                    }
                }
            });

            // Move up functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('move-up')) {
                    const exerciseEntry = e.target.closest('.exercise-entry');
                    const previousEntry = exerciseEntry.previousElementSibling;
                    if (previousEntry) {
                        exerciseEntry.parentNode.insertBefore(exerciseEntry, previousEntry);
                        updateExerciseNumbers();
                        updateMoveButtons();
                    }
                }
            });

            // Move down functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('move-down')) {
                    const exerciseEntry = e.target.closest('.exercise-entry');
                    const nextEntry = exerciseEntry.nextElementSibling;
                    if (nextEntry) {
                        exerciseEntry.parentNode.insertBefore(nextEntry, exerciseEntry);
                        updateExerciseNumbers();
                        updateMoveButtons();
                    }
                }
            });
            
            function createExerciseEntry(index) {
                const div = document.createElement('div');
                div.className = 'exercise-entry';
                div.setAttribute('data-index', index);
                div.innerHTML = `
                    <div class="exercise-header">
                        <h3>Exercise ${index + 1}</h3>
                        <button type="button" class="button delete remove-exercise">Remove Exercise</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="exercises[${index}][exercise_id]">Exercise:</label>
                        <select name="exercises[${index}][exercise_id]" id="exercises_${index}_exercise_id" required>
                            <option value="">Select an Exercise</option>
                            @foreach ($exercises as $exercise)
                                <option value="{{ $exercise->id }}">{{ $exercise->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="exercises[${index}][exercise_type]">Exercise Type:</label>
                        <select name="exercises[${index}][exercise_type]" id="exercises_${index}_exercise_type" required>
                            <option value="">Select Type</option>
                            <option value="main">Main Lift</option>
                            <option value="accessory">Accessory</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1; margin-right: 1rem;">
                            <label for="exercises[${index}][sets]">Sets:</label>
                            <input type="number" name="exercises[${index}][sets]" id="exercises_${index}_sets" 
                                   min="1" max="20" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="exercises[${index}][reps]">Reps:</label>
                            <input type="number" name="exercises[${index}][reps]" id="exercises_${index}_reps" 
                                   min="1" max="100" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exercises[${index}][notes]">Exercise Notes (optional):</label>
                        <input type="text" name="exercises[${index}][notes]" id="exercises_${index}_notes" 
                               placeholder="e.g., heavy, 75-80% of Day 1 weight, speed work">
                    </div>

                    <div class="exercise-order-controls">
                        <button type="button" class="button move-up">Move Up</button>
                        <button type="button" class="button move-down">Move Down</button>
                    </div>
                `;
                return div;
            }
            
            function updateExerciseNumbers() {
                const exercises = document.querySelectorAll('.exercise-entry');
                exercises.forEach(function(exercise, index) {
                    exercise.setAttribute('data-index', index);
                    exercise.querySelector('h3').textContent = `Exercise ${index + 1}`;
                    
                    // Update form field names and IDs
                    exercise.querySelectorAll('select, input').forEach(function(field) {
                        const name = field.getAttribute('name');
                        const id = field.getAttribute('id');
                        if (name) {
                            field.setAttribute('name', name.replace(/\[\d+\]/, `[${index}]`));
                        }
                        if (id) {
                            field.setAttribute('id', id.replace(/_\d+_/, `_${index}_`));
                        }
                    });
                    
                    // Update labels
                    exercise.querySelectorAll('label').forEach(function(label) {
                        const forAttr = label.getAttribute('for');
                        if (forAttr) {
                            label.setAttribute('for', forAttr.replace(/_\d+_/, `_${index}_`));
                        }
                    });
                });
            }

            function updateMoveButtons() {
                const exercises = document.querySelectorAll('.exercise-entry');
                exercises.forEach(function(exercise, index) {
                    const moveUpBtn = exercise.querySelector('.move-up');
                    const moveDownBtn = exercise.querySelector('.move-down');
                    
                    moveUpBtn.disabled = index === 0;
                    moveDownBtn.disabled = index === exercises.length - 1;
                });
            }
            
            // Initialize move buttons
            updateMoveButtons();
        });
    </script>

    <style>
        .exercise-entry {
            border: 1px solid #555;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            background: #2a2a2a;
        }

        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .exercise-header h3 {
            margin: 0;
            color: #4CAF50;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .exercise-order-controls {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .form-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }

        .button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
@endsection