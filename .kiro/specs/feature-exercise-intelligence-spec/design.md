# Design Document

## Overview

The Exercise Intelligence System adds smart workout recommendations by creating a separate intelligence data layer that tracks detailed muscle involvement, movement patterns, and exercise metadata. The system analyzes user activity over the last 31 days to recommend balanced workouts while keeping the existing Exercise model and TSV functionality completely unchanged.

## Architecture

### High-Level Architecture

```
┌─────────────────┐    ┌──────────────────────┐    ┌─────────────────────┐
│   Exercise      │    │  ExerciseIntelligence│    │  Recommendation     │
│   Model         │◄───┤  Model               │───►│  Engine             │
│  (unchanged)    │    │  (new)               │    │  (new)              │
└─────────────────┘    └──────────────────────┘    └─────────────────────┘
         │                        │                          │
         │                        │                          │
         ▼                        ▼                          ▼
┌─────────────────┐    ┌──────────────────────┐    ┌─────────────────────┐
│   TSV Import/   │    │  Intelligence        │    │  User Activity      │
│   Export        │    │  Management UI       │    │  Analysis           │
│  (unchanged)    │    │  (new)               │    │  (new)              │
└─────────────────┘    └──────────────────────┘    └─────────────────────┘
```

### Database Design

#### Updated Table: exercises
```sql
-- Add canonical_name column to existing exercises table
ALTER TABLE exercises ADD COLUMN canonical_name VARCHAR(255) NULL;
```

#### New Table: exercise_intelligence

```sql
CREATE TABLE exercise_intelligence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id BIGINT UNSIGNED NOT NULL,
    canonical_name VARCHAR(255) NULL,
    muscle_data JSON NOT NULL,
    primary_mover VARCHAR(100) NOT NULL,
    largest_muscle VARCHAR(100) NOT NULL,
    movement_archetype ENUM('push', 'pull', 'squat', 'hinge', 'carry', 'core') NOT NULL,
    category ENUM('strength', 'cardio', 'mobility', 'plyometric', 'flexibility') NOT NULL,
    difficulty_level TINYINT UNSIGNED NOT NULL CHECK (difficulty_level BETWEEN 1 AND 5),
    recovery_hours INT UNSIGNED NOT NULL DEFAULT 48,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exercise_intelligence (exercise_id),
    INDEX idx_movement_archetype (movement_archetype),
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_canonical_name (canonical_name)
);
```

#### Muscle Data JSON Structure

```json
{
  "muscles": [
    {
      "name": "pectoralis_major",
      "role": "primary_mover",
      "contraction_type": "isotonic"
    },
    {
      "name": "anterior_deltoid",
      "role": "synergist", 
      "contraction_type": "isotonic"
    },
    {
      "name": "triceps_brachii",
      "role": "synergist",
      "contraction_type": "isotonic"
    },
    {
      "name": "core_stabilizers",
      "role": "stabilizer",
      "contraction_type": "isometric"
    }
  ]
}
```



## Components and Interfaces

### 1. ExerciseIntelligence Model

```php
class ExerciseIntelligence extends Model
{
    protected $fillable = [
        'exercise_id',
        'canonical_name',
        'muscle_data',
        'primary_mover',
        'largest_muscle', 
        'movement_archetype',
        'category',
        'difficulty_level',
        'recovery_hours'
    ];
    
    protected $casts = [
        'muscle_data' => 'array',
        'difficulty_level' => 'integer',
        'recovery_hours' => 'integer'
    ];
    
    // Relationships
    public function exercise(): BelongsTo;
    
    // Scopes
    public function scopeForGlobalExercises($query);
    public function scopeByMovementArchetype($query, string $archetype);
    public function scopeByCategory($query, string $category);
    
    // Helper methods
    public function getPrimaryMoverMuscles(): array;
    public function getSynergistMuscles(): array;
    public function getStabilizerMuscles(): array;
    public function getIsotonicMuscles(): array;
    public function getIsometricMuscles(): array;
}
```

### 2. Exercise Model Extensions

```php
// Add to existing Exercise model
protected $fillable = [
    // ... existing fields
    'canonical_name'
];

public function intelligence(): HasOne
{
    return $this->hasOne(ExerciseIntelligence::class);
}

public function hasIntelligence(): bool
{
    return $this->intelligence !== null;
}

// Scope for exercises with intelligence data
public function scopeWithIntelligence($query)
{
    return $query->whereHas('intelligence');
}
```

### 3. RecommendationEngine Service

```php
class RecommendationEngine
{
    public function getRecommendations(int $userId, int $count = 5): array;
    private function analyzeUserActivity(int $userId): UserActivityAnalysis;
    private function calculateMuscleWorkload(array $liftLogs): array;
    private function findUnderworkedMuscles(array $muscleWorkload): array;
    private function filterByRecovery(array $exercises, array $recentActivity): array;
    private function scoreExercises(array $exercises, UserActivityAnalysis $analysis): array;
}
```

### 4. UserActivityAnalysis Value Object

```php
class UserActivityAnalysis
{
    public function __construct(
        public readonly array $muscleWorkload,
        public readonly array $movementArchetypes,
        public readonly array $recentExercises,
        public readonly Carbon $analysisDate
    ) {}
    
    public function getMuscleWorkloadScore(string $muscle): float;
    public function getArchetypeFrequency(string $archetype): int;
    public function wasExerciseRecentlyPerformed(int $exerciseId): bool;
    public function getDaysSinceLastWorkout(string $muscle): ?int;
}
```

### 5. ExerciseIntelligenceController

```php
class ExerciseIntelligenceController extends Controller
{
    public function index(): View; // List intelligence data
    public function create(Exercise $exercise): View; // Create intelligence for exercise
    public function store(Request $request, Exercise $exercise): RedirectResponse;
    public function edit(ExerciseIntelligence $intelligence): View;
    public function update(Request $request, ExerciseIntelligence $intelligence): RedirectResponse;
    public function destroy(ExerciseIntelligence $intelligence): RedirectResponse;
}
```

### 6. RecommendationController

```php
class RecommendationController extends Controller
{
    public function index(Request $request): View;
    public function api(Request $request): JsonResponse; // For AJAX requests
}
```

### 7. Intelligence Data Synchronization

#### SyncExerciseIntelligence Command

```php
class SyncExerciseIntelligence extends Command
{
    public function handle(): int;
    private function loadIntelligenceData(): array;
    private function syncExercise(string $canonicalName, array $data): void;
    private function findExerciseByCanonicalName(string $canonicalName): ?Exercise;
}
```

#### JSON Data Structure

The system uses a JSON file (`database/seeders/json/exercise_intelligence_data.json`) for synchronization:

```json
{
  "back_squat": {
    "canonical_name": "back_squat",
    "muscle_data": {
      "muscles": [
        {
          "name": "quadriceps",
          "role": "primary_mover",
          "contraction_type": "isotonic"
        },
        {
          "name": "gluteus_maximus",
          "role": "synergist",
          "contraction_type": "isotonic"
        }
      ]
    },
    "primary_mover": "quadriceps",
    "largest_muscle": "quadriceps",
    "movement_archetype": "squat",
    "category": "strength",
    "difficulty_level": 3,
    "recovery_hours": 48
  },
  "bench_press": {
    "canonical_name": "bench_press",
    "muscle_data": {
      "muscles": [
        {
          "name": "pectoralis_major",
          "role": "primary_mover", 
          "contraction_type": "isotonic"
        }
      ]
    },
    "primary_mover": "pectoralis_major",
    "largest_muscle": "pectoralis_major",
    "movement_archetype": "push",
    "category": "strength",
    "difficulty_level": 2,
    "recovery_hours": 48
  }
}
```

## Data Models

### Canonical Names

Canonical names provide code-friendly, standardized identifiers for exercises to enable reliable synchronization across different data sources.

**Format Rules:**
- Lowercase letters only
- Underscores for word separation
- No spaces, special characters, or numbers
- Examples: `back_squat`, `bench_press`, `overhead_press`, `barbell_row`

**Usage:**
- Optional field for all exercises (nullable)
- Populated only for global exercises
- Used by synchronization commands for exercise matching
- Not displayed in user interface
- Enables consistent data import/export across systems

### Muscle Categories

The system will support individual muscle tracking with the following structure:

**Upper Body:**
- Chest: pectoralis_major, pectoralis_minor
- Back: latissimus_dorsi, rhomboids, middle_trapezius, lower_trapezius, upper_trapezius
- Shoulders: anterior_deltoid, medial_deltoid, posterior_deltoid
- Arms: biceps_brachii, triceps_brachii, brachialis, brachioradialis

**Lower Body:**
- Quadriceps: rectus_femoris, vastus_lateralis, vastus_medialis, vastus_intermedius
- Hamstrings: biceps_femoris, semitendinosus, semimembranosus
- Glutes: gluteus_maximus, gluteus_medius, gluteus_minimus
- Calves: gastrocnemius, soleus

**Core:**
- Abdominals: rectus_abdominis, external_obliques, internal_obliques, transverse_abdominis
- Lower Back: erector_spinae, multifidus

### Movement Archetypes

- **push**: Exercises that involve pushing movements (bench press, overhead press, push-ups)
- **pull**: Exercises that involve pulling movements (rows, pull-ups, deadlifts)
- **squat**: Knee-dominant lower body movements (squats, lunges)
- **hinge**: Hip-dominant movements (deadlifts, hip thrusts, good mornings)
- **carry**: Loaded carries and holds (farmer's walks, suitcase carries)
- **core**: Core-specific movements (planks, crunches, Russian twists)

### Exercise Categories

- **strength**: Traditional resistance training exercises
- **cardio**: Cardiovascular exercises
- **mobility**: Flexibility and mobility work
- **plyometric**: Explosive, jumping movements
- **flexibility**: Static stretching exercises

## Error Handling

### Database Constraints
- Foreign key constraints ensure intelligence data is only created for existing exercises
- Unique constraint prevents duplicate intelligence records per exercise
- Check constraints validate difficulty levels (1-5)
- JSON validation ensures proper muscle_data structure

### Application-Level Validation
- Validate that intelligence can only be added to global exercises
- Ensure muscle names match predefined muscle list
- Validate contraction types (isotonic/isometric)
- Validate muscle roles (primary_mover/synergist/stabilizer)
- Validate movement archetypes and categories against enums

### Graceful Degradation
- Recommendation engine handles exercises without intelligence data by excluding them
- UI shows appropriate messages when no recommendations are available
- System continues to function normally if intelligence data is missing

## Testing Strategy

### Unit Tests
- ExerciseIntelligence model relationships and methods
- RecommendationEngine service logic
- UserActivityAnalysis calculations
- Muscle data JSON structure validation

### Integration Tests
- Exercise-ExerciseIntelligence relationship integrity
- Recommendation generation with real data
- Controller actions and responses
- Database constraint enforcement

### Feature Tests
- Complete recommendation workflow
- Intelligence data CRUD operations
- Global exercise restriction enforcement
- Error handling scenarios

### Performance Tests
- Recommendation generation with large datasets
- Complex muscle workload calculations
- Database query optimization
- JSON field querying performance

## Security Considerations

### Authorization
- Only admins can create/edit intelligence data (global exercises only)
- Users can view recommendations but not modify intelligence data
- Proper authorization checks on all intelligence management endpoints

### Data Validation
- Strict validation of JSON structures
- Sanitization of user inputs
- Prevention of SQL injection through proper ORM usage
- Validation of foreign key relationships

### Privacy
- User activity analysis respects user data privacy
- No sharing of individual user patterns
- Recommendations based only on user's own data