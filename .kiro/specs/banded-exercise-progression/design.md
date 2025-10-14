# Design Document

## Overview

This design implements band-based resistance exercise support for the existing lift logging system. The feature extends the current exercise model to support a new "banded" attribute and modifies the lift logging interfaces to use band selection instead of weight input for banded exercises. The system will maintain the existing hypertrophy progression logic while adapting it for band-based resistance levels.

## Architecture

### Database Schema Changes

The implementation requires minimal database changes to the existing structure:

1. **Exercise Model Extension**: Add `is_banded` boolean column to the `exercises` table
2. **LiftSet Model Extension**: Add `band_type` string column to the `lift_sets` table to store band color


### Band System Design

The system will use a standardized band hierarchy:
- **Red**: Lightest resistance (level 1)
- **Blue**: Light-medium resistance (level 2) 
- **Green**: Medium-heavy resistance (level 3)
- **Black**: Heaviest resistance (level 4)

This hierarchy enables progression logic similar to weight-based exercises.

## Components and Interfaces

### 1. Exercise Management Interface

**Exercise Creation/Edit Forms**
- Add "Banded Exercise" checkbox alongside existing "Bodyweight Exercise" checkbox
- Update validation rules to handle banded exercise attribute

**Exercise Display**
- Show band indicator in exercise lists and details
- Update exercise badges/indicators to include banded status

### 2. Lift Log Entry Interfaces

**Mobile Entry Screen (`mobile-entry.blade.php`)**
- Replace weight input with band selection buttons for banded exercises
- Display colored buttons (red, blue, green, black) with single-tap selection
- Show recommended band based on exercise history
- Maintain existing increment/decrement functionality for reps and sets

**Desktop Lift Log Creation**
- Replace weight input field with band selection buttons for banded exercises
- Implement single-click band selection with visual feedback
- Show band recommendation logic similar to weight suggestions

**Band Selection Component**
- Create reusable band selection component
- Display as colored circular buttons with band names
- Highlight recommended band with visual indicator (border, glow, or checkmark)
- Show selected band with active state styling

### 3. Lift Log Display and History

**Lift Log Tables and Lists**
- Replace weight display with band color/name for banded exercises
- Maintain existing display components but adapt for band data



## Data Models

### Exercise Model Updates

```php
// Add to fillable array
'is_banded'

// Add to casts array  
'is_banded' => 'boolean'



// Add helper method
public function isBanded(): bool
{
    return $this->is_banded === true;
}
```

### LiftSet Model Updates

```php
// Add to fillable array
'band_type'

// Add band hierarchy constants
const BAND_HIERARCHY = [
    'red' => 1,
    'blue' => 2, 
    'green' => 3,
    'black' => 4
];

// Add helper methods
public function getBandLevel(): int
{
    return self::BAND_HIERARCHY[$this->band_type] ?? 0;
}

public function getDisplayWeight(): string
{
    return $this->band_type ? ucfirst($this->band_type) . ' Band' : $this->weight . ' lbs';
}
```

### Band Recommendation Service

Create a new service class to handle band recommendations:

```php
class BandRecommendationService
{
    public function getRecommendedBand(int $userId, int $exerciseId, Carbon $date): ?string
    {
        // Logic similar to TrainingProgressionService but for bands
        // Return recommended band color based on last workout
    }
    
    public function getNextBandProgression(string $currentBand, int $currentReps): array
    {
        // Return progression suggestion (next band or more reps)
    }
}
```

## Error Handling

### Validation Rules

1. **Exercise Creation/Update**
   - Validate band type against allowed values when creating lift sets

2. **Lift Log Creation**
   - Require band selection for banded exercises
   - Validate band type is one of: red, blue, green, black
   - Ensure weight is null when band_type is provided

3. **Data Migration**
   - Handle existing exercises gracefully (default `is_banded` to false)
   - Ensure existing lift sets maintain weight data integrity

### Error Messages

- "Band selection is required for banded exercises"
- "Invalid band type selected"

## Testing Strategy

### Unit Tests

1. **Model Tests**
   - Exercise model banded attribute handling
   - LiftSet model band hierarchy methods
   - Band recommendation service logic

2. **Service Tests**
   - BandRecommendationService recommendation logic
   - Progression calculation for band-based exercises

### Feature Tests

1. **Exercise Management**
   - Creating banded exercises
   - Exercise display with band indicators

2. **Lift Log Entry**
   - Mobile entry with band selection
   - Desktop entry with band selection
   - Band recommendation display
   - Form validation for banded exercises

3. **Lift Log Display**
   - Band display in mobile completed lifts

### Integration Tests

1. **End-to-End Workflows**
   - Complete banded exercise creation and logging workflow
   - Band progression over multiple workouts
   - Mobile and desktop interface consistency

2. **Data Integrity**
   - Migration of existing data
   - Backward compatibility with weight-based exercises
   - Mixed workout sessions (banded and weighted exercises)

## Implementation Considerations

### UI/UX Design

1. **Band Color Representation**
   - Use actual band colors for buttons (#FF0000 for red, #0000FF for blue, etc.)
   - Ensure accessibility with color-blind friendly design
   - Add text labels alongside colors

2. **Mobile Optimization**
   - Large touch targets for band selection buttons
   - Responsive design for different screen sizes
   - Maintain existing mobile-first approach

3. **Visual Feedback**
   - Clear indication of selected band
   - Smooth transitions between band selections
   - Consistent styling with existing UI components

### Performance Considerations

1. **Database Queries**
   - Index `is_banded` column for efficient filtering
   - Optimize band recommendation queries
   - Maintain existing query performance for non-banded exercises

2. **Caching**
   - Cache band recommendations similar to weight suggestions
   - Leverage existing caching mechanisms where possible

### Backward Compatibility

1. **Existing Data**
   - All existing exercises default to `is_banded = false`
   - Existing lift sets continue using weight-based display
   - No disruption to current user workflows

2. **API Consistency**
   - Maintain existing API endpoints
   - Add optional band parameters where needed
   - Preserve existing response formats for non-banded exercises