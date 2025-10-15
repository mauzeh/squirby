# Design Document

## Overview

This design extends the existing TSV import/export functionality to support banded exercises. The implementation will modify the `TsvImporterService` to handle `band_type` for exercises and `band_color` for lift sets, and add export functionality to both Exercise and LiftLog controllers.

## Architecture

The solution follows the existing TSV architecture pattern:

1. **Controllers** handle export endpoints and delegate import processing to services
2. **TsvImporterService** processes TSV data and validates banded exercise fields
3. **Export methods** generate TSV content with banded exercise data
4. **Validation** ensures banded exercise data integrity during import

## Components and Interfaces

### 1. Exercise TSV Format Extension

**Current Format:**
```
Title	Description	Is Bodyweight
```

**New Format:**
```
Title	Description	Is Bodyweight	Band Type
```

**Example Data:**
```
Banded Pull-ups	Pull-ups with resistance band	false	resistance
Assisted Dips	Dips with assistance band	false	assistance
Regular Push-ups	Standard push-ups	true	none
```

### 2. LiftLog TSV Format Extension

**Current Format:**
```
Date	Time	Exercise	Weight	Reps	Rounds	Comments
```

**New Format:**
```
Date	Time	Exercise	Weight	Reps	Rounds	Comments	Band Color
```

**Example Data:**
```
10/15/2025	10:00 AM	Banded Pull-ups	0	10	3	Good form	red
10/15/2025	10:15 AM	Regular Bench Press	135	8	3	Felt strong	none
```

### 3. Service Layer Changes

#### TsvImporterService Modifications

**Exercise Import Enhancement:**
- Add `band_type` parsing in `importExercises()` method
- Validate band_type values ('resistance', 'assistance', or 'none')
- Update `processExerciseImport()` methods to handle band_type

**LiftLog Import Enhancement:**
- Add `band_color` parsing in `importLiftLogs()` method
- Validate band_color requirements based on exercise band_type
- Update lift set creation to include band_color

#### New Export Methods

**ExerciseController:**
- Add `exportTsv()` method to generate exercise TSV data
- Include band_type in export format

**LiftLogController:**
- Add `exportTsv()` method to generate lift log TSV data
- Include band_color in export format

## Data Models

### Exercise TSV Mapping
```php
[
    'title' => $exercise->title,
    'description' => $exercise->description,
    'is_bodyweight' => $exercise->is_bodyweight ? 'true' : 'false',
    'band_type' => $exercise->band_type ?? 'none'
]
```

### LiftLog TSV Mapping
```php
[
    'date' => $liftLog->logged_at->format('m/d/Y'),
    'time' => $liftLog->logged_at->format('g:i A'),
    'exercise' => $liftLog->exercise->title,
    'weight' => $liftSet->weight,
    'reps' => $liftSet->reps,
    'rounds' => $liftLog->liftSets->count(),
    'comments' => $liftLog->comments ?? '',
    'band_color' => $liftSet->band_color ?? 'none'
]
```

## Error Handling

### Import Validation Rules

1. **Band Type Validation:**
   - Must be 'resistance', 'assistance', or 'none'
   - Invalid values added to `invalidRows` with descriptive error

2. **Band Color Validation:**
   - Must be a valid band color for banded exercises (band_type is 'resistance' or 'assistance')
   - Must be 'none' for non-banded exercises (band_type is 'none')
   - Must match configured band colors from `config/bands.php` when not 'none'

3. **Cross-Field Validation:**
   - When exercise band_type is 'resistance' or 'assistance', band_color must be a valid band color in lift logs
   - When exercise band_type is 'none', band_color must be 'none'
   - Weight should be 0 for banded exercises (existing logic)

### Error Messages

```php
// Band type validation
"Invalid band type '{$bandType}' - must be 'resistance', 'assistance', or 'none'"

// Band color validation  
"Invalid band color '{$bandColor}' for banded exercise '{$exerciseTitle}' - must be a valid band color"
"Invalid band color '{$bandColor}' for non-banded exercise '{$exerciseTitle}' - must be 'none'"
"Invalid band color '{$bandColor}' - must be one of: red, blue, green, black"
```

## Testing Strategy

### Unit Tests

1. **TsvImporterService Tests:**
   - Test exercise import with valid band_type values
   - Test exercise import with invalid band_type values
   - Test lift log import with band_color for banded exercises
   - Test lift log import with missing band_color for banded exercises
   - Test lift log import with band_color for non-banded exercises

2. **Export Method Tests:**
   - Test exercise TSV export includes band_type column
   - Test lift log TSV export includes band_color column
   - Test export format consistency

### Integration Tests

1. **Controller Tests:**
   - Test full import/export cycle for banded exercises
   - Test validation error handling
   - Test mixed data (banded and non-banded exercises)

### Feature Tests

1. **End-to-End Tests:**
   - Test complete workflow: create banded exercise → log workout → export → import
   - Test error scenarios with invalid data
   - Test data integrity after import/export cycle

## Implementation Notes

### Existing Code Integration

1. **Leverage Existing Patterns:**
   - Follow existing TSV parsing patterns in `TsvImporterService`
   - Use existing validation and error handling structures
   - Maintain consistent return format for import results

2. **Band Configuration Integration:**
   - Use existing `BandService` for band color validation
   - Reference `config/bands.php` for valid band colors
   - Maintain consistency with existing band logic

3. **Model Integration:**
   - Use existing Exercise and LiftSet model relationships
   - Leverage existing fillable fields and validation
   - Maintain existing database constraints

### Performance Considerations

1. **Export Optimization:**
   - Use eager loading for exercise and lift set relationships
   - Stream large exports to avoid memory issues
   - Consider pagination for very large datasets

2. **Import Optimization:**
   - Batch database operations where possible
   - Validate band colors once per import session
   - Use existing duplicate detection logic