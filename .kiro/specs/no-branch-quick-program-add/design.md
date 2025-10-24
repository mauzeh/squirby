# Design Document

## Overview

The quick program add feature will integrate an exercise selector interface identical to the one used in lift-logs/index into the programs/index page. This will allow users to quickly add program entries by clicking on exercise buttons or selecting from a dropdown, automatically creating program entries for the currently viewed date.

## Architecture

The feature will leverage existing components and infrastructure:

- **Existing Component**: `top-exercises-buttons` component will be reused with modified routing
- **Existing Controller Method**: `ProgramController::quickAdd()` method already exists and handles program creation
- **Existing Route**: `programs.quick-add` route already exists for the quick add functionality
- **Frontend Integration**: The component will be integrated into the programs index view

## Components and Interfaces

### 1. Exercise Selector Component

**Component**: `resources/views/components/top-exercises-buttons.blade.php`

**Current Behavior**: 
- Displays top 5 exercises as clickable buttons
- Shows remaining exercises in a hover-activated dropdown
- Routes to `exercises.show-logs` when clicked

**Required Modification**:
- Add a new prop `routeType` to determine routing behavior
- When `routeType` is 'programs', route to `programs.quick-add` instead of `exercises.show-logs`
- Pass the current date as a parameter to the route

### 2. Programs Index View

**File**: `resources/views/programs/index.blade.php`

**Integration Point**: Add the exercise selector component inline with the existing "Add Program Entry" button, positioned to the right of it

**Data Requirements**:
- `$displayExercises`: Top 5 exercises for buttons
- `$allExercises`: All available exercises for dropdown
- `$selectedDate`: Current date being viewed for routing

### 3. Controller Integration

**File**: `app/Http/Controllers/ProgramController.php`

**Existing Method**: `quickAdd(Request $request, Exercise $exercise, $date)`
- Already handles program creation with proper sets/reps calculation
- Returns redirect to `lift-logs.mobile-entry` - needs modification for programs context

**Required Modification**:
- Update redirect destination to return to `programs.index` with the same date
- Maintain existing program creation logic

**Index Method Enhancement**:
- Add exercise data fetching for the selector component
- Fetch top exercises and all exercises similar to lift-logs controller

## Data Models

### Exercise Selection Logic

The exercise selection will follow the same pattern as lift-logs:

1. **Top Exercises**: Determined by user's most frequently used exercises
2. **All Exercises**: All exercises available to the current user
3. **Filtering**: Exercises filtered by user ownership and availability

### Program Creation Logic

Existing logic in `ProgramController::quickAdd()`:
- Calculates sets/reps using `TrainingProgressionService`
- Determines priority based on existing programs for the date
- Creates program with proper user association

## Error Handling

### Validation Errors
- Exercise must exist and be available to user
- Date must be valid
- User must be authenticated

### Business Logic Errors
- Duplicate program entries: Allow multiple entries for same exercise (current behavior)
- Invalid exercise selection: Display error message and redirect back

### User Feedback
- Success message: "Exercise added to program successfully"
- Error messages: Display validation or system errors
- Redirect behavior: Return to programs index with same date context

## Testing Strategy

### Unit Tests
- Test component prop handling for different route types
- Test controller method modifications
- Test exercise data fetching in index method

### Integration Tests
- Test complete flow from button click to program creation
- Test dropdown functionality with program routing
- Test date context preservation
- Test error handling scenarios

### Feature Tests
- Test user interaction with exercise selector
- Test program creation from both buttons and dropdown
- Test success/error message display
- Test redirect behavior

## Implementation Details

### Component Modification

```php
// Modified component signature
@props(['exercises', 'allExercises', 'currentExerciseId' => null, 'routeType' => 'exercises', 'date' => null])

// Conditional routing logic
@if($routeType === 'programs')
    <a href="{{ route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]) }}" class="button">
@else
    <a href="{{ route('exercises.show-logs', ['exercise' => $exercise->id]) }}" class="button">
@endif
```

### Controller Enhancement

```php
// In ProgramController::index()
$displayExercises = Exercise::getTopExercisesForUser(auth()->id(), 5);
$allExercises = Exercise::availableToUser(auth()->id())->orderBy('title')->get();

// In ProgramController::quickAdd()
return redirect()->route('programs.index', ['date' => $date])
    ->with('success', 'Exercise added to program successfully.');
```

### View Integration

```php
// In programs/index.blade.php - inline layout
<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
    <a href="{{ route('programs.create', ['date' => $selectedDate->toDateString()]) }}" class="button create">Add Program Entry</a>
    
    <div style="flex: 1;">
        <x-top-exercises-buttons 
            :exercises="$displayExercises" 
            :allExercises="$allExercises" 
            routeType="programs"
            :date="$selectedDate->toDateString()" 
        />
    </div>
</div>
```

## Security Considerations

- User authentication: Ensured through existing middleware
- Exercise ownership: Validated through `availableToUser()` scope
- Program ownership: Enforced through user_id assignment
- Date validation: Handled through Carbon parsing with error handling

## Performance Considerations

- Exercise queries: Reuse existing optimized queries from lift-logs
- Component rendering: Minimal overhead as component already exists
- Database operations: Single insert operation for program creation
- Caching: No additional caching requirements