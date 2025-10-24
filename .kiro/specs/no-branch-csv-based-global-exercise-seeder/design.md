# Design Document

## Overview

This design outlines the conversion of the GlobalExerciseSeeder from a hardcoded array-based approach to a CSV-based approach that mirrors the IngredientSeeder implementation. The solution will maintain all existing functionality while providing better maintainability through external data management.

## Architecture

The new CSV-based GlobalExerciseSeeder will follow the same architectural pattern as the IngredientSeeder:

1. **CSV File Storage**: Exercise data will be stored in `database/seeders/csv/exercises_from_real_world.csv`
2. **CSV to TSV Conversion**: The seeder will convert CSV format to TSV for consistent processing
3. **Row-by-row Processing**: Each CSV row will be processed individually with error handling
4. **Database Interaction**: Uses `firstOrCreate` to prevent duplicates

## Components and Interfaces

### CSV File Structure

The CSV file will have the following columns:
- `title` (string, required): The exercise name
- `description` (string, optional): Exercise description 
- `is_bodyweight` (boolean, optional): Whether the exercise is bodyweight (1/true or 0/false)

Example CSV structure:
```csv
title,description,is_bodyweight
"Back Squat","A compound exercise that targets the muscles of the legs and core.",0
"Chin-Ups","A bodyweight pulling exercise with supinated grip.",1
"Bench Press","A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.",0
```

### Seeder Class Structure

The GlobalExerciseSeeder will be refactored to include:

```php
class GlobalExercisesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Read CSV file
        // 2. Convert CSV to TSV
        // 3. Process each row with callback
        // 4. Log results
    }
    
    private function processExerciseRow(array $rowData): void
    {
        // Handle individual exercise creation
    }
}
```

## Data Models

### Exercise Model Fields

The seeder will populate the following Exercise model fields:
- `title`: From CSV 'title' column
- `description`: From CSV 'description' column (empty string if not provided)
- `is_bodyweight`: From CSV 'is_bodyweight' column (defaults to false)
- `user_id`: Always null for global exercises

### CSV Data Validation

Each row will be validated for:
- Required `title` field presence
- Valid boolean conversion for `is_bodyweight`
- Non-empty title after trimming

## Error Handling

### File-level Errors
- Missing CSV file: Allow seeder to fail with clear error message
- File read permissions: Allow seeder to fail with clear error message

### Row-level Errors
- Missing title: Skip row silently (exercises are simpler than ingredients)
- Invalid boolean value: Use default (false)
- Malformed CSV row: Skip row silently

### Processing Results
- Simple processing without complex error tracking (unlike IngredientSeeder which uses a processor service)

## Testing Strategy

### Unit Tests
- Test CSV parsing with valid data
- Test error handling for malformed CSV
- Test boolean conversion logic
- Test duplicate prevention with `firstOrCreate`

### Integration Tests
- Test full seeder execution with sample CSV
- Test seeder behavior with missing CSV file
- Test seeder behavior with empty CSV file
- Verify all current exercises are preserved after migration

### Data Migration Verification
- Compare exercise count before and after migration
- Verify all exercise attributes are correctly preserved
- Ensure no duplicate exercises are created

## Implementation Approach

### Phase 1: Create CSV File
1. Extract current exercise data from GlobalExerciseSeeder
2. Create CSV file with proper headers and data
3. Validate CSV format and content

### Phase 2: Refactor Seeder
1. Modify GlobalExerciseSeeder to read from CSV
2. Implement CSV to TSV conversion logic
3. Add row processing callback function
4. Implement error handling and logging

### Phase 3: Testing and Validation
1. Create comprehensive tests
2. Verify data integrity
3. Test error scenarios
4. Performance validation

## Migration Considerations

### Backward Compatibility
- The seeder will maintain the same public interface
- All existing exercises will be preserved
- Database schema remains unchanged

### Data Integrity
- Use `firstOrCreate` to prevent duplicates
- Maintain existing exercise IDs where possible
- Preserve all exercise attributes

### Performance
- CSV processing is lightweight for the expected data volume (~25 exercises)
- File I/O is minimal and acceptable for seeding operations
- Memory usage is negligible for this dataset size