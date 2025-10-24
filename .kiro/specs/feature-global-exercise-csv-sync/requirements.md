# Requirements Document

## Introduction

This feature enhances the existing console command `exercises:persist-missing-global` by renaming it to `exercises:sync-global-to-csv` and transforming it to perform a complete synchronization of all global exercises from the database to the CSV file. Instead of only adding missing exercises, the command will update existing CSV entries and create new ones as needed, using canonical name as the unique identifier for matching. Additionally, this feature updates the GlobalExercisesSeeder to properly handle the band_type field when reading from the CSV file.

## Glossary

- **Global Exercise**: An exercise record in the database with `user_id = null`
- **CSV File**: The file located at `database/seeders/csv/exercises_from_real_world.csv`
- **Canonical Name**: A unique identifier field used to match exercises between database and CSV
- **Console Command**: The Laravel Artisan command `exercises:sync-global-to-csv` (renamed from `exercises:persist-missing-global`)
- **Sync Operation**: The process of ensuring CSV data matches database data through updates and inserts
- **Banded Exercise**: An exercise with a `band_type` field set to 'resistance' or 'assistance'
- **Band Type**: A field indicating the type of band used ('resistance', 'assistance', or null for non-banded exercises)
- **GlobalExercisesSeeder**: The seeder class that reads from the CSV file to populate global exercises in the database

## Requirements

### Requirement 1

**User Story:** As a developer, I want the console command to sync all global exercises to the CSV file, so that the CSV always reflects the current state of global exercises in the database.

#### Acceptance Criteria

1. WHEN the console command is executed, THE Console Command SHALL retrieve all global exercises from the database
2. THE Console Command SHALL read the existing CSV file and parse all current entries
3. THE Console Command SHALL use canonical name as the primary key for matching exercises between database and CSV
4. THE Console Command SHALL update existing CSV entries when the canonical name matches but other fields differ
5. THE Console Command SHALL create new CSV entries for global exercises that do not exist in the CSV
6. THE Console Command SHALL handle banded exercises by including the band_type field in the synchronization

### Requirement 2

**User Story:** As a developer, I want the command to handle CSV updates intelligently, so that existing data is preserved when appropriate and updated when necessary.

#### Acceptance Criteria

1. WHEN a global exercise canonical name matches an existing CSV entry, THE Console Command SHALL compare all relevant fields
2. IF any field differs between database and CSV, THEN THE Console Command SHALL update the CSV entry with database values
3. THE Console Command SHALL update the title field when it differs from the database value
4. THE Console Command SHALL update the is_bodyweight field when it differs from the database value
5. THE Console Command SHALL update the description field when it differs from the database value
6. THE Console Command SHALL update the band_type field when it differs from the database value

### Requirement 3

**User Story:** As a developer, I want the command to provide clear feedback about what changes are being made, so that I can understand the impact of the synchronization.

#### Acceptance Criteria

1. THE Console Command SHALL display the total number of global exercises found in the database
2. THE Console Command SHALL display the number of CSV entries that will be updated
3. THE Console Command SHALL display the number of new CSV entries that will be created
4. THE Console Command SHALL list the specific exercises being updated or created
5. THE Console Command SHALL ask for user confirmation before making any changes to the CSV file

### Requirement 4

**User Story:** As a developer, I want the command to maintain data integrity and handle edge cases, so that the synchronization process is robust and reliable.

#### Acceptance Criteria

1. THE Console Command SHALL only run in the local environment for security reasons
2. IF the CSV file does not exist, THEN THE Console Command SHALL return an error message
3. THE Console Command SHALL handle exercises with missing canonical names by skipping them with a warning
4. THE Console Command SHALL preserve the CSV file structure and formatting

### Requirement 5

**User Story:** As a developer, I want the command to handle the complete CSV rewrite efficiently, so that the operation completes successfully even with large datasets.

#### Acceptance Criteria

1. THE Console Command SHALL rewrite the entire CSV file with updated and new data
2. THE Console Command SHALL maintain the original CSV header structure
3. THE Console Command SHALL preserve the order of existing entries where possible
4. THE Console Command SHALL append new entries at the end of the CSV file
5. THE Console Command SHALL close file handles properly to prevent resource leaks

### Requirement 6

**User Story:** As a developer, I want the GlobalExercisesSeeder to properly handle banded exercises from the CSV file, so that band_type information is correctly imported into the database.

#### Acceptance Criteria

1. THE GlobalExercisesSeeder SHALL read the band_type column from the CSV file
2. THE GlobalExercisesSeeder SHALL set the band_type field on exercises when importing from CSV
3. THE GlobalExercisesSeeder SHALL validate band_type values are 'resistance', 'assistance', or empty/null
4. THE GlobalExercisesSeeder SHALL report band_type changes when updating existing exercises