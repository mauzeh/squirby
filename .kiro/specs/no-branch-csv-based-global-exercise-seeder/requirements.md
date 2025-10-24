# Requirements Document

## Introduction

This feature will convert the GlobalExerciseSeeder from using hardcoded array data to a CSV-based approach, similar to how the IngredientSeeder currently works. This will make it easier to maintain and update the global exercise list by allowing data to be managed in a CSV file rather than requiring code changes.

## Requirements

### Requirement 1

**User Story:** As a developer, I want the GlobalExerciseSeeder to read exercise data from a CSV file, so that I can easily maintain and update the global exercise list without modifying code.

#### Acceptance Criteria

1. WHEN the GlobalExerciseSeeder runs THEN it SHALL read exercise data from a CSV file located at `database/seeders/csv/exercises_from_real_world.csv`
2. WHEN processing the CSV data THEN the seeder SHALL convert CSV format to TSV format for consistent processing
3. WHEN creating exercises THEN the seeder SHALL use `firstOrCreate` to avoid duplicates based on title and user_id being null
4. WHEN an exercise has `is_bodyweight` set to true in the CSV THEN the seeder SHALL set the `is_bodyweight` field to true in the database

### Requirement 2

**User Story:** As a developer, I want the CSV file to contain all current global exercises, so that no existing functionality is lost during the migration.

#### Acceptance Criteria

1. WHEN the CSV file is created THEN it SHALL contain all exercises currently defined in the GlobalExerciseSeeder array
2. WHEN the CSV includes exercise data THEN it SHALL have columns for title, description, and is_bodyweight
3. WHEN an exercise has no description THEN the CSV SHALL contain an empty string for that field
4. WHEN an exercise is not bodyweight THEN the CSV SHALL contain false or 0 for the is_bodyweight field

### Requirement 3

**User Story:** As a developer, I want the seeder to handle CSV parsing errors gracefully, so that the seeding process doesn't fail unexpectedly.

#### Acceptance Criteria

1. WHEN CSV parsing encounters an error THEN the seeder SHALL skip malformed rows and continue processing
2. WHEN a row has missing title field THEN the seeder SHALL skip that row
3. WHEN the CSV file is missing THEN the seeder SHALL fail with a clear error message
4. WHEN processing completes THEN the seeder SHALL complete successfully without additional logging (keeping it simple like the original seeder)

### Requirement 4

**User Story:** As a developer, I want the new CSV-based approach to follow similar patterns as the IngredientSeeder where appropriate, so that the codebase remains consistent.

#### Acceptance Criteria

1. WHEN implementing the CSV processing THEN the seeder SHALL use file() to read CSV lines similar to IngredientSeeder
2. WHEN parsing CSV rows THEN the seeder SHALL use str_getcsv for parsing similar to IngredientSeeder
3. WHEN processing is simpler than ingredients THEN the seeder SHALL NOT require a separate processor service
4. WHEN creating exercises THEN the seeder SHALL use direct model creation similar to the original GlobalExerciseSeeder approach