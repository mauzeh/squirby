# Design Document

## Overview

This design implements bulk exercise promotion functionality that allows administrators to promote multiple user-created exercises to global exercises in a single operation. The feature integrates seamlessly with the existing bulk delete functionality, using the same UI patterns and interaction model for consistency.

## Architecture

### System Integration

The bulk promotion feature leverages the existing exercise management infrastructure:

- **Exercise Model**: Already supports global/user-specific exercises via `user_id` field
- **Authorization System**: Uses existing admin role checking and exercise policies
- **UI Framework**: Extends the current bulk operations interface in the exercise index
- **Route Structure**: Follows the established pattern for bulk operations

### Data Flow

1. **Selection**: Admin selects user exercises via checkboxes (same as bulk delete)
2. **Submission**: Form submits selected exercise IDs to new bulk promotion endpoint
3. **Authorization**: System verifies admin permissions for each selected exercise
4. **Promotion**: Updates `user_id` to `null` for selected exercises
5. **Response**: Returns success message and refreshes exercise list

## Components and Interfaces

### Controller Enhancement

The existing `ExerciseController` will be extended with a new method:

```php
/**
 * Promote selected user exercises to global exercises.
 */
public function promoteSelected(Request $request)
{
    $validated = $request->validate([
        'exercise_ids' => 'required|array',
        'exercise_ids.*' => 'exists:exercises,id',
    ]);

    $exercises = Exercise::whereIn('id', $validated['exercise_ids'])->get();

    // Verify admin permissions and that exercises are user-specific
    foreach ($exercises as $exercise) {
        $this->authorize('promoteToGlobal', $exercise);
        
        if ($exercise->isGlobal()) {
            return back()->withErrors(['error' => "Exercise '{$exercise->title}' is already global."]);
        }
    }

    // Promote all selected exercises
    Exercise::whereIn('id', $validated['exercise_ids'])
        ->update(['user_id' => null]);

    $count = count($validated['exercise_ids']);
    return redirect()->route('exercises.index')
        ->with('success', "Successfully promoted {$count} exercise(s) to global status.");
}
```

### Authorization Policy Extension

The existing `ExercisePolicy` will be extended:

```php
/**
 * Determine whether the user can promote the exercise to global.
 */
public function promoteToGlobal(User $user, Exercise $exercise): bool
{
    // Only admins can promote exercises
    if (!$user->hasRole('Admin')) {
        return false;
    }
    
    // Can only promote user-specific exercises (not already global)
    return !$exercise->isGlobal();
}
```

### Route Addition

A new route will be added following the existing pattern:

```php
Route::post('exercises/promote-selected', [ExerciseController::class, 'promoteSelected'])
    ->name('exercises.promote-selected');
```

### Frontend Implementation

The exercise index view will be enhanced with bulk promotion functionality:

#### HTML Structure

```html
<!-- Add promotion form alongside existing delete form in tfoot -->
<tfoot>
    <tr>
        <th><input type="checkbox" id="select-all-exercises-footer"></th>
        <th colspan="4" style="text-align:left; font-weight:normal;">
            <!-- Existing delete form -->
            <form action="{{ route('exercises.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected exercises?');" style="display:inline;">
                @csrf
                <button type="submit" class="button delete"><i class="fa-solid fa-trash"></i> Delete Selected</button>
            </form>
            
            <!-- New promotion form (ADMIN ONLY - hidden from regular users) -->
            @if(auth()->user()->hasRole('Admin'))
            <form action="{{ route('exercises.promote-selected') }}" method="POST" id="promote-selected-form" onsubmit="return confirm('Are you sure you want to promote the selected exercises to global status?');" style="display:inline; margin-left: 10px;">
                @csrf
                <button type="submit" class="button" style="background-color: #4CAF50;"><i class="fa-solid fa-globe"></i> Promote</button>
            </form>
            @endif
        </th>
    </tr>
</tfoot>
```

#### JavaScript Enhancement

```javascript
// Extend existing JavaScript to handle promotion form
document.getElementById('promote-selected-form')?.addEventListener('submit', function(e) {
    var checkedBoxes = document.querySelectorAll('.exercise-checkbox:checked:not([disabled])');
    if (checkedBoxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one exercise to promote.');
        return false;
    }
    
    // Filter to only user exercises (not already global)
    var userExercises = Array.from(checkedBoxes).filter(function(checkbox) {
        var row = checkbox.closest('tr');
        var badge = row.querySelector('.badge');
        return badge && !badge.textContent.includes('Everyone');
    });
    
    if (userExercises.length === 0) {
        e.preventDefault();
        alert('Please select user exercises to promote. Global exercises cannot be promoted.');
        return false;
    }
    
    // Add selected user exercise IDs to the form
    userExercises.forEach(function(checkbox) {
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'exercise_ids[]';
        hiddenInput.value = checkbox.value;
        this.appendChild(hiddenInput);
    }, this);
});
```

### UI/UX Considerations

#### Visual Design

- **Button Styling**: Green background (`#4CAF50`) to distinguish from red delete button
- **Icon**: Globe icon (`fa-globe`) to represent global promotion
- **Placement**: Next to delete button in table footer
- **Visibility**: **ONLY shown to admin users** - completely hidden from regular users via `@if(auth()->user()->hasRole('Admin'))` blade directive

#### User Experience

- **Selection Logic**: Only user exercises can be selected for promotion
- **Confirmation**: Standard confirmation dialog before promotion
- **Feedback**: Clear success/error messages after operation
- **State Management**: Immediate UI refresh to show promoted exercises as global

## Data Models

### Exercise Model

No changes required to the existing Exercise model. The promotion functionality uses existing methods:

- `isGlobal()`: Check if exercise is already global
- `canBeEditedBy()`: Authorization for exercise modification
- Existing scopes: `global()`, `userSpecific()`, `availableToUser()`

### Database Operations

The promotion operation is a simple update query:

```sql
UPDATE exercises 
SET user_id = NULL 
WHERE id IN (selected_exercise_ids);
```

This leverages the existing database schema where:
- `user_id = NULL` indicates global exercises
- `user_id = [user_id]` indicates user-specific exercises

## Error Handling

### Validation Errors

1. **No Selection**: JavaScript prevents submission with helpful message
2. **Invalid IDs**: Laravel validation ensures exercise IDs exist
3. **Already Global**: Server-side check prevents promoting global exercises
4. **Permission Denied**: Policy authorization returns 403 for non-admins

### Exception Handling

```php
try {
    Exercise::whereIn('id', $validated['exercise_ids'])
        ->update(['user_id' => null]);
} catch (\Exception $e) {
    return back()->withErrors(['error' => 'Promotion failed: ' . $e->getMessage()]);
}
```

### User Feedback

- **Success**: "Successfully promoted X exercise(s) to global status."
- **Partial Success**: Not applicable (all-or-nothing operation)
- **Failure**: Specific error messages for different failure scenarios

## Testing Strategy

### Unit Tests

1. **Controller Method**: Test `promoteSelected()` with various inputs
2. **Policy Method**: Test `promoteToGlobal()` authorization logic
3. **Model Behavior**: Verify exercise state changes after promotion

### Feature Tests

1. **Admin Promotion**: Test successful bulk promotion by admin user
2. **Permission Denial**: Test 403 response for non-admin users
3. **UI Integration**: Test form submission and response handling
4. **Edge Cases**: Test promotion of already-global exercises

### Integration Tests

1. **Database Changes**: Verify `user_id` updates correctly
2. **UI Updates**: Confirm exercise list reflects changes
3. **Authorization Flow**: Test complete admin workflow

### Test Scenarios

```php
/** @test */
public function admin_can_bulk_promote_user_exercises_to_global()
{
    // Setup: Create admin user and user exercises
    // Action: Submit promotion form
    // Assert: Exercises become global, success message shown
}

/** @test */
public function non_admin_cannot_access_bulk_promotion()
{
    // Setup: Create regular user
    // Action: Attempt promotion
    // Assert: 403 response
}

/** @test */
public function cannot_promote_already_global_exercises()
{
    // Setup: Create global exercises
    // Action: Attempt promotion
    // Assert: Error message, no changes
}
```