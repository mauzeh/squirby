# Cardio Exercise Type Design

## Overview

The Cardio exercise type introduces distance-based cardiovascular exercise tracking to the existing exercise type system. This design leverages the current Strategy Pattern architecture while adding cardio-specific behavior for exercises like running, cycling, and rowing.

The key innovation is treating the existing `reps` field as distance (in meters) and `sets` field as rounds, while always setting `weight` to 0. This approach requires no database schema changes while providing cardio-appropriate semantics.

## Architecture

### Exercise Type Strategy Integration

The Cardio exercise type integrates into the existing exercise type system:

```
ExerciseTypeInterface
├── BaseExerciseType (abstract)
│   ├── RegularExerciseType
│   ├── BandedExerciseType
│   ├── BodyweightExerciseType
│   └── CardioExerciseType (NEW)
```

### Data Model Mapping

The Cardio type reuses existing database fields with semantic mapping:

| Database Field | Cardio Semantic | Example Value | Notes |
|---------------|-----------------|---------------|-------|
| `reps` | Distance (meters) | 500 | Always in meters |
| `sets` | Rounds | 7 | Number of intervals |
| `weight` | Not used | 0 | Always zero |
| `band_color` | Not used | null | Always null |

### Configuration Structure

```php
'cardio' => [
    'class' => \App\Services\ExerciseTypes\CardioExerciseType::class,
    'validation' => [
        'reps' => 'required|integer|min:50|max:50000', // Distance in meters
        'weight' => 'nullable|numeric|in:0', // Must be 0
    ],
    'chart_type' => 'cardio_progression',
    'supports_1rm' => false,
    'form_fields' => ['reps'], // Only distance, no weight
    'progression_types' => ['cardio_progression'],
    'display_format' => 'distance_rounds',
],
```

## Components and Interfaces

### CardioExerciseType Class

```php
class CardioExerciseType extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'cardio';
    }
    
    public function processLiftData(array $data): array
    {
        // Force weight to 0 and nullify band_color
        $data['weight'] = 0;
        $data['band_color'] = null;
        
        // Validate distance (reps field)
        if (!isset($data['reps']) || $data['reps'] < 50) {
            throw new InvalidExerciseDataException('Distance must be at least 50 meters');
        }
        
        return $data;
    }
    
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        // Display distance instead of weight
        $distance = $liftLog->display_reps; // reps = distance
        return $distance . 'm';
    }
    
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        // Cardio-specific progression logic
        $distance = $liftLog->display_reps;
        $rounds = $liftLog->liftSets->count();
        
        if ($distance < 1000) {
            $newDistance = $distance + 100;
            return "Try {$newDistance}m × {$rounds} rounds";
        } else {
            $newRounds = $rounds + 1;
            return "Try {$distance}m × {$newRounds} rounds";
        }
    }
}
```

### Mobile Entry Interface Updates

The mobile entry forms need cardio-specific labels:

```php
// In LiftLogService::generateProgramForms()
if ($strategy->getTypeName() === 'cardio') {
    $numericFields[] = [
        'id' => $formId . '-distance',
        'name' => 'reps', // Still maps to reps field
        'label' => 'Distance (m):',
        'defaultValue' => $defaultDistance,
        'increment' => 50, // 50m increments
        'min' => 50,
        'max' => 50000,
    ];
    
    $numericFields[] = [
        'id' => $formId . '-rounds',
        'name' => 'rounds',
        'label' => 'Rounds:',
        'defaultValue' => $defaultRounds,
        'increment' => 1,
        'min' => 1,
    ];
}
```

### Exercise Type Detection

Update the exercise type detection logic to identify cardio exercises:

```php
// In ExerciseTypeFactory or detection logic
public function determineExerciseType(Exercise $exercise): string
{
    // Check for cardio indicators
    if ($this->isCardioExercise($exercise)) {
        return 'cardio';
    }
    
    // Existing logic for other types...
}

private function isCardioExercise(Exercise $exercise): bool
{
    $cardioKeywords = ['run', 'running', 'cycle', 'cycling', 'row', 'rowing', 'walk', 'walking'];
    $title = strtolower($exercise->title);
    
    foreach ($cardioKeywords as $keyword) {
        if (str_contains($title, $keyword)) {
            return true;
        }
    }
    
    return false;
}
```

## Data Models

### Exercise Model Updates

No database schema changes required. The Exercise model may need a method to determine if it's a cardio exercise:

```php
// In Exercise model
public function isCardioExercise(): bool
{
    return $this->getTypeStrategy()->getTypeName() === 'cardio';
}
```

### Display Formatting

Update display methods to show cardio-appropriate formatting:

```php
// In LiftLog model or display helpers
public function getFormattedDisplay(): string
{
    $strategy = $this->exercise->getTypeStrategy();
    
    if ($strategy->getTypeName() === 'cardio') {
        $distance = $this->display_reps; // reps = distance
        $rounds = $this->liftSets->count();
        return "{$distance}m × {$rounds} rounds";
    }
    
    // Existing formatting for other types...
}
```

## Error Handling

### Validation Errors

Cardio-specific validation with appropriate error messages:

```php
class CardioExerciseType extends BaseExerciseType
{
    public function getValidationRules(?User $user = null): array
    {
        return [
            'reps' => 'required|integer|min:50|max:50000',
            'weight' => 'nullable|numeric|in:0',
            'sets' => 'required|integer|min:1|max:20',
        ];
    }
    
    public function getValidationMessages(): array
    {
        return [
            'reps.min' => 'Distance must be at least 50 meters',
            'reps.max' => 'Distance cannot exceed 50,000 meters',
            'weight.in' => 'Cardio exercises cannot have weight',
        ];
    }
}
```

### Data Processing Errors

Handle edge cases in cardio data processing:

```php
public function processLiftData(array $data): array
{
    // Ensure weight is always 0
    $data['weight'] = 0;
    $data['band_color'] = null;
    
    // Validate distance
    if (!isset($data['reps']) || !is_numeric($data['reps'])) {
        throw InvalidExerciseDataException::forField('reps', 'cardio', 'distance must be a number');
    }
    
    if ($data['reps'] < 50) {
        throw InvalidExerciseDataException::forField('reps', 'cardio', 'distance must be at least 50 meters');
    }
    
    return $data;
}
```

## Testing Strategy

### Unit Tests

1. **CardioExerciseTypeTest**: Test all strategy methods
2. **CardioValidationTest**: Test validation rules and error handling
3. **CardioDisplayTest**: Test formatting and display methods
4. **CardioProgressionTest**: Test progression suggestion logic

### Integration Tests

1. **CardioMobileEntryTest**: Test mobile entry form generation
2. **CardioExerciseCreationTest**: Test creating cardio exercises
3. **CardioWorkoutLoggingTest**: Test logging cardio workouts
4. **CardioHistoryDisplayTest**: Test historical data display

### Migration Tests

1. **CardioMigrationTest**: Test converting existing running exercises
2. **CardioDataIntegrityTest**: Ensure no data loss during migration
3. **CardioBackwardCompatibilityTest**: Ensure old data still works

## Implementation Phases

### Phase 1: Core Cardio Type
- Create CardioExerciseType class
- Add cardio configuration
- Implement basic validation and processing
- Add unit tests

### Phase 2: Mobile Entry Integration
- Update LiftLogService for cardio forms
- Add cardio-specific form fields and labels
- Update form generation logic
- Test mobile entry interface

### Phase 3: Progression Logic
- Implement cardio progression in TrainingProgressionService
- Add cardio-specific suggestion algorithms
- Test progression suggestions
- Validate against real cardio data

### Phase 4: Display and Formatting
- Update display formatting for cardio exercises
- Add cardio-appropriate terminology throughout UI
- Test all display contexts (logs, history, summaries)
- Ensure consistent cardio presentation

### Phase 5: Migration and Cleanup
- Create migration script for existing cardio exercises
- Update exercise type detection logic
- Test with real user data (especially "Run" exercise)
- Performance testing and optimization

## Migration Strategy

### Identifying Cardio Exercises

```sql
-- Find exercises that should be cardio type
SELECT * FROM exercises 
WHERE LOWER(title) LIKE '%run%' 
   OR LOWER(title) LIKE '%cycle%' 
   OR LOWER(title) LIKE '%row%'
   OR LOWER(title) LIKE '%walk%';
```

### Migration Script

```php
// Migration to update exercise types
public function migrateCardioExercises()
{
    $cardioKeywords = ['run', 'cycle', 'row', 'walk'];
    
    foreach ($cardioKeywords as $keyword) {
        Exercise::where('title', 'LIKE', "%{$keyword}%")
            ->update(['exercise_type' => 'cardio']); // If we add this field
    }
    
    // Clear any cached exercise type strategies
    Cache::tags(['exercise_types'])->flush();
}
```

## Performance Considerations

### Database Optimization

- No additional database queries required (reuses existing schema)
- Existing indexes on exercises and lift_logs tables remain effective
- No performance impact on non-cardio exercises

## Security Considerations

### Input Validation

- Validate distance values are reasonable (50m - 50km)
- Ensure weight is always 0 for cardio exercises
- Prevent injection attacks through distance/rounds inputs

### Data Integrity

- Enforce cardio exercise constraints at the application level
- Maintain referential integrity with existing exercise system
- Ensure cardio exercises cannot have invalid weight/band data