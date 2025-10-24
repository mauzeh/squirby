# Design Document

## Overview

This design modifies the existing exercise promotion system to replace bulk promotion with individual promotion buttons. The change involves updating the UI to show promote buttons next to each user exercise's actions, creating a new route for individual promotion, and removing the bulk promotion functionality while preserving bulk deletion.

## Architecture

The system will maintain the existing MVC architecture with these modifications:

- **Route**: New individual promotion route replacing bulk promotion route
- **Controller**: New individual promotion method replacing bulk promotion method  
- **View**: Updated exercises index to show individual promote buttons
- **Policy**: Reuse existing `promoteToGlobal` policy for authorization
- **JavaScript**: Remove bulk promotion logic while keeping bulk deletion

## Components and Interfaces

### Route Changes

**Remove:**
- `POST /exercises/promote-selected` (bulk promotion)

**Add:**
- `POST /exercises/{exercise}/promote` (individual promotion)

### Controller Changes

**ExerciseController modifications:**

**Remove:**
- `promoteSelected(Request $request)` method

**Add:**
- `promote(Exercise $exercise)` method that:
  - Authorizes using `promoteToGlobal` policy
  - Validates exercise is not already global
  - Updates exercise to global status
  - Returns redirect with success message

### View Changes

**exercises/index.blade.php modifications:**

**Remove:**
- Bulk promote button from table footer
- Bulk promotion JavaScript logic
- Bulk promotion form submission handling

**Add:**
- Individual promote button in actions column for each user exercise
- Promote button only shown for:
  - Admin users
  - User exercises (not global exercises)
- Button styled consistently with edit/delete buttons
- Globe icon for visual consistency

**Maintain:**
- Checkboxes for bulk deletion
- Bulk delete functionality
- All existing JavaScript for bulk deletion

### UI Design

**Promote Button Specifications:**
- Icon: `fa-solid fa-globe` (globe icon)
- Color: Green background (`#4CAF50`) to match existing promote button color
- Position: In actions column after edit button, before delete button
- Tooltip: "Promote to global exercise"
- Confirmation: JavaScript confirm dialog before submission

**Button Visibility Logic:**
```php
@if(auth()->user()->hasRole('Admin') && !$exercise->isGlobal())
    // Show promote button
@endif
```

## Data Models

No changes to existing data models. The promotion process continues to work by setting `user_id = null` to make exercises global.

## Error Handling

**Authorization Errors:**
- 403 Forbidden if non-admin attempts promotion
- 403 Forbidden if attempting to promote already global exercise

**Validation Errors:**
- Model binding handles invalid exercise IDs (404)
- Policy authorization handles permission checks

**Success Handling:**
- Redirect to exercises index with success flash message
- Message format: "Exercise '{title}' promoted to global status successfully."

## Testing Strategy

**Update Existing Tests:**
- Modify `ExerciseBulkPromotionTest` to test individual promotion
- Update test methods to use new individual promotion route
- Remove bulk promotion specific test cases
- Add tests for individual promote button visibility

**Test Cases to Maintain:**
- Admin can promote user exercises
- Non-admin cannot promote exercises
- Cannot promote already global exercises
- Promotion preserves exercise metadata and lift logs
- Promoted exercises become visible to all users
- UI shows promote buttons only for eligible exercises

**New Test Cases:**
- Individual promotion route works correctly
- Promote button appears in correct position
- Promote button has correct styling and icon
- JavaScript confirmation works for individual promotion

## Implementation Notes

**Backward Compatibility:**
- Remove bulk promotion route to prevent accidental usage
- Update any existing bookmarks or direct links to bulk promotion

**Performance Considerations:**
- Individual promotion is more efficient than bulk for single exercises
- No impact on page load performance
- Reduced JavaScript complexity

**Security Considerations:**
- Reuse existing authorization policy
- Maintain CSRF protection on new route
- No additional security concerns introduced