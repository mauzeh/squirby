# Design Document

## Overview

This design transforms the existing `PersistMissingGlobalExercises` console command (renamed to `SyncGlobalExercisesToCsv`) from a simple "append missing exercises" operation into a comprehensive synchronization tool. The enhanced command will perform a complete sync of all global exercises from the database to the CSV file, using canonical name as the unique identifier for matching and updating existing entries. Additionally, the design includes updates to the `GlobalExercisesSeeder` to properly handle the `band_type` field when importing exercises from the CSV file.

## Architecture

The solution follows a three-phase approach for the console command:

1. **Data Collection Phase**: Read all global exercises from database and parse existing CSV entries
2. **Comparison Phase**: Match exercises by canonical name and identify differences
3. **Synchronization Phase**: Update existing CSV entries and create new ones as needed

Additionally, the `GlobalExercisesSeeder` will be enhanced to:

1. **CSV Parsing**: Read and process the `band_type` column
2. **Data Import**: Set the `band_type` field on exercises during import

The command maintains the existing Laravel Artisan command structure while significantly expanding its functionality to handle updates, not just insertions.

## Components and Interfaces

### Enhanced Console Command Structure

```php
class SyncGlobalExercisesToCsv extends Command
{
    protected $signature = 'exercises:sync-global-to-csv';
    protected $description = 'Synchronize all global exercises to the CSV file';
    
    public function handle()
    {
        // Environment validation
        // CSV file validation  
        // Data collection and parsing
        // Exercise matching and comparison
        // User confirmation
        // CSV synchronization
        // Backup and file operations
    }
}
```

### Data Structures

**Exercise Data Array**:
```php
[
    'title' => string,
    'description' => string,
    'canonical_name' => string,
    'is_bodyweight' => boolean,
    'band_type' => string|null
]
```

**CSV Entry Structure**:
```php
[
    'canonical_name' => string,
    'original_data' => array,
    'needs_update' => boolean,
    'changes' => array
]
```

### Core Methods

#### 1. Data Collection Methods

```php
private function collectGlobalExercises(): Collection
{
    return Exercise::whereNull('user_id')
        ->whereNotNull('canonical_name')
        ->get();
}

private function parseExistingCsv(string $csvPath): array
{
    // Read CSV file
    // Parse header and data rows
    // Index by canonical_name for fast lookup
    // Return structured data array
}
```

#### 2. Comparison and Matching

```php
private function compareExercises(Exercise $dbExercise, array $csvData): array
{
    // Compare title, description, is_bodyweight, band_type
    // Return array of differences
}

private function identifyChanges(Collection $globalExercises, array $csvData): array
{
    // Match by canonical_name
    // Identify updates needed and new entries
    // Return categorized changes
}
```

#### 3. CSV Operations

```php
private function writeSynchronizedCsv(string $csvPath, array $header, array $allExercises): void
{
    // Write complete CSV with updates and new entries
    // Maintain proper CSV formatting
    // Handle file operations safely
}
```

## Data Models

### CSV Header Structure

The CSV file will maintain its current structure with the addition of a `band_type` column:

```
title,description,is_bodyweight,canonical_name,band_type
```

### Field Mapping

| Database Field | CSV Column | Data Type | Notes |
|---------------|------------|-----------|-------|
| title | title | string | Exercise name |
| description | description | string | Exercise description (empty string if null) |
| is_bodyweight | is_bodyweight | boolean | Converted to '1'/'0' in CSV |
| canonical_name | canonical_name | string | Unique identifier for matching |
| band_type | band_type | string | 'resistance', 'assistance', or empty string if null |

### Exercise Matching Logic

1. **Primary Key**: `canonical_name` is used as the unique identifier
2. **Matching Process**: 
   - Index existing CSV entries by canonical_name
   - For each database exercise, check if canonical_name exists in CSV
   - If exists, compare all fields for differences
   - If not exists, mark as new entry

## Error Handling

### Validation Checks

1. **Environment Validation**: Command only runs in local environment
2. **File Existence**: CSV file must exist before synchronization
3. **Canonical Name Validation**: Skip exercises without canonical_name with warning
4. **File Permissions**: Ensure CSV file is writable

### Error Recovery

```php
// Handle missing canonical names
if (empty($exercise->canonical_name)) {
    $this->warn("Skipping exercise '{$exercise->title}' - missing canonical name");
    continue;
}

// Handle file operation failures
try {
    $this->writeSynchronizedCsv($csvPath, $header, $allExercises);
} catch (Exception $e) {
    $this->error("Failed to write CSV: {$e->getMessage()}");
    return Command::FAILURE;
}
```

### Graceful Degradation

- Skip exercises with missing canonical names rather than failing completely
- Continue processing if individual exercise updates fail
- Provide detailed error messages for troubleshooting

## Testing Strategy

### Unit Tests

1. **CSV Parsing Tests**:
   - Test parsing of valid CSV files
   - Test handling of malformed CSV entries
   - Test header validation

2. **Exercise Comparison Tests**:
   - Test field comparison logic
   - Test change detection accuracy
   - Test canonical name matching

3. **File Operation Tests**:
   - Test backup creation
   - Test CSV writing with various data sets
   - Test file handle management

### Integration Tests

1. **End-to-End Synchronization**:
   - Test complete sync process with known data
   - Verify CSV output matches expected format
   - Test with mixed update/create scenarios

2. **Error Handling Tests**:
   - Test behavior with missing CSV file
   - Test handling of exercises without canonical names
   - Test file permission issues

### Feature Tests

1. **Command Execution Tests**:
   - Test command in local environment
   - Test environment restriction enforcement
   - Test user confirmation flow

2. **Data Integrity Tests**:
   - Verify no data loss during synchronization
   - Test CSV format preservation

## Performance Considerations

### Memory Management

- Process exercises in batches if dataset is large
- Use generators for CSV reading to handle large files
- Minimize memory footprint during comparison operations

### File I/O Optimization

- Read CSV file once and index in memory
- Write complete CSV in single operation
- Use appropriate file buffering for large datasets

### Scalability

- Design supports hundreds of exercises efficiently
- Minimal database queries (single query for all global exercises)
- Fast lookup using indexed arrays for comparison

## Security Considerations

### Environment Restrictions

- Command only executes in local environment
- Prevents accidental production data modification
- Clear error message for non-local execution attempts

### File Operations

- Validate file paths to prevent directory traversal
- Proper file handle cleanup to prevent resource leaks

### Data Validation

- Validate canonical names before processing
- Sanitize data before CSV writing
- Handle special characters in CSV fields properly

## GlobalExercisesSeeder Enhancements

### Enhanced CSV Processing

The seeder will be updated to handle the `band_type` field:

```php
// Enhanced exercise data processing
$exercise = [
    'title' => $exerciseData['title'],
    'description' => $exerciseData['description'] ?? '',
    'user_id' => null,
    'canonical_name' => $exerciseData['canonical_name'] ?? null,
];

// Handle is_bodyweight (existing logic)
if ($isBodyweight) {
    $exercise['is_bodyweight'] = true;
}

// Handle band_type (new logic)
if (isset($exerciseData['band_type']) && !empty($exerciseData['band_type'])) {
    $validBandTypes = ['resistance', 'assistance'];
    if (in_array($exerciseData['band_type'], $validBandTypes)) {
        $exercise['band_type'] = $exerciseData['band_type'];
    }
}
```



### Change Reporting

Enhanced change detection to include `band_type` updates:

```php
// Check band_type changes
if (($existingExercise->band_type ?? null) !== ($exercise['band_type'] ?? null)) {
    $changes[] = "band_type: '{$existingExercise->band_type}' → '{$exercise['band_type']}'";
}