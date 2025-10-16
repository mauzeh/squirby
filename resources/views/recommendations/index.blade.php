@extends('app')

@section('content')
    <div class="container">
        <h1>Exercise Recommendations</h1>
        <p>Based on your activity over the last 31 days, here are personalized exercise suggestions to help balance your training.</p>
        
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

        <!-- Filter Form -->
        <div class="form-container">
            <h3>Filter Recommendations</h3>
            <form method="GET" action="{{ route('recommendations.index') }}" id="filter-form">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="movement_archetype" style="display: block; margin-bottom: 5px; color: #f2f2f2;">Movement Pattern:</label>
                        <select name="movement_archetype" id="movement_archetype" style="width: 100%; padding: 8px; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">
                            <option value="">All Patterns</option>
                            @foreach($movementArchetypes as $archetype)
                                <option value="{{ $archetype }}" {{ $movementArchetype === $archetype ? 'selected' : '' }}>
                                    {{ ucfirst($archetype) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="difficulty_level" style="display: block; margin-bottom: 5px; color: #f2f2f2;">Difficulty Level:</label>
                        <select name="difficulty_level" id="difficulty_level" style="width: 100%; padding: 8px; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">
                            <option value="">All Levels</option>
                            @foreach($difficultyLevels as $level)
                                <option value="{{ $level }}" {{ $difficultyLevel == $level ? 'selected' : '' }}>
                                    Level {{ $level }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="count" style="display: block; margin-bottom: 5px; color: #f2f2f2;">Number of Recommendations:</label>
                        <select name="count" id="count" style="width: 100%; padding: 8px; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">
                            <option value="5" {{ $count == 5 ? 'selected' : '' }}>5</option>
                            <option value="10" {{ $count == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ $count == 15 ? 'selected' : '' }}>15</option>
                            <option value="20" {{ $count == 20 ? 'selected' : '' }}>20</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="button">Apply Filters</button>
                    <a href="{{ route('recommendations.index') }}" class="button" style="background-color: #666;">Clear Filters</a>
                    <button type="button" id="refresh-recommendations" class="button" style="background-color: #4CAF50;">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </form>
        </div>

        <!-- Recommendations Display -->
        <div id="recommendations-container">
            @if (empty($recommendations))
                <div class="container" style="text-align: center; padding: 40px; background-color: #2a2a2a; border-radius: 8px;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #666; margin-bottom: 20px;"></i>
                    <h3 style="color: #f2f2f2; margin-bottom: 10px;">No Recommendations Available</h3>
                    <p style="color: #ccc; margin-bottom: 20px;">
                        We couldn't generate recommendations at this time. This might be because:
                    </p>
                    <ul style="color: #ccc; text-align: left; max-width: 400px; margin: 0 auto 20px;">
                        <li>No exercises have intelligence data configured</li>
                        <li>Your current filters are too restrictive</li>
                        <li>You haven't logged any workouts recently</li>
                    </ul>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="{{ route('exercises.index') }}" class="button">Browse Exercises</a>
                        <a href="{{ route('lift-logs.index') }}" class="button">Log a Workout</a>
                    </div>
                </div>
            @else
                <div class="recommendations-grid">
                    @foreach($recommendations as $recommendation)
                        <div class="recommendation-card">
                            <div class="recommendation-header">
                                <h3 class="exercise-title">
                                    <a href="{{ route('exercises.show-logs', $recommendation['exercise']) }}" class="text-white">
                                        {{ $recommendation['exercise']->title }}
                                    </a>
                                </h3>
                                <div class="recommendation-score">
                                    Score: {{ number_format($recommendation['score'], 1) }}
                                </div>
                            </div>
                            
                            <div class="exercise-details">
                                @if($recommendation['exercise']->description)
                                    <p class="exercise-description">{{ $recommendation['exercise']->description }}</p>
                                @endif
                                
                                <div class="exercise-metadata">
                                    <div class="metadata-row">
                                        <span class="metadata-label">Movement Pattern:</span>
                                        <span class="metadata-value archetype-{{ $recommendation['intelligence']->movement_archetype }}">
                                            {{ ucfirst($recommendation['intelligence']->movement_archetype) }}
                                        </span>
                                    </div>
                                    
                                    <div class="metadata-row">
                                        <span class="metadata-label">Difficulty:</span>
                                        <span class="metadata-value">
                                            <div class="difficulty-stars">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="fas fa-star {{ $i <= $recommendation['intelligence']->difficulty_level ? 'active' : '' }}"></i>
                                                @endfor
                                            </div>
                                            Level {{ $recommendation['intelligence']->difficulty_level }}
                                        </span>
                                    </div>
                                    
                                    <div class="metadata-row">
                                        <span class="metadata-label">Primary Focus:</span>
                                        <span class="metadata-value">{{ str_replace('_', ' ', ucwords($recommendation['intelligence']->primary_mover, '_')) }}</span>
                                    </div>
                                    
                                    <div class="metadata-row">
                                        <span class="metadata-label">Exercise Type:</span>
                                        <span class="metadata-value">
                                            @if($recommendation['exercise']->is_bodyweight)
                                                <i class="fas fa-user"></i> Bodyweight
                                            @elseif($recommendation['exercise']->band_type)
                                                <i class="fas fa-circle"></i> {{ ucfirst($recommendation['exercise']->band_type) }} Band
                                            @else
                                                <i class="fas fa-dumbbell"></i> Weighted
                                            @endif
                                        </span>
                                    </div>
                                    
                                    <div class="metadata-row">
                                        <span class="metadata-label">Recovery Time:</span>
                                        <span class="metadata-value">{{ $recommendation['intelligence']->recovery_hours }} hours</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="recommendation-reasoning">
                                <h4>Why This Exercise?</h4>
                                <ul class="reasoning-list">
                                    @foreach($recommendation['reasoning'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <div class="muscle-involvement">
                                <h4>Muscles Targeted</h4>
                                <div class="muscle-groups">
                                    @if(isset($recommendation['intelligence']->muscle_data['muscles']))
                                        @php
                                            $musclesByRole = collect($recommendation['intelligence']->muscle_data['muscles'])->groupBy('role');
                                        @endphp
                                        
                                        @foreach(['primary_mover', 'synergist', 'stabilizer'] as $role)
                                            @if(isset($musclesByRole[$role]))
                                                <div class="muscle-role-group">
                                                    <span class="muscle-role-label role-{{ str_replace('_', '-', $role) }}">
                                                        {{ ucwords(str_replace('_', ' ', $role)) }}:
                                                    </span>
                                                    <div class="muscle-list">
                                                        @foreach($musclesByRole[$role] as $muscle)
                                                            <span class="muscle-tag contraction-{{ $muscle['contraction_type'] }}">
                                                                {{ str_replace('_', ' ', ucwords($muscle['name'], '_')) }}
                                                                <small>({{ $muscle['contraction_type'] }})</small>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            
                            <div class="recommendation-actions">
                                <a href="{{ route('exercises.show-logs', $recommendation['exercise']) }}" class="button">
                                    <i class="fas fa-chart-line"></i> View Exercise History
                                </a>
                                @if(isset($todayProgramExercises[$recommendation['exercise']->id]))
                                    <form action="{{ route('programs.destroy', $todayProgramExercises[$recommendation['exercise']->id]) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="redirect_to" value="recommendations">
                                        @foreach(request()->query() as $key => $value)
                                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                        @endforeach
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to remove this exercise from today\'s program?');" title="Remove from Today">
                                            <i class="fas fa-minus"></i> Remove from Today
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('programs.quick-add', array_merge(['exercise' => $recommendation['exercise']->id, 'date' => \Carbon\Carbon::today()->format('Y-m-d'), 'redirect_to' => 'recommendations'], request()->query())) }}" class="button" style="background-color: #4CAF50;">
                                        <i class="fas fa-plus"></i> Add to Today
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .recommendation-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #444;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .recommendation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .recommendation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
        }
        
        .exercise-title {
            margin: 0;
            font-size: 1.2em;
            flex: 1;
        }
        
        .exercise-title a {
            text-decoration: none;
            color: #f2f2f2;
        }
        
        .exercise-title a:hover {
            color: #4CAF50;
        }
        
        .recommendation-score {
            background-color: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .exercise-description {
            color: #ccc;
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .exercise-metadata {
            margin-bottom: 15px;
        }
        
        .metadata-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        
        .metadata-label {
            color: #aaa;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .metadata-value {
            color: #f2f2f2;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .difficulty-stars {
            display: flex;
            gap: 2px;
        }
        
        .difficulty-stars .fa-star {
            color: #666;
            font-size: 0.8em;
        }
        
        .difficulty-stars .fa-star.active {
            color: #FFD700;
        }
        
        .archetype-push { color: #FF6B6B; }
        .archetype-pull { color: #4ECDC4; }
        .archetype-squat { color: #45B7D1; }
        .archetype-hinge { color: #96CEB4; }
        .archetype-carry { color: #FFEAA7; }
        .archetype-core { color: #DDA0DD; }
        
        .recommendation-reasoning {
            margin-bottom: 15px;
        }
        
        .recommendation-reasoning h4 {
            color: #f2f2f2;
            margin: 0 0 8px 0;
            font-size: 1em;
        }
        
        .reasoning-list {
            margin: 0;
            padding-left: 20px;
            color: #ccc;
        }
        
        .reasoning-list li {
            margin-bottom: 4px;
            font-size: 0.9em;
        }
        
        .muscle-involvement {
            margin-bottom: 20px;
        }
        
        .muscle-involvement h4 {
            color: #f2f2f2;
            margin: 0 0 10px 0;
            font-size: 1em;
        }
        
        .muscle-role-group {
            margin-bottom: 10px;
        }
        
        .muscle-role-label {
            display: block;
            font-size: 0.85em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .role-primary-mover { color: #FF6B6B; }
        .role-synergist { color: #4ECDC4; }
        .role-stabilizer { color: #96CEB4; }
        
        .muscle-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .muscle-tag {
            background-color: #3a3a3a;
            color: #f2f2f2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            border: 1px solid #555;
        }
        
        .contraction-isotonic {
            border-color: #4CAF50;
        }
        
        .contraction-isometric {
            border-color: #FF9800;
        }
        
        .muscle-tag small {
            color: #aaa;
            font-size: 0.85em;
        }
        
        .recommendation-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .recommendation-actions .button {
            flex: 1;
            min-width: 150px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        @media (max-width: 768px) {
            .recommendations-grid {
                grid-template-columns: 1fr;
            }
            
            .recommendation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .recommendation-score {
                margin-left: 0;
            }
            
            .metadata-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
            
            .recommendation-actions {
                flex-direction: column;
            }
            
            .recommendation-actions .button {
                min-width: auto;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filters change
            const filterForm = document.getElementById('filter-form');
            const filterInputs = filterForm.querySelectorAll('select');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
            
            // Refresh recommendations button
            const refreshButton = document.getElementById('refresh-recommendations');
            refreshButton.addEventListener('click', function() {
                refreshButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                refreshButton.disabled = true;
                
                // Get current form data
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData);
                
                // Make AJAX request to API endpoint
                fetch(`{{ route('recommendations.api') }}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show updated recommendations
                        window.location.reload();
                    } else {
                        alert('Failed to refresh recommendations: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to refresh recommendations. Please try again.');
                })
                .finally(() => {
                    refreshButton.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                    refreshButton.disabled = false;
                });
            });
        });
    </script>
@endsection