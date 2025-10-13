# Design Document

## Overview

This design implements automatic derivation of sets and reps for new program entries using the existing TrainingProgressionService. The solution removes manual input fields from the program creation form only and integrates progression logic to calculate optimal training parameters based on user history. Program editing functionality retains manual control over sets and reps values.

## Architecture

The design leverages the existing TrainingProgressionService architecture with minimal changes to the current system. The main integration points are:

- **ProgramController**: Enhanced to use TrainingProgressionService for calculating sets/reps
- **Program Forms**: Modified to remove manual input fields and show calculated values
- **Request Validation**: Updated to remove sets/reps validation requirements
- **TrainingProgressionService**: Used as-is for progression calculations

## Components and Interfaces

### Enhanced ProgramController

**Modified Methods:**
- `create()`: Inject TrainingProgressionService and pass suggestion data to view
- `store()`: Calculate sets/reps before saving program entry
- `edit()`: No changes - retains existing functionality with manual input fields
- `update()`: No changes - retains existing functionality with manual input fields

**New Private Method:**
```php
private function calculateSetsAndReps(int $exerciseId, Carbon $date): array
{
    $suggestion = $this->trainingProgressionService->getSuggestionDetails(
        auth()->id(), 
        $exerciseId, 
        $date
    );
    
    return [
        'sets' => $suggestion ? $suggestion->sets : config('training.defaults.sets', 3),
        'reps' => $suggestion ? $suggestion->reps : config('training.defaults.reps', 10),
        'suggestion_available' => $suggestion !== null
    ];
}
```

### Updated Form Views

**programs/create.blade.php:**
- Create separate form partial for creation that excludes sets/reps fields
- Add informational section showing how sets/reps are calculated
- No real-time calculation display needed

**programs/edit.blade.php:**
- No changes - retains existing _form.blade.php with manual input fields
- Continues to show current sets/reps values as editable fields

**programs/_form_create.blade.php (new):**
- Copy of _form.blade.php but without sets and reps input fields
- Add informational messaging about auto-calculation
- No JavaScript or real-time updates needed

**programs/_form.blade.php:**
- No changes - retains sets and reps input fields for editing

### Request Validation Updates

**StoreProgramRequest:**
- Remove 'sets' and 'reps' from validation rules
- Keep other validation rules unchanged

**UpdateProgramRequest:**
- No changes - retains 'sets' and 'reps' validation rules for manual editing
- Continues to validate manual input for program updates



## Data Models

No changes to existing data models are required. The Program model continues to store sets and reps as integer fields, but these values are now calculated automatically rather than manually input.

**Program Model Fields (unchanged):**
- sets: integer
- reps: integer
- Other existing fields remain the same

## Error Handling

### Progression Service Failures
- **Scenario**: TrainingProgressionService returns null or throws exception
- **Handling**: Fall back to default values from config/training.php
- **User Experience**: Show message indicating default values are being used

### Invalid Exercise Selection
- **Scenario**: Exercise ID is invalid or doesn't belong to user
- **Handling**: Existing validation handles this case
- **User Experience**: Standard validation error messages

### Missing Configuration Defaults
- **Scenario**: Default values not found in config
- **Handling**: Hard-coded fallbacks (3 sets, 10 reps)
- **User Experience**: System continues to function with reasonable defaults

## Testing Strategy

### Unit Tests

**ProgramController Tests:**
- Test calculateSetsAndReps() method with various scenarios
- Test store() method uses calculated values
- Test update() method preserves values when exercise unchanged
- Test fallback to defaults when progression service returns null

**Integration Tests:**
- Test complete program creation workflow with auto-calculated values
- Test program editing workflow with exercise changes
- Test quick-add functionality continues to work
- Test backward compatibility with existing program entries

**Feature Tests:**
- Test form rendering without sets/reps fields
- Test error handling scenarios
- Test user experience with informational messages

### Test Scenarios

1. **New Program Creation**
   - With existing progression data → Uses calculated values
   - Without progression data → Uses default values
   - With bodyweight exercise → Uses appropriate calculation method

2. **Program Editing**
   - Manual input fields available → User can edit sets/reps manually
   - Form validation works as before → Validates manual input
   - No auto-calculation → User retains full control

3. **Quick Operations**
   - Quick-add with progression data → Uses calculated values
   - Quick-create new exercise → Uses default values

4. **Edge Cases**
   - TrainingProgressionService throws exception → Uses defaults
   - Missing configuration values → Uses hard-coded fallbacks
   - User has no lift history → Uses defaults gracefully

## Implementation Considerations

### Backward Compatibility
- Existing program entries retain their stored sets/reps values
- No database migration required
- Edit functionality remains unchanged with manual input fields

### Performance Impact
- Minimal impact as TrainingProgressionService is already optimized
- Consider caching calculated values for repeated requests
- Database queries are already efficient in existing service

### User Experience
- Clear messaging about automatic calculation
- Simple form without manual sets/reps input
- Consistent behavior across create/edit/quick-add workflows

### Configuration Dependencies
- Relies on existing config/training.php defaults
- Falls back gracefully if configuration is missing
- No new configuration requirements

## Security Considerations

- No new security vulnerabilities introduced
- Existing authorization checks remain in place
- Input validation simplified by removing manual sets/reps input
- TrainingProgressionService already handles user authorization properly