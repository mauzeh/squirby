# Design Document

## Overview

This document provides a comprehensive analysis of the TSV import functionality differences between lift-logs and exercises, along with design recommendations for achieving feature parity and improving user experience consistency.

## Architecture

### Current Implementation Analysis

**Lift-Log TSV Import:**
- **Data Structure**: 7 columns (date, time, exercise_name, weight, reps, rounds, notes)
- **Duplicate Detection**: Complex logic based on user_id, exercise_id, logged_at, and lift set matching
- **Update Logic**: Updates comments if sets match but notes differ
- **Dependencies**: Requires existing exercises (no auto-creation)
- **Scope**: Personal data only (no global concept)
- **Success Messages**: Detailed lists for small imports, summaries for large ones

**Exercise TSV Import:**
- **Data Structure**: 3 columns (title, description, is_bodyweight)
- **Duplicate Detection**: Case-insensitive title matching within scope
- **Update Logic**: Updates description and is_bodyweight if data differs
- **Dependencies**: Self-contained (creates new exercises)
- **Scope**: Personal or global (admin-controlled)
- **Success Messages**: Detailed lists with change tracking for all import sizes

### Key Differences Identified

1. **Message Detail Level**: Exercises provide detailed change tracking, lift-logs provide entry descriptions
2. **Administrative Controls**: Exercises support global imports, lift-logs are personal-only
3. **Dependency Handling**: Lift-logs require existing exercises, exercises are self-contained
4. **Update Granularity**: Different approaches to detecting and handling data changes
5. **Error Reporting**: Different levels of detail in error messages and warnings

## Components and Interfaces

### TsvImporterService Methods

**Current Methods:**
- `importLiftLogs(string $tsvData, string $date, int $userId): array`
- `importExercises(string $tsvData, int $userId, bool $importAsGlobal = false): array`

**Return Structure Differences:**

**Lift-Log Returns:**
```php
[
    'importedCount' => int,
    'updatedCount' => int,
    'notFound' => array,           // Missing exercise names
    'invalidRows' => array,        // Malformed data
    'importedEntries' => array,    // Descriptive strings
    'updatedEntries' => array      // Descriptive strings
]
```

**Exercise Returns:**
```php
[
    'importedCount' => int,
    'updatedCount' => int,
    'skippedCount' => int,
    'invalidRows' => array,
    'importedExercises' => array,  // Detailed exercise data
    'updatedExercises' => array,   // With change tracking
    'skippedExercises' => array,   // With skip reasons
    'importMode' => string         // 'global' or 'personal'
]
```

### Controller Message Building

**Lift-Log Controller:**
- Builds HTML messages with conditional logic
- Handles error cases first, then success cases
- Provides detailed lists for small imports
- Includes warnings for missing exercises

**Exercise Controller:**
- Uses dedicated `buildImportSuccessMessage()` method
- Structured HTML with consistent formatting
- Always shows detailed lists regardless of size
- Includes change details for updates

## Data Models

### Duplicate Detection Logic

**Lift-Logs:**
```php
// Complex matching logic
$existingLiftLogs = LiftLog::with('liftSets')
    ->where('user_id', $userId)
    ->where('exercise_id', $exercise->id)
    ->where('logged_at', $loggedAt->format('Y-m-d H:i:s'))
    ->get();

// Check if sets match (count, weight, reps)
foreach ($existingLiftLogs as $existingLiftLog) {
    if ($existingLiftLog->liftSets->count() === (int)$rounds) {
        $allSetsMatch = true;
        foreach ($existingLiftLog->liftSets as $set) {
            if (!($set->weight == $weight && $set->reps == $reps)) {
                $allSetsMatch = false;
                break;
            }
        }
    }
}
```

**Exercises:**
```php
// Simple case-insensitive title matching
$existingExercise = Exercise::whereRaw('LOWER(title) = ?', [strtolower($title)])
    ->where(/* scope conditions */)
    ->first();
```

### Change Tracking

**Lift-Logs:**
- Only tracks comment changes
- Updates both LiftLog.comments and LiftSet.notes
- No detailed change reporting

**Exercises:**
- Tracks all field changes (description, is_bodyweight)
- Provides before/after values
- Detailed change reporting in success messages

## Error Handling

### Current Error Handling Patterns

**Lift-Logs:**
1. **Missing Exercises**: Collects not-found exercise names, shows list or count
2. **Invalid Rows**: Tracks malformed data, shows in warnings
3. **Empty Data**: Returns error message
4. **No Imports**: Shows "no new data" message

**Exercises:**
1. **Permission Errors**: Admin-only global import validation
2. **Empty Data**: Validation error
3. **Conflicts**: Detailed conflict resolution messages
4. **Invalid Rows**: Tracks and reports invalid data

### Consistency Issues

1. **Error Message Format**: Different HTML structures and styling
2. **Validation Timing**: Different approaches to input validation
3. **User Feedback**: Inconsistent detail levels and formatting
4. **Recovery Guidance**: Varying levels of actionable advice

## Testing Strategy

### Current Test Coverage

**Lift-Log Tests:**
- `LiftLogTsvImportDetailedMessagesTest`: Message formatting
- `LiftLogTsvImportMultipleRowsTest`: Bulk import scenarios
- `LiftLogTsvImportWithGlobalExercisesTest`: Global exercise integration
- `LiftLogTsvImportMissingExercisesTest`: Error handling

**Exercise Tests:**
- `ExerciseTsvImportFeatureTest`: Full feature testing
- `ExerciseTsvImportIntegrationTest`: Integration scenarios
- Unit tests for service methods

### Test Coverage Gaps

1. **Cross-Feature Consistency**: No tests comparing message formats
2. **Permission Parity**: Limited testing of consistent authorization
3. **UI Consistency**: No tests for form layout and behavior
4. **Error Message Standards**: No validation of consistent error formatting

## Design Recommendations

### 1. Standardize Return Structures

**Proposed Unified Return Structure:**
```php
[
    'importedCount' => int,
    'updatedCount' => int,
    'skippedCount' => int,
    'invalidRows' => array,
    'importedItems' => array,    // Standardized item structure
    'updatedItems' => array,     // With change tracking
    'skippedItems' => array,     // With skip reasons
    'importMode' => string,      // 'global', 'personal', or 'n/a'
    'warnings' => array          // Non-fatal issues
]
```

### 2. Unified Message Building

**Proposed MessageBuilder Service:**
```php
class ImportMessageBuilder
{
    public function buildSuccessMessage(array $result, string $itemType): string
    public function buildErrorMessage(array $result, string $itemType): string
    public function formatItemList(array $items, string $type): string
    public function formatChangeDetails(array $changes): string
}
```

### 3. Consistent Error Handling

**Standardized Error Categories:**
1. **Validation Errors**: Input format and required fields
2. **Permission Errors**: Authorization and access control
3. **Data Conflicts**: Duplicate detection and resolution
4. **Dependency Errors**: Missing related data
5. **System Errors**: Unexpected failures

### 4. Enhanced Change Tracking

**Proposed Change Tracking Interface:**
```php
interface ChangeTrackable
{
    public function detectChanges(array $newData): array;
    public function formatChangeDescription(array $changes): string;
}
```

### 5. UI Consistency Standards

**Form Layout Standards:**
- Consistent textarea styling and sizing
- Standardized button placement and styling
- Uniform help text and placeholder formatting
- Consistent success/error message display

**JavaScript Behavior:**
- Standardized copy-to-clipboard functionality
- Consistent form validation and submission
- Uniform loading states and feedback

### 6. Production Environment Controls

**Unified Middleware Approach:**
- Single middleware for all TSV import restrictions
- Consistent environment checking logic
- Standardized 404 error responses
- Unified route protection patterns

## Implementation Phases

### Phase 1: Analysis and Documentation
1. Complete feature comparison analysis
2. Document current behavior differences
3. Identify improvement opportunities
4. Create standardization guidelines

### Phase 2: Service Layer Standardization
1. Create unified return structures
2. Implement consistent error handling
3. Standardize message building
4. Add comprehensive change tracking

### Phase 3: UI/UX Consistency
1. Standardize form layouts and styling
2. Unify JavaScript behavior
3. Consistent success/error messaging
4. Improve user guidance and help text

### Phase 4: Testing and Validation
1. Add cross-feature consistency tests
2. Validate error handling improvements
3. Test UI/UX standardization
4. Performance and security validation