# Design Document

## Overview

This design implements exercise unpromote functionality that allows administrators to convert global exercises back to user exercises. The system uses existing LiftLog entries to determine the original owner and includes safety checks to prevent data integrity issues when other users have logged workouts with the exercise.

## Architecture

The unpromote feature follows the same architectural patterns as the existing promote functionality, using:
- Controller method for handling unpromote requests
- Policy-based authorization
- Database queries to check LiftLog conflicts
- UI integration with existing exercise management interface

## Components and Interfaces

### Controller Method

**Add to ExerciseController:**
```php
/**
 * Unpromote a global exercise back to user exercise.
 */
public function unpromote(Exercise $exercise)
{
    $this->authorize('unpromoteToUser', $exercise);
    
    if (!$exercise->isGlobal()) {
        return back()->withErrors(['error' => "Exercise '{$exercise->title}' is not a global exercise."]);
    }

    // Determine original owner from earliest lift log
    $originalOwner = $this->determineOriginalOwner($exercise);
    
    if (!$originalOwner) {
        return back()->withErrors(['error' => "Cannot determine original owner for exercise '{$exercise->title}'."]);
    }

    // Check if other users have lift logs with this exercise
    $otherUsersCount = $exercise->liftLogs()
        ->where('user_id', '!=', $originalOwner->id)
        ->distinct('user_id')
        ->count('user_id');

    if ($otherUsersCount > 0) {
        $userText = $otherUsersCount === 1 ? 'user has' : 'users have';
        return back()->withErrors(['error' => "Cannot unpromote exercise '{$exercise->title}': {$otherUsersCount} other {$userText} workout logs with this exercise. The exercise must remain global to preserve their data."]);
    }

    $exercise->update(['user_id' => $originalOwner->id]);

    return redirect()->route('exercises.index')
        ->with('success', "Exercise '{$exercise->title}' unpromoted to personal exercise successfully.");
}

/**
 * Determine the original owner of an exercise based on lift logs.
 */
private function determineOriginalOwner(Exercise $exercise): ?User
{
    // Get the user who has the earliest lift log for this exercise
    $earliestLog = $exercise->liftLogs()
        ->with('user')
        ->orderBy('logged_at', 'asc')
        ->first();

    return $earliestLog ? $earliestLog->user : null;
}
```

### Policy Authorization

**Add to ExercisePolicy:**
```php
/**
 * Determine whether the user can unpromote the exercise to user exercise.
 */
public function unpromoteToUser(User $user, Exercise $exercise): bool
{
    // Only admins can unpromote exercises
    if (!$user->hasRole('Admin')) {
        return false;
    }
    
    // Can only unpromote global exercises
    return $exercise->isGlobal();
}
```

### Route Definition

**Add to routes/web.php:**
```php
Route::post('exercises/{exercise}/unpromote', [ExerciseController::class, 'unpromote'])
    ->name('exercises.unpromote');
```

### UI Components

**Modify exercises/index.blade.php:**

Add unpromote button in the actions column for global exercises:
```php
@if(auth()->user()->hasRole('Admin') && $exercise->isGlobal())
    <form action="{{ route('exercises.unpromote', $exercise->id) }}" method="POST" style="display:inline;">
        @csrf
        <button type="submit" class="button" style="background-color: #FF9800;" onclick="return confirm('Are you sure you want to unpromote this exercise back to personal status? This will only work if no other users have workout logs with this exercise.');" title="Unpromote to personal exercise"><i class="fa-solid fa-user"></i></button>
    </form>
@endif
```

**Button Styling:**
- Color: Orange background (`#FF9800`) to distinguish from promote (green) and delete (red)
- Icon: User icon (`fa-user`) to represent personal ownership
- Position: After promote button (if present), before delete button
- Tooltip: "Unpromote to personal exercise"
- Confirmation: JavaScript confirm dialog with detailed explanation

## Data Models

### Exercise Model

No changes needed to the Exercise model - existing `isGlobal()` method and relationships are sufficient.

### LiftLog Relationships

Leverages existing relationships:
- `Exercise->liftLogs()` to check for conflicts
- `LiftLog->user` to determine original owner
- Uses `logged_at` timestamp to identify earliest usage

## Error Handling

### Authorization Errors
- 403 Forbidden if non-admin attempts unpromote
- 403 Forbidden if attempting to unpromote non-global exercise

### Validation Errors
- Cannot unpromote if exercise is not global
- Cannot unpromote if original owner cannot be determined
- Cannot unpromote if other users have lift logs

### Data Integrity Safeguards
- Check for other users' lift logs before allowing unpromote
- Preserve all existing lift log relationships
- Maintain exercise metadata and timestamps

## Testing Strategy

### Unit Tests
- Test original owner determination logic
- Test conflict detection for other users' logs
- Test authorization policy methods

### Integration Tests
- Test complete unpromote workflow
- Test error scenarios (no original owner, other users have logs)
- Test UI button visibility and functionality

### Test Cases to Maintain
- Admin can unpromote global exercises (when safe)
- Non-admin cannot unpromote exercises
- Cannot unpromote exercises with other users' logs
- Cannot unpromote non-global exercises
- Unpromoted exercises become personal to original owner
- UI shows unpromote buttons only for eligible exercises

### New Test Cases
- Original owner determination from lift logs
- Conflict detection prevents unsafe unpromote
- Error messages provide clear feedback
- Successful unpromote preserves all data integrity

## Implementation Notes

### Original Owner Logic
- Uses earliest `logged_at` timestamp to determine original owner
- Assumes the first user to log workouts was the creator
- Handles edge case where no lift logs exist (prevents unpromote)

### Safety Checks
- Counts distinct users with lift logs (excluding original owner)
- Provides specific error messages with user counts
- Prevents any unpromote that would break data relationships

### User Experience
- Clear confirmation dialog explains the safety check
- Detailed error messages explain why unpromote failed
- Success messages confirm the ownership change
- Immediate UI refresh shows updated exercise status