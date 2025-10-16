@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Intelligence Data</h1>
        <h2 style="color: #aaa; margin-top: 0;">{{ $intelligence->exercise->title }}</h2>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('exercise-intelligence.update', $intelligence) }}" method="POST" id="intelligence-form">
            @csrf
            @method('PUT')
            
            <!-- Muscle Data Section -->
            <div class="form-container">
                <h3>Muscle Involvement</h3>
                <p style="color: #aaa; margin-bottom: 20px;">Define which muscles are involved in this exercise and how they contribute to the movement.</p>
                
                <div id="muscle-list">
                    <!-- Muscles will be added here dynamically -->
                </div>
                
                <button type="button" id="add-muscle-btn" class="button" style="background-color: #4CAF50;">
                    <i class="fa-solid fa-plus"></i> Add Muscle
                </button>
            </div>

            <!-- Primary Movement Data -->
            <div class="form-container">
                <h3>Primary Movement Characteristics</h3>
                
                <div class="form-group">
                    <label for="primary_mover">Primary Mover Muscle:</label>
                    <select name="primary_mover" id="primary_mover" class="form-control" required>
                        <option value="">Select the main muscle driving this movement...</option>
                    </select>
                    <small style="color: #aaa;">The muscle that generates the most force and is primarily responsible for the movement.</small>
                </div>

                <div class="form-group">
                    <label for="largest_muscle">Largest Muscle Involved:</label>
                    <select name="largest_muscle" id="largest_muscle" class="form-control" required>
                        <option value="">Select the largest muscle involved...</option>
                    </select>
                    <small style="color: #aaa;">The muscle with the greatest mass that participates in this exercise.</small>
                </div>
            </div>

            <!-- Movement Classification -->
            <div class="form-container">
                <h3>Movement Classification</h3>
                
                <div class="form-group">
                    <label for="movement_archetype">Movement Archetype:</label>
                    <select name="movement_archetype" id="movement_archetype" class="form-control" required>
                        <option value="">Select movement pattern...</option>
                        <option value="push" {{ old('movement_archetype', $intelligence->movement_archetype) == 'push' ? 'selected' : '' }}>Push - Pushing movements (bench press, overhead press, push-ups)</option>
                        <option value="pull" {{ old('movement_archetype', $intelligence->movement_archetype) == 'pull' ? 'selected' : '' }}>Pull - Pulling movements (rows, pull-ups, deadlifts)</option>
                        <option value="squat" {{ old('movement_archetype', $intelligence->movement_archetype) == 'squat' ? 'selected' : '' }}>Squat - Knee-dominant lower body (squats, lunges)</option>
                        <option value="hinge" {{ old('movement_archetype', $intelligence->movement_archetype) == 'hinge' ? 'selected' : '' }}>Hinge - Hip-dominant movements (deadlifts, hip thrusts)</option>
                        <option value="carry" {{ old('movement_archetype', $intelligence->movement_archetype) == 'carry' ? 'selected' : '' }}>Carry - Loaded carries and holds (farmer's walks)</option>
                        <option value="core" {{ old('movement_archetype', $intelligence->movement_archetype) == 'core' ? 'selected' : '' }}>Core - Core-specific movements (planks, crunches)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Exercise Category:</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">Select category...</option>
                        <option value="strength" {{ old('category', $intelligence->category) == 'strength' ? 'selected' : '' }}>Strength - Traditional resistance training</option>
                        <option value="cardio" {{ old('category', $intelligence->category) == 'cardio' ? 'selected' : '' }}>Cardio - Cardiovascular exercises</option>
                        <option value="mobility" {{ old('category', $intelligence->category) == 'mobility' ? 'selected' : '' }}>Mobility - Flexibility and mobility work</option>
                        <option value="plyometric" {{ old('category', $intelligence->category) == 'plyometric' ? 'selected' : '' }}>Plyometric - Explosive, jumping movements</option>
                        <option value="flexibility" {{ old('category', $intelligence->category) == 'flexibility' ? 'selected' : '' }}>Flexibility - Static stretching exercises</option>
                    </select>
                </div>
            </div>

            <!-- Training Parameters -->
            <div class="form-container">
                <h3>Training Parameters</h3>
                
                <div class="form-group">
                    <label for="difficulty_level">Difficulty Level:</label>
                    <select name="difficulty_level" id="difficulty_level" class="form-control" required>
                        <option value="">Select difficulty...</option>
                        <option value="1" {{ old('difficulty_level', $intelligence->difficulty_level) == '1' ? 'selected' : '' }}>1 - Beginner (very easy to learn and perform)</option>
                        <option value="2" {{ old('difficulty_level', $intelligence->difficulty_level) == '2' ? 'selected' : '' }}>2 - Novice (easy with basic instruction)</option>
                        <option value="3" {{ old('difficulty_level', $intelligence->difficulty_level) == '3' ? 'selected' : '' }}>3 - Intermediate (moderate complexity)</option>
                        <option value="4" {{ old('difficulty_level', $intelligence->difficulty_level) == '4' ? 'selected' : '' }}>4 - Advanced (requires good technique)</option>
                        <option value="5" {{ old('difficulty_level', $intelligence->difficulty_level) == '5' ? 'selected' : '' }}>5 - Expert (very complex, high skill required)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="recovery_hours">Recovery Time (hours):</label>
                    <input type="number" name="recovery_hours" id="recovery_hours" class="form-control" 
                           value="{{ old('recovery_hours', $intelligence->recovery_hours) }}" min="0" max="168" required>
                    <small style="color: #aaa;">Recommended time between sessions targeting the same muscle groups (0-168 hours).</small>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="button">Update Intelligence Data</button>
                <a href="{{ route('exercise-intelligence.index') }}" class="button" style="background-color: #666;">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Available muscles organized by category
        const muscleOptions = {
            'Upper Body - Chest': [
                { value: 'pectoralis_major', label: 'Pectoralis Major' },
                { value: 'pectoralis_minor', label: 'Pectoralis Minor' }
            ],
            'Upper Body - Back': [
                { value: 'latissimus_dorsi', label: 'Latissimus Dorsi' },
                { value: 'rhomboids', label: 'Rhomboids' },
                { value: 'middle_trapezius', label: 'Middle Trapezius' },
                { value: 'lower_trapezius', label: 'Lower Trapezius' },
                { value: 'upper_trapezius', label: 'Upper Trapezius' }
            ],
            'Upper Body - Shoulders': [
                { value: 'anterior_deltoid', label: 'Anterior Deltoid' },
                { value: 'medial_deltoid', label: 'Medial Deltoid' },
                { value: 'posterior_deltoid', label: 'Posterior Deltoid' }
            ],
            'Upper Body - Arms': [
                { value: 'biceps_brachii', label: 'Biceps Brachii' },
                { value: 'triceps_brachii', label: 'Triceps Brachii' },
                { value: 'brachialis', label: 'Brachialis' },
                { value: 'brachioradialis', label: 'Brachioradialis' }
            ],
            'Lower Body - Quadriceps': [
                { value: 'rectus_femoris', label: 'Rectus Femoris' },
                { value: 'vastus_lateralis', label: 'Vastus Lateralis' },
                { value: 'vastus_medialis', label: 'Vastus Medialis' },
                { value: 'vastus_intermedius', label: 'Vastus Intermedius' }
            ],
            'Lower Body - Hamstrings': [
                { value: 'biceps_femoris', label: 'Biceps Femoris' },
                { value: 'semitendinosus', label: 'Semitendinosus' },
                { value: 'semimembranosus', label: 'Semimembranosus' }
            ],
            'Lower Body - Glutes': [
                { value: 'gluteus_maximus', label: 'Gluteus Maximus' },
                { value: 'gluteus_medius', label: 'Gluteus Medius' },
                { value: 'gluteus_minimus', label: 'Gluteus Minimus' }
            ],
            'Lower Body - Calves': [
                { value: 'gastrocnemius', label: 'Gastrocnemius' },
                { value: 'soleus', label: 'Soleus' }
            ],
            'Core - Abdominals': [
                { value: 'rectus_abdominis', label: 'Rectus Abdominis' },
                { value: 'external_obliques', label: 'External Obliques' },
                { value: 'internal_obliques', label: 'Internal Obliques' },
                { value: 'transverse_abdominis', label: 'Transverse Abdominis' }
            ],
            'Core - Lower Back': [
                { value: 'erector_spinae', label: 'Erector Spinae' },
                { value: 'multifidus', label: 'Multifidus' }
            ]
        };

        let muscleCount = 0;

        function createMuscleOptions() {
            let options = '<option value="">Select muscle...</option>';
            for (const [category, muscles] of Object.entries(muscleOptions)) {
                options += `<optgroup label="${category}">`;
                muscles.forEach(muscle => {
                    options += `<option value="${muscle.value}">${muscle.label}</option>`;
                });
                options += '</optgroup>';
            }
            return options;
        }

        function addMuscleRow(name = '', role = '', contractionType = '') {
            const muscleList = document.getElementById('muscle-list');
            const muscleRow = document.createElement('div');
            muscleRow.className = 'muscle-row';
            muscleRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 15px; align-items: flex-start; flex-wrap: wrap;';
            
            muscleRow.innerHTML = `
                <div style="flex: 2; min-width: 200px;">
                    <label>Muscle:</label>
                    <select name="muscle_data[muscles][${muscleCount}][name]" class="form-control muscle-select" required>
                        ${createMuscleOptions()}
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label>Role:</label>
                    <select name="muscle_data[muscles][${muscleCount}][role]" class="form-control muscle-role" required>
                        <option value="">Select role...</option>
                        <option value="primary_mover">Primary Mover</option>
                        <option value="synergist">Synergist</option>
                        <option value="stabilizer">Stabilizer</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label>Contraction:</label>
                    <select name="muscle_data[muscles][${muscleCount}][contraction_type]" class="form-control" required>
                        <option value="">Select type...</option>
                        <option value="isotonic">Isotonic (muscle changes length)</option>
                        <option value="isometric">Isometric (muscle maintains length)</option>
                    </select>
                </div>
                <div style="flex: 0 0 auto; padding-top: 25px;">
                    <button type="button" class="button delete remove-muscle-btn" style="padding: 8px 12px;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;

            // Set values if provided
            if (name) muscleRow.querySelector('.muscle-select').value = name;
            if (role) muscleRow.querySelector('.muscle-role').value = role;
            if (contractionType) muscleRow.querySelector('select[name*="contraction_type"]').value = contractionType;

            muscleList.appendChild(muscleRow);
            
            // Add event listeners
            muscleRow.querySelector('.remove-muscle-btn').addEventListener('click', function() {
                muscleRow.remove();
                updatePrimaryMoverOptions();
                updateLargestMuscleOptions();
            });

            muscleRow.querySelector('.muscle-select').addEventListener('change', function() {
                updatePrimaryMoverOptions();
                updateLargestMuscleOptions();
            });

            muscleRow.querySelector('.muscle-role').addEventListener('change', function() {
                updatePrimaryMoverOptions();
            });

            muscleCount++;
            updatePrimaryMoverOptions();
            updateLargestMuscleOptions();
        }

        function updatePrimaryMoverOptions() {
            const primaryMoverSelect = document.getElementById('primary_mover');
            const currentValue = primaryMoverSelect.value;
            
            // Get all muscles with primary_mover role
            const primaryMovers = Array.from(document.querySelectorAll('.muscle-row')).filter(row => {
                const roleSelect = row.querySelector('.muscle-role');
                return roleSelect && roleSelect.value === 'primary_mover';
            }).map(row => {
                const muscleSelect = row.querySelector('.muscle-select');
                return {
                    value: muscleSelect.value,
                    label: muscleSelect.options[muscleSelect.selectedIndex].text
                };
            }).filter(muscle => muscle.value);

            // Update primary mover options
            primaryMoverSelect.innerHTML = '<option value="">Select the main muscle driving this movement...</option>';
            primaryMovers.forEach(muscle => {
                const option = document.createElement('option');
                option.value = muscle.value;
                option.textContent = muscle.label;
                primaryMoverSelect.appendChild(option);
            });

            // Restore selection if still valid
            if (currentValue && primaryMovers.some(m => m.value === currentValue)) {
                primaryMoverSelect.value = currentValue;
            }
        }

        function updateLargestMuscleOptions() {
            const largestMuscleSelect = document.getElementById('largest_muscle');
            const currentValue = largestMuscleSelect.value;
            
            // Get all selected muscles
            const allMuscles = Array.from(document.querySelectorAll('.muscle-row')).map(row => {
                const muscleSelect = row.querySelector('.muscle-select');
                return {
                    value: muscleSelect.value,
                    label: muscleSelect.options[muscleSelect.selectedIndex].text
                };
            }).filter(muscle => muscle.value);

            // Update largest muscle options
            largestMuscleSelect.innerHTML = '<option value="">Select the largest muscle involved...</option>';
            allMuscles.forEach(muscle => {
                const option = document.createElement('option');
                option.value = muscle.value;
                option.textContent = muscle.label;
                largestMuscleSelect.appendChild(option);
            });

            // Restore selection if still valid
            if (currentValue && allMuscles.some(m => m.value === currentValue)) {
                largestMuscleSelect.value = currentValue;
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add muscle button
            document.getElementById('add-muscle-btn').addEventListener('click', function() {
                addMuscleRow();
            });

            // Load existing muscle data
            const existingMuscles = @json(old('muscle_data.muscles', $intelligence->muscle_data['muscles'] ?? []));
            
            if (existingMuscles.length > 0) {
                existingMuscles.forEach((muscle, index) => {
                    addMuscleRow(muscle.name || '', muscle.role || '', muscle.contraction_type || '');
                });
            } else {
                // Add initial empty row if no existing data
                addMuscleRow();
            }

            // Set primary mover and largest muscle values after all muscles are loaded
            setTimeout(() => {
                document.getElementById('primary_mover').value = '{{ old('primary_mover', $intelligence->primary_mover) }}';
                document.getElementById('largest_muscle').value = '{{ old('largest_muscle', $intelligence->largest_muscle) }}';
            }, 100);
        });
    </script>
@endsection