# Design Document

## Overview

The Exercise Merge feature allows administrators to consolidate duplicate user exercises into existing global exercises. This addresses the common scenario where users create exercises with slightly different names that represent the same movement (e.g., "Bench Press" vs "Barbell Bench Press"). The feature provides a streamlined interface directly on the exercises index page and handles all data migration automatically.

## Architecture

### Core Components

1. **ExerciseMergeService**: Handles the business logic for merging exercises
2. **ExerciseController**: Extended with merge-related endpoints
3. **Exercise Model**: Enhanced with merge compatibility methods
4. **Exercise Index View**: Updated to show merge buttons for eligible exercises

### Data Flow

```
Admin clicks merge button → ExerciseController::showMerge() → 
Display target selection → Admin selects target → 
ExerciseController::merge() → ExerciseMergeService::mergeExercises() → 
Data migration → Source exercise deletion → Redirect with success
```

## Components and Interfaces

### ExerciseMergeService

**Purpose**: Encapsulates all merge logic and data migration operations

**Key Methods**:
- `canBeMerged(Exercise $sourceExercise): bool` - Determines if exercise is eligible for merging
- `getPotentialTargets(Exercise $sourceExercise): Collection` - Returns compatible global exercises
- `validateMergeCompatibility(Exercise $source, Exercise $target): array` - Checks compatibility and returns warnings
- `mergeExercises(Exercise $source, Exercise $target, User $admin): bool` - Performs the actual merge

**Merge Compatibility Rules**:
- Target must be global exercise
- Source and target cannot be the same exercise
- Both must have same `is_bodyweight` value
- Both must have compatible `band_type` values (null can merge with any value)

### Controller Extensions

**New Routes**:
- `GET /exercises/{exercise}/merge` - Show merge target selection page
- `POST /exercises/{exercise}/merge` - Execute the merge operation

**ExerciseController New Methods**:
- `showMerge(Exercise $exercise)` - Display merge interface
- `merge(Request $request, Exercise $exercise)` - Process merge request

### Database Operations

**Data Migration Steps**:
1. Update all `lift_logs` records: change `exercise_id` from source to target
2. Update all `programs` records: change `exercise_id` from source to target  
3. Append merge notes to transferred lift log comments
4. Delete source exercise (this will cascade delete any exercise intelligence)

**Transaction Safety**: All operations wrapped in database transaction to ensure atomicity

## Data Models

### Exercise Model Extensions

**New Methods**:
- `canBeMergedByAdmin(): bool` - Check if exercise is eligible for merging
- `isCompatibleForMerge(Exercise $target): bool` - Check merge compatibility
- `hasOwnerWithGlobalVisibilityDisabled(): bool` - Check if owner has global exercises disabled (for user exercises only)

### LiftLog Comment Annotation

**Format**: `[Merged from: {original_exercise_name}]`

**Implementation**: 
- If existing comments: `{existing_comments} [Merged from: {original_exercise_name}]`
- If no existing comments: `[Merged from: {original_exercise_name}]`

## Error Handling

### Validation Errors
- **Incompatible Exercise Types**: Different bodyweight/band settings
- **User Visibility Warning**: Source exercise owner has global exercises disabled
- **No Target Available**: No compatible global exercises exist
- **Self-Merge Attempt**: Cannot merge exercise into itself

### Runtime Errors
- **Database Transaction Failure**: Rollback all changes, show error message
- **Foreign Key Constraint**: Handle gracefully with user-friendly message
- **Concurrent Modification**: Handle race conditions during merge

### User Feedback
- **Success Messages**: Clear confirmation of merge completion
- **Warning Messages**: Visibility issues and data access implications
- **Error Messages**: Specific, actionable error descriptions

## Testing Strategy

### Unit Tests
- `ExerciseMergeServiceTest`: Test all service methods with various scenarios
- `ExerciseModelTest`: Test new model methods and compatibility checks
- `ExerciseControllerMergeTest`: Test controller endpoints and authorization

### Integration Tests
- **Complete Merge Flow**: End-to-end merge process with data verification
- **Transaction Rollback**: Ensure proper cleanup on failure
- **Permission Checks**: Verify admin-only access to merge functionality

### Test Scenarios
- **Compatible Exercises**: Successful merge with data transfer
- **Incompatible Exercises**: Proper validation and error handling
- **Visibility Warnings**: Correct warning display for users with global exercises disabled
- **Data Integrity**: Verify all foreign keys updated correctly
- **Comment Annotation**: Ensure proper formatting of merge notes

## User Interface Design

### Exercise Index Page Updates

**Merge Button Display Logic**:
- Show for both user and global exercises
- Show only for admin users
- Show only if compatible global target exercises exist (excluding the exercise itself)
- Button style: Orange background with merge icon

**Button Placement**: In the actions column, between promote and delete buttons

### Merge Selection Page

**Layout**:
- Source exercise details at top
- List of compatible target exercises with radio buttons
- Compatibility warnings (if any)
- Merge confirmation button

**Target Exercise Display**:
- Exercise name and description
- Usage statistics (lift logs count, users count)
- Compatibility indicators

### Success/Error Feedback

**Success Message**: "Exercise '{source_name}' successfully merged into '{target_name}'. All workout data has been preserved."

**Error Messages**: Context-specific messages for each validation failure

## Security Considerations

### Authorization
- Only admin users can access merge functionality
- Proper policy checks on all merge endpoints
- CSRF protection on merge forms

### Data Integrity
- Database transactions ensure atomicity
- Foreign key constraint validation
- Proper error handling and rollback procedures

### Audit Considerations
- While no formal audit trail is required, merge operations are logged via standard Laravel logging
- Success/failure outcomes recorded in application logs